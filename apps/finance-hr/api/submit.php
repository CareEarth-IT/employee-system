<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/chat.php';

$user = require_login_api();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['error' => 'POSTのみ許可されています'], 405);
}

$raw = file_get_contents('php://input');
$payload = json_decode($raw ?: '', true);
if (!is_array($payload)) {
    json_response(['error' => '不正なリクエストです'], 400);
}

$type = trim((string) ($payload['type'] ?? ''));
$title = trim((string) ($payload['title'] ?? ''));
$body = trim((string) ($payload['body'] ?? ''));
$categoryKey = trim((string) ($payload['category'] ?? ''));
$attachments = $payload['attachments'] ?? [];

if ($type === '' || $title === '' || $body === '') {
    json_response(['error' => 'すべての項目を入力してください'], 400);
}

if ($categoryKey === '') {
    $categoryKey = category_key_for_type($type);
}

if (! category_for_key($categoryKey) || ! is_type_in_category($type, $categoryKey)) {
    json_response(['error' => '選択カテゴリとお問い合わせ内容が一致しません'], 400);
}

if (mb_strlen($title) > 30) {
    json_response(['error' => 'タイトルは30字以内にしてください'], 400);
}

if (!is_array($attachments)) {
    $attachments = [];
}

if (is_onboarding_doc_type($type) && count($attachments) === 0) {
    json_response(['error' => '入社書類提出の場合は、添付が1件以上必要です'], 400);
}

if (count($attachments) > 10) {
    json_response(['error' => '添付は最大10件までです'], 400);
}

$uploadDir = __DIR__ . '/../uploads';
if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
    json_response(['error' => 'アップロード先の作成に失敗しました'], 500);
}

$allowedExt = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp'];
$maxBytes = 5 * 1024 * 1024;
$savedFiles = [];
$sheetKey = sheet_key_for_category($categoryKey);
$attachmentUrls = [];

$pdo = db();
$pdo->beginTransaction();

try {
    $stmt = $pdo->prepare(
        'INSERT INTO inquiries (user_id, sheet_key, inquiry_type, title, body, status, flag)
         VALUES (?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([(int) $user['id'], $sheetKey, $type, $title, $body, '未対応', '未']);
    $inquiryId = (int) $pdo->lastInsertId();

    $attachStmt = $pdo->prepare(
        'INSERT INTO inquiry_attachments
         (inquiry_id, original_name, stored_name, mime_type, file_size)
         VALUES (?, ?, ?, ?, ?)'
    );

    foreach ($attachments as $item) {
        if (!is_array($item)) {
            throw new RuntimeException('添付データの形式が不正です');
        }

        $name = basename((string) ($item['name'] ?? 'file'));
        $mime = (string) ($item['mimeType'] ?? '');
        $data = (string) ($item['data'] ?? '');
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));

        if ($data === '') {
            throw new RuntimeException('空の添付ファイルがあります');
        }
        if (!in_array($ext, $allowedExt, true) && strpos($mime, 'image/') !== 0) {
            throw new RuntimeException('対応形式外のファイルです: ' . $name);
        }

        $binary = base64_decode($data, true);
        if ($binary === false) {
            throw new RuntimeException('ファイルのデコードに失敗しました: ' . $name);
        }
        if (strlen($binary) > $maxBytes) {
            throw new RuntimeException('1ファイル5MB以内にしてください: ' . $name);
        }

        $stored = sprintf(
            '%d_%s_%s.%s',
            $inquiryId,
            date('YmdHis'),
            bin2hex(random_bytes(4)),
            $ext !== '' ? $ext : 'bin'
        );
        $path = $uploadDir . DIRECTORY_SEPARATOR . $stored;
        if (file_put_contents($path, $binary) === false) {
            throw new RuntimeException('ファイルの保存に失敗しました: ' . $name);
        }

        $attachStmt->execute([
            $inquiryId,
            $name,
            $stored,
            $mime,
            strlen($binary),
        ]);
        $savedFiles[] = $path;
        $attachmentUrls[] = 'api/download.php?id=' . (int) $pdo->lastInsertId();
    }

    $pdo->commit();

    $profile = [
        'fullName' => (string) $user['full_name'],
        'employeeId' => (string) ($user['employee_id'] ?? ''),
        'company' => (string) $user['company'],
        'department' => (string) $user['department'],
        'email' => (string) $user['email'],
    ];
    $formData = [
        'type' => $type,
        'title' => $title,
        'body' => $body,
        'attachmentUrls' => implode("\n", $attachmentUrls),
    ];
    $chat = send_chat_notification($profile, $formData, $inquiryId, $sheetKey);

    json_response([
        'ok' => true,
        'success' => true,
        'id' => $inquiryId,
        'sheetKey' => $sheetKey,
        'chatNotified' => (bool) $chat['ok'],
        'chatStatus' => (int) $chat['status'],
        'chatError' => $chat['ok'] ? '' : chat_failure_hint((int) $chat['status'], (string) $chat['body']),
    ]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    foreach ($savedFiles as $path) {
        if (is_file($path)) {
            @unlink($path);
        }
    }
    json_response(['error' => $e->getMessage()], 500);
}

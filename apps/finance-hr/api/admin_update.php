<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';

$user = require_admin_api();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['error' => 'POSTのみ許可されています'], 405);
}

$raw = file_get_contents('php://input');
$payload = json_decode($raw ?: '', true);
if (!is_array($payload)) {
    json_response(['error' => '不正なリクエストです'], 400);
}

$row = (int) ($payload['row'] ?? 0);
$sheetKey = (string) ($payload['sheetKey'] ?? 'main');
$status = trim((string) ($payload['status'] ?? ''));
$comment = (string) ($payload['comment'] ?? '');
$tantousha = trim((string) ($payload['tantousha'] ?? ''));
$categoryKey = trim((string) ($payload['category'] ?? ''));

if ($row <= 0) {
    json_response(['success' => false, 'message' => '不正な行番号です']);
}

$allowedStatus = ['未対応', '対応中', '解決済'];
if (!in_array($status, $allowedStatus, true)) {
    json_response(['success' => false, 'message' => '進捗の値が不正です']);
}

$stmt = db()->prepare(
    'SELECT id, sheet_key, inquiry_type, flag FROM inquiries WHERE id = ? LIMIT 1'
);
$stmt->execute([$row]);
$inquiry = $stmt->fetch();

if (!$inquiry || ($inquiry['sheet_key'] ?: 'main') !== $sheetKey) {
    json_response(['success' => false, 'message' => '対象データが見つかりません']);
}

$perm = assert_can_edit_inquiry($user, $sheetKey, (string) $inquiry['inquiry_type']);
if (!$perm['ok']) {
    json_response(['success' => false, 'message' => $perm['message']]);
}

if (($inquiry['flag'] ?? '') === '済') {
    json_response(['success' => false, 'message' => '上役確認済のため編集できません。']);
}

$newSheetKey = $sheetKey;
if ($categoryKey !== '') {
    $cat = category_for_key($categoryKey);
    if ($cat === null) {
        json_response(['success' => false, 'message' => '担当部署の指定が不正です']);
    }
    $newSheetKey = (string) ($cat['sheet_key'] ?? 'main');
}

$taiousha = (string) $user['full_name'];

$upd = db()->prepare(
    'UPDATE inquiries
     SET status = ?, comment = ?, tantousha = ?, taiousha = ?, sheet_key = ?, updated_at = NOW()
     WHERE id = ?'
);
$upd->execute([$status, $comment, $tantousha, $taiousha, $newSheetKey, $row]);

json_response([
    'success' => true,
    'updatedAt' => format_datetime_sec(date('Y-m-d H:i:s')),
    'tantousha' => $tantousha,
    'taiousha' => $taiousha,
    'sheetKey' => $newSheetKey,
    'category' => category_key_for_sheet($newSheetKey),
    'categoryLabel' => category_label_for_sheet($newSheetKey),
]);

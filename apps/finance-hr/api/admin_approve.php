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

if ($row <= 0) {
    json_response(['success' => false, 'message' => '不正な行番号です']);
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
    json_response(['success' => false, 'message' => 'すでに上役確認済です。']);
}

$upd = db()->prepare(
    'UPDATE inquiries SET flag = ?, flag_at = NOW(), updated_at = NOW() WHERE id = ?'
);
$upd->execute(['済', $row]);

$email = normalize_email((string) $user['email']);
$target = normalize_email((string) app_config()['approver_target_email']);
$isMatch = $email !== '' && $email === $target;

$audit = db()->prepare(
    'INSERT INTO approval_audit
     (inquiry_id, operator_name, operator_email, approver_match)
     VALUES (?, ?, ?, ?)'
);
$audit->execute([
    $row,
    (string) $user['full_name'],
    $email !== '' ? $email : '(メール未取得)',
    $isMatch ? 1 : 0,
]);

json_response([
    'success' => true,
    'flagAt' => format_datetime_sec(date('Y-m-d H:i:s')),
    'approverMatch' => $isMatch,
    'approverEmail' => $email,
]);

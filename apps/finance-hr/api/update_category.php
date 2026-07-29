<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';

$user = require_admin_api();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['error' => 'POSTのみ許可されています'], 405);
}

$raw = file_get_contents('php://input');
$payload = json_decode($raw ?: '', true);
if (! is_array($payload)) {
    json_response(['error' => '不正なリクエストです'], 400);
}

$rowId = (int) ($payload['row'] ?? 0);
$categoryKey = trim((string) ($payload['category'] ?? ''));

if ($rowId < 1) {
    json_response(['error' => 'お問い合わせ番号が不正です'], 400);
}

$cat = category_for_key($categoryKey);
if ($cat === null) {
    json_response(['error' => '担当部署の指定が不正です'], 400);
}

$sheetKey = (string) ($cat['sheet_key'] ?? 'main');

$stmt = db()->prepare(
    'SELECT id, sheet_key, inquiry_type, flag FROM inquiries WHERE id = ? LIMIT 1'
);
$stmt->execute([$rowId]);
$inquiry = $stmt->fetch();

if (! $inquiry) {
    json_response(['error' => '対象のお問い合わせが見つかりません'], 404);
}

$currentSheet = (string) ($inquiry['sheet_key'] ?: 'main');
$perm = assert_can_edit_inquiry($user, $currentSheet, (string) $inquiry['inquiry_type']);
if (! $perm['ok']) {
    json_response(['error' => $perm['message']], 403);
}

if (($inquiry['flag'] ?? '') === '済') {
    json_response(['error' => '上役確認済のため編集できません。'], 403);
}

$update = db()->prepare(
    'UPDATE inquiries SET sheet_key = ?, updated_at = NOW() WHERE id = ?'
);
$update->execute([$sheetKey, $rowId]);

json_response([
    'ok' => true,
    'row' => $rowId,
    'sheetKey' => $sheetKey,
    'category' => $categoryKey,
    'categoryLabel' => (string) ($cat['label'] ?? $categoryKey),
]);

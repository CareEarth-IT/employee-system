<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';

$user = require_login_api();

$stmt = db()->prepare(
    'SELECT id, sheet_key, inquiry_type, title, body, status, created_at
     FROM inquiries
     WHERE user_id = ?
     ORDER BY id DESC
     LIMIT 50'
);
$stmt->execute([(int) $user['id']]);
$rows = $stmt->fetchAll();

$ids = array_map(static fn(array $r): int => (int) $r['id'], $rows);
$attachmentsByInquiry = [];

if ($ids) {
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $aStmt = db()->prepare(
        "SELECT id, inquiry_id, original_name
         FROM inquiry_attachments
         WHERE inquiry_id IN ($placeholders)
         ORDER BY id ASC"
    );
    $aStmt->execute($ids);
    foreach ($aStmt->fetchAll() as $a) {
        $inqId = (int) $a['inquiry_id'];
        $attachmentsByInquiry[$inqId][] = [
            'url' => 'api/download.php?id=' . (int) $a['id'],
            'name' => (string) $a['original_name'],
        ];
    }
}

$history = array_map(static function (array $r) use ($attachmentsByInquiry): array {
    $sheetKey = (string) ($r['sheet_key'] ?: 'main');
    $id = (int) $r['id'];
    return [
        'row' => $id,
        'sheetKey' => $sheetKey,
        'category' => category_key_for_sheet($sheetKey),
        'categoryLabel' => category_label_for_sheet($sheetKey),
        'timestamp' => date('Y/m/d H:i', strtotime($r['created_at'])),
        'title' => $r['title'],
        'type' => $r['inquiry_type'],
        'status' => $r['status'],
        'body' => $r['body'],
        'attachments' => $attachmentsByInquiry[$id] ?? [],
    ];
}, $rows);

json_response($history);

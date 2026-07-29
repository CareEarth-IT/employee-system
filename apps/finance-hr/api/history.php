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

$history = array_map(static function (array $r): array {
    $sheetKey = (string) ($r['sheet_key'] ?: 'main');
    return [
        'row' => (int) $r['id'],
        'sheetKey' => $sheetKey,
        'category' => category_key_for_sheet($sheetKey),
        'categoryLabel' => category_label_for_sheet($sheetKey),
        'timestamp' => date('Y/m/d H:i', strtotime($r['created_at'])),
        'title' => $r['title'],
        'type' => $r['inquiry_type'],
        'status' => $r['status'],
        'body' => $r['body'],
    ];
}, $rows);

json_response($history);

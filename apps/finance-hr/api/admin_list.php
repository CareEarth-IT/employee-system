<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';

$user = require_admin_api();
$session = get_admin_session_for_user($user);

if (! $session['isRegistered'] && ! finance_hr_can_admin($user)) {
    json_response([]);
}

$stmt = db()->query(
    'SELECT i.id, i.sheet_key, i.inquiry_type, i.title, i.body, i.status,
            i.comment, i.tantousha, i.taiousha, i.flag, i.flag_at,
            i.created_at, i.updated_at,
            u.full_name, u.company, u.department, u.email
     FROM inquiries i
     INNER JOIN users u ON u.id = i.user_id
     ORDER BY i.id DESC
     LIMIT 500'
);
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
        $attachmentsByInquiry[$inqId][] = 'api/download.php?id=' . (int) $a['id'];
    }
}

$list = [];
foreach ($rows as $r) {
    $sheetKey = $r['sheet_key'] ?: 'main';
    $type = (string) $r['inquiry_type'];
    $access = resolve_inquiry_access($user, $sheetKey, $type);
    if ($access === 'none') {
        continue;
    }

    $id = (int) $r['id'];
    $urls = $attachmentsByInquiry[$id] ?? [];
    $categoryKey = category_key_for_sheet($sheetKey);
    $list[] = [
        'row' => $id,
        'sheetKey' => $sheetKey,
        'category' => $categoryKey,
        'categoryLabel' => category_label_for_sheet($sheetKey),
        'timestamp' => format_datetime_sec($r['created_at']),
        'title' => $r['title'],
        'name' => $r['full_name'],
        'company' => $r['company'],
        'dept' => $r['department'],
        'email' => $r['email'],
        'type' => $type,
        'body' => $r['body'],
        'status' => $r['status'] ?: '未対応',
        'comment' => $r['comment'] ?? '',
        'tantousha' => $r['tantousha'] ?? '',
        'taiousha' => $r['taiousha'] ?? '',
        'flag' => ($r['flag'] ?? '') === '済' ? '済' : '未',
        'flagAt' => format_datetime_sec($r['flag_at'] ?? null),
        'updatedAt' => format_datetime_sec($r['updated_at']),
        'attachments' => $urls ? implode("\n", $urls) : '',
        'access' => $access,
    ];
}

json_response($list);

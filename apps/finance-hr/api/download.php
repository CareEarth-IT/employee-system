<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';

$user = require_login();

$id = (int) ($_GET['id'] ?? 0);
if ($id <= 0) {
    http_response_code(400);
    echo '不正なリクエストです';
    exit;
}

$stmt = db()->prepare(
    'SELECT a.id, a.original_name, a.stored_name, a.mime_type, a.inquiry_id, i.user_id
     FROM inquiry_attachments a
     INNER JOIN inquiries i ON i.id = a.inquiry_id
     WHERE a.id = ?
     LIMIT 1'
);
$stmt->execute([$id]);
$file = $stmt->fetch();

if (!$file) {
    http_response_code(404);
    echo 'ファイルが見つかりません';
    exit;
}

$isOwner = (int) $file['user_id'] === (int) $user['id'];
if (!$isOwner && !is_admin($user)) {
    http_response_code(403);
    echo '権限がありません';
    exit;
}

$path = __DIR__ . '/../uploads/' . basename((string) $file['stored_name']);
if (!is_file($path)) {
    http_response_code(404);
    echo 'ファイルが存在しません';
    exit;
}

$mime = (string) ($file['mime_type'] ?: 'application/octet-stream');
$name = (string) $file['original_name'];

header('Content-Type: ' . $mime);
header('Content-Length: ' . (string) filesize($path));
header(
    'Content-Disposition: attachment; filename="' . rawurlencode($name) . '"; filename*=UTF-8\'\'' . rawurlencode($name)
);
readfile($path);
exit;

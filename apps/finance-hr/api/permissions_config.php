<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';

$user = require_login_api();

if (! finance_hr_can_manage_permissions($user)) {
    json_response(['error' => '権限設定の編集権限がありません'], 403);
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    json_response([
        'config' => load_permissions_runtime(),
        'permissionAdmins' => finance_hr_permission_config_admin_emails(),
    ]);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['error' => 'Method not allowed'], 405);
}

$raw = file_get_contents('php://input');
$payload = json_decode($raw ?: '{}', true);
if (! is_array($payload)) {
    json_response(['error' => 'Invalid JSON'], 400);
}

$config = $payload['config'] ?? null;
if (! is_array($config)) {
    json_response(['error' => 'config が必要です'], 400);
}

$result = save_permissions_runtime($config, $user);
if (! ($result['ok'] ?? false)) {
    json_response(['error' => (string) ($result['message'] ?? '保存に失敗しました')], 403);
}

json_response([
    'success' => true,
    'config' => $result['config'] ?? load_permissions_runtime(),
]);

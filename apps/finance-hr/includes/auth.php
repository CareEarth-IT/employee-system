<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/staff.php';

function is_allowed_email_domain(string $email): bool
{
    $email = normalize_email($email);
    $at = strrpos($email, '@');
    if ($at === false) {
        return false;
    }
    $domain = substr($email, $at + 1);
    $allowed = app_config()['allowed_email_domains'] ?? [];
    return in_array($domain, $allowed, true);
}

function current_user(): ?array
{
    if (empty($_SESSION['user_id'])) {
        return null;
    }

    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }

    finance_hr_ensure_users_employee_id_column();

    $stmt = db()->prepare(
        'SELECT id, email, employee_id, full_name, company, department, role, staff_label, is_hr_staff
         FROM users WHERE id = ? LIMIT 1'
    );
    $stmt->execute([(int) $_SESSION['user_id']]);
    $user = $stmt->fetch();
    $cached = $user ?: null;

    return $cached;
}

function require_login(): array
{
    $user = current_user();
    if (!$user) {
        // 社員ポータル経由の SSO（未ログイン時は Laravel 側でログイン画面へ）
        header('Location: /apps/finance-hr');
        exit;
    }
    return $user;
}

function require_login_api(): array
{
    $user = current_user();
    if (!$user) {
        json_response([
            'error' => '社員ポータルへのログインが必要です',
            'redirect' => '/apps/finance-hr',
        ], 401);
    }
    return $user;
}

function is_admin(array $user): bool
{
    return finance_hr_can_admin($user);
}

function require_admin(): array
{
    $user = require_login();
    if (! is_admin($user)) {
        http_response_code(403);
        echo '管理者権限が必要です。人事課・経理部（経理課/総務課）・情報システム部・役員、または許可されたアカウントで社員ポータルから開いてください。';
        exit;
    }
    return $user;
}

function require_permission_config_admin(): array
{
    $user = require_login();
    if (! finance_hr_can_manage_permissions($user)) {
        http_response_code(403);
        echo '権限設定の編集権限がありません。FINANCE_HR_PERMISSION_CONFIG_ADMIN_EMAILS に登録されたアカウントで開いてください。';
        exit;
    }

    return $user;
}

function require_admin_api(): array
{
    $user = require_login_api();
    if (! is_admin($user)) {
        json_response(['error' => '管理者権限が必要です'], 403);
    }
    return $user;
}

function format_datetime(?string $dt): string
{
    if ($dt === null || $dt === '') {
        return '';
    }
    $ts = strtotime($dt);
    return $ts ? date('Y/m/d H:i', $ts) : '';
}

function format_datetime_sec(?string $dt): string
{
    if ($dt === null || $dt === '') {
        return '';
    }
    $ts = strtotime($dt);
    return $ts ? date('Y/m/d H:i:s', $ts) : '';
}

function login_user(array $user): void
{
    session_regenerate_id(true);
    $_SESSION['user_id'] = (int) $user['id'];
}

function logout_user(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            (bool) $params['secure'],
            (bool) $params['httponly']
        );
    }
    session_destroy();
}

function json_response(array $data, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

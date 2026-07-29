<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/sso.php';

$token = (string) ($_GET['token'] ?? '');
$identity = finance_hr_verify_sso_token($token);

if ($identity === null) {
    http_response_code(403);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'SSO トークンが無効または期限切れです。社員ポータルから再度開いてください。';
    exit;
}

try {
    unset($_SESSION['finance_hr_can_admin']);
    $user = finance_hr_upsert_sso_user($identity);
} catch (Throwable $e) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'ユーザー同期に失敗しました。管理者にお問い合わせください。';
    exit;
}

login_user($user);
$_SESSION['finance_hr_can_admin'] = ! empty($identity['can_admin'])
    || finance_hr_can_admin($user);

$category = trim((string) ($_GET['category'] ?? ''));
$allowedCategories = ['hr', 'finance', 'is'];
if (in_array($category, $allowedCategories, true)) {
    header('Location: index.php?category='.rawurlencode($category));
    exit;
}

$goAdmin = ! empty($_SESSION['finance_hr_can_admin']);
header('Location: ' . ($goAdmin ? 'admin.php' : 'index.php'));
exit;

<?php
declare(strict_types=1);

/** @var array<string, mixed> $user */
$displayName = trim((string) ($user['full_name'] ?? ''));
if ($displayName === '') {
    $displayName = trim((string) ($user['email'] ?? ''));
}
if ($displayName === '') {
    $displayName = '—';
}
?>
<header class="portal-header">
  <div class="portal-header-inner">
    <a href="/dashboard" class="portal-header-logo">
      <img
        src="/branding/group-web.png"
        alt="Group Web"
        width="388"
        height="97"
      >
    </a>
    <div class="portal-header-actions">
      <span class="portal-header-user"><?= htmlspecialchars($displayName, ENT_QUOTES, 'UTF-8') ?> さん</span>
      <a class="portal-header-logout" href="logout.php">ログアウト</a>
    </div>
  </div>
</header>

<?php
declare(strict_types=1);

/**
 * 独自ログインは廃止。社員ポータル SSO へ誘導する。
 */
header('Location: /apps/finance-hr');
exit;

<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';

$user = require_admin_api();
json_response(get_admin_session_for_user($user));

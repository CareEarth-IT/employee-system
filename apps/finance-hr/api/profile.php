<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';

$user = require_login_api();
finance_hr_ensure_users_employee_id_column();

json_response([
    'fullName' => $user['full_name'],
    'employeeId' => (string) ($user['employee_id'] ?? ''),
    'company' => $user['company'],
    'department' => $user['department'],
    'email' => $user['email'],
]);

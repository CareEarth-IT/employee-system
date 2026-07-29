<?php
/**
 * データベース接続設定
 * 環境変数があれば優先（Cloud Run / ローカル共通）
 */
$host = getenv('FINANCE_HR_DB_HOST');
$socket = getenv('FINANCE_HR_DB_SOCKET');
$user = getenv('FINANCE_HR_DB_USERNAME');
$pass = getenv('FINANCE_HR_DB_PASSWORD');
$name = getenv('FINANCE_HR_DB_DATABASE');

return [
    'host' => ($host !== false && $host !== '') ? $host : '127.0.0.1',
    'port' => (int) ((getenv('FINANCE_HR_DB_PORT') !== false && getenv('FINANCE_HR_DB_PORT') !== '')
        ? getenv('FINANCE_HR_DB_PORT')
        : 3306),
    'unix_socket' => ($socket !== false && $socket !== '') ? $socket : '',
    'dbname' => ($name !== false && $name !== '') ? $name : 'finance_hr',
    'username' => ($user !== false && $user !== '') ? $user : 'root',
    'password' => ($pass !== false) ? $pass : '',
    'charset' => 'utf8mb4',
];

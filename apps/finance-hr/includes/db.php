<?php
declare(strict_types=1);

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $cfg = require __DIR__ . '/../config/database.php';
    if (! empty($cfg['unix_socket'])) {
        $dsn = sprintf(
            'mysql:unix_socket=%s;dbname=%s;charset=%s',
            $cfg['unix_socket'],
            $cfg['dbname'],
            $cfg['charset']
        );
    } else {
        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            $cfg['host'],
            $cfg['port'],
            $cfg['dbname'],
            $cfg['charset']
        );
    }

    $pdo = new PDO($dsn, $cfg['username'], $cfg['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);

    return $pdo;
}

/** users.employee_id が無い環境向けの軽量マイグレーション */
function finance_hr_ensure_users_employee_id_column(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    $stmt = db()->query("SHOW COLUMNS FROM users LIKE 'employee_id'");
    if ($stmt && $stmt->fetch()) {
        return;
    }

    db()->exec(
        "ALTER TABLE users ADD COLUMN employee_id VARCHAR(50) NOT NULL DEFAULT '' AFTER email"
    );
}

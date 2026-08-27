<?php

declare(strict_types=1);

/**
 * Hide real-estate portal users from home 担当者業績一覧 (show_performance = 0).
 * Target: real_estate.careearth_users only. ceemployee is not touched.
 *
 * Env:
 *   DB_SOCKET, DB_DATABASE, DB_USERNAME, DB_PASSWORD
 *   HIDE_EMAILS — comma-separated emails
 */

$socket = getenv('DB_SOCKET');
$database = getenv('DB_DATABASE') ?: 'real_estate';
$user = getenv('DB_USERNAME') ?: 'real_estate_app';
$pass = getenv('DB_PASSWORD') ?: '';
$rawEmails = getenv('HIDE_EMAILS') ?: '';

if ($socket === false || $socket === '') {
    fwrite(STDERR, "FAIL DB_SOCKET is not set\n");
    exit(1);
}

if ($pass === '') {
    fwrite(STDERR, "FAIL DB_PASSWORD is not set\n");
    exit(1);
}

$emails = array_values(array_unique(array_filter(array_map(
    static fn (string $email): string => strtolower(trim($email)),
    explode(',', $rawEmails)
))));

if ($emails === []) {
    fwrite(STDERR, "FAIL HIDE_EMAILS is empty\n");
    exit(1);
}

try {
    $pdo = new PDO(
        "mysql:unix_socket={$socket};dbname={$database};charset=utf8mb4",
        $user,
        $pass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ],
    );

    $select = $pdo->prepare(
        'SELECT id, name, email, show_performance FROM careearth_users WHERE LOWER(email) = ? LIMIT 1'
    );
    $update = $pdo->prepare(
        'UPDATE careearth_users SET show_performance = 0, updated_at = NOW() WHERE id = ?'
    );

    foreach ($emails as $email) {
        $select->execute([$email]);
        $row = $select->fetch(PDO::FETCH_ASSOC);

        if ($row === false) {
            echo "SKIP not found: {$email}\n";
            continue;
        }

        if ((int) ($row['show_performance'] ?? 0) === 0) {
            echo "OK already hidden: {$row['email']} ({$row['name']})\n";
            continue;
        }

        $update->execute([(int) $row['id']]);
        echo "OK hidden: {$row['email']} ({$row['name']})\n";
    }

    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, 'FAIL '.$e->getMessage().PHP_EOL);
    exit(1);
}

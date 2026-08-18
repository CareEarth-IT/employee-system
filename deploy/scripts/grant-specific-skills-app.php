<?php

declare(strict_types=1);

/**
 * Grant specific_skills_app access to specific_skills DB via Cloud SQL unix socket.
 * Run from Cloud Run job only (deploy/grant-specific-skills-app-prod.ps1).
 */

$socket = getenv('DB_SOCKET');
$user = getenv('GRANT_MYSQL_USER') ?: 'root';
$pass = getenv('GRANT_MYSQL_PASSWORD') ?: '';
$dbName = 'specific_skills';
$appUser = 'specific_skills_app';

if ($socket === false || $socket === '') {
    fwrite(STDERR, "FAIL DB_SOCKET is not set\n");
    exit(1);
}

if ($pass === '') {
    fwrite(STDERR, "FAIL GRANT_MYSQL_PASSWORD is not set\n");
    exit(1);
}

try {
    $pdo = new PDO(
        "mysql:unix_socket={$socket};charset=utf8mb4",
        $user,
        $pass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ],
    );

    $hostStmt = $pdo->query(
        'SELECT Host FROM mysql.user WHERE User = '.$pdo->quote($appUser)
    );
    $hosts = $hostStmt->fetchAll(PDO::FETCH_COLUMN);

    if ($hosts === []) {
        throw new RuntimeException('specific_skills_app not found in mysql.user');
    }

    foreach ($hosts as $host) {
        $escapedHost = str_replace('`', '``', (string) $host);
        $grantUser = "`{$appUser}`@`{$escapedHost}`";
        $pdo->exec("GRANT ALL PRIVILEGES ON `{$dbName}`.* TO {$grantUser}");
    }

    $pdo->exec('FLUSH PRIVILEGES');

    $grantUserForShow = '`'.$appUser.'`@`'.str_replace('`', '``', (string) $hosts[0]).'`';
    $stmt = $pdo->query("SHOW GRANTS FOR {$grantUserForShow}");
    $grants = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo 'OK'.PHP_EOL;
    foreach ($grants as $grant) {
        echo $grant.PHP_EOL;
    }

    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, 'FAIL '.$e->getMessage().PHP_EOL);
    exit(1);
}

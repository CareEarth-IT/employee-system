<?php

declare(strict_types=1);

require __DIR__.'/../includes/bootstrap.php';
require __DIR__.'/../includes/db.php';

try {
    $pdo = db();
    $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
    echo 'OK tables='.implode(',', $tables).PHP_EOL;
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, 'FAIL '.$e->getMessage().PHP_EOL);
    exit(1);
}

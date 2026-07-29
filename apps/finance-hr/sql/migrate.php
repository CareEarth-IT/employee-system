<?php
declare(strict_types=1);

/**
 * 既存 DB 向けの差分マイグレーション（データ保持）
 * php migrate.php
 */

require_once __DIR__ . '/../includes/db.php';

$pdo = db();

function columnExists(PDO $pdo, string $table, string $column): bool
{
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
    );
    $stmt->execute([$table, $column]);
    return (int) $stmt->fetchColumn() > 0;
}

function tableExists(PDO $pdo, string $table): bool
{
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?'
    );
    $stmt->execute([$table]);
    return (int) $stmt->fetchColumn() > 0;
}

$alters = [
    ['users', 'staff_label', "ALTER TABLE users ADD COLUMN staff_label VARCHAR(100) NOT NULL DEFAULT '' AFTER role"],
    ['users', 'is_hr_staff', 'ALTER TABLE users ADD COLUMN is_hr_staff TINYINT(1) NOT NULL DEFAULT 0 AFTER staff_label'],
    ['inquiries', 'sheet_key', "ALTER TABLE inquiries ADD COLUMN sheet_key VARCHAR(20) NOT NULL DEFAULT 'main' AFTER user_id"],
    ['inquiries', 'comment', 'ALTER TABLE inquiries ADD COLUMN comment TEXT NULL AFTER status'],
    ['inquiries', 'tantousha', "ALTER TABLE inquiries ADD COLUMN tantousha VARCHAR(100) NOT NULL DEFAULT '' AFTER comment"],
    ['inquiries', 'taiousha', "ALTER TABLE inquiries ADD COLUMN taiousha VARCHAR(100) NOT NULL DEFAULT '' AFTER tantousha"],
    ['inquiries', 'flag', "ALTER TABLE inquiries ADD COLUMN flag VARCHAR(10) NOT NULL DEFAULT '未' AFTER taiousha"],
    ['inquiries', 'flag_at', 'ALTER TABLE inquiries ADD COLUMN flag_at DATETIME NULL AFTER flag'],
];

foreach ($alters as [$table, $column, $sql]) {
    if (!columnExists($pdo, $table, $column)) {
        $pdo->exec($sql);
        echo "added {$table}.{$column}\n";
    } else {
        echo "skip {$table}.{$column}\n";
    }
}

if (!tableExists($pdo, 'approval_audit')) {
    $pdo->exec(
        'CREATE TABLE approval_audit (
          id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
          inquiry_id INT UNSIGNED NOT NULL,
          operator_name VARCHAR(100) NOT NULL DEFAULT \'\',
          operator_email VARCHAR(255) NOT NULL DEFAULT \'\',
          approver_match TINYINT(1) NOT NULL DEFAULT 0,
          created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
          CONSTRAINT fk_audit_inquiry FOREIGN KEY (inquiry_id) REFERENCES inquiries(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
    );
    echo "created approval_audit\n";
} else {
    echo "skip approval_audit\n";
}

$hash = '$2y$10$INLmxQ1al.UviFPuKjDK/uv/kFDvj1yRcXYQdSYV1KTqFcU0MV2x6';
$stmt = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
$stmt->execute(['admin@example.com']);
if (!$stmt->fetch()) {
    $ins = $pdo->prepare(
        'INSERT INTO users (email, password_hash, full_name, company, department, role, staff_label, is_hr_staff)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $ins->execute([
        'admin@example.com',
        $hash,
        '西 デモ',
        'CareEarth株式会社',
        '総務経理部',
        'admin',
        '西',
        0,
    ]);
    echo "inserted admin@example.com\n";
} else {
    $pdo->prepare(
        'UPDATE users SET full_name = ?, company = ?, staff_label = ? WHERE email = ?'
    )->execute(['西 デモ', 'CareEarth株式会社', '西', 'admin@example.com']);
    echo "updated admin@example.com\n";
}

echo "done\n";

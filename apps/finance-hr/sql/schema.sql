-- 社内お問い合わせ DB 初期化
-- mysql --default-character-set=utf8mb4 -u root < schema.sql

SET NAMES utf8mb4;

CREATE DATABASE IF NOT EXISTS finance_hr
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE finance_hr;

DROP TABLE IF EXISTS inquiry_attachments;
DROP TABLE IF EXISTS inquiries;
DROP TABLE IF EXISTS users;

CREATE TABLE users (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  email VARCHAR(255) NOT NULL UNIQUE,
  employee_id VARCHAR(50) NOT NULL DEFAULT '',
  password_hash VARCHAR(255) NOT NULL,
  full_name VARCHAR(100) NOT NULL,
  company VARCHAR(100) NOT NULL DEFAULT '',
  department VARCHAR(100) NOT NULL DEFAULT '',
  role VARCHAR(20) NOT NULL DEFAULT 'user',
  staff_label VARCHAR(100) NOT NULL DEFAULT '',
  is_hr_staff TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE inquiries (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  sheet_key VARCHAR(20) NOT NULL DEFAULT 'main',
  inquiry_type VARCHAR(255) NOT NULL,
  title VARCHAR(30) NOT NULL,
  body TEXT NOT NULL,
  status VARCHAR(20) NOT NULL DEFAULT '未対応',
  comment TEXT NULL,
  tantousha VARCHAR(100) NOT NULL DEFAULT '',
  taiousha VARCHAR(100) NOT NULL DEFAULT '',
  flag VARCHAR(10) NOT NULL DEFAULT '未',
  flag_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_inquiries_user FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE inquiry_attachments (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  inquiry_id INT UNSIGNED NOT NULL,
  original_name VARCHAR(255) NOT NULL,
  stored_name VARCHAR(255) NOT NULL,
  mime_type VARCHAR(100) NOT NULL DEFAULT '',
  file_size INT UNSIGNED NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_attachments_inquiry FOREIGN KEY (inquiry_id) REFERENCES inquiries(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE approval_audit (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  inquiry_id INT UNSIGNED NOT NULL,
  operator_name VARCHAR(100) NOT NULL DEFAULT '',
  operator_email VARCHAR(255) NOT NULL DEFAULT '',
  approver_match TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_audit_inquiry FOREIGN KEY (inquiry_id) REFERENCES inquiries(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE app_settings (
  setting_key VARCHAR(64) NOT NULL PRIMARY KEY,
  setting_value JSON NOT NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  updated_by VARCHAR(255) NOT NULL DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- パスワードはいずれも: password
INSERT INTO users (email, password_hash, full_name, company, department, role, staff_label, is_hr_staff)
VALUES
  (
    'demo@example.com',
    '$2y$10$INLmxQ1al.UviFPuKjDK/uv/kFDvj1yRcXYQdSYV1KTqFcU0MV2x6',
    '山田 太郎',
    'サンプル株式会社',
    '営業部',
    'user',
    '',
    0
  ),
  (
    'admin@example.com',
    '$2y$10$INLmxQ1al.UviFPuKjDK/uv/kFDvj1yRcXYQdSYV1KTqFcU0MV2x6',
    '西 デモ',
    'CareEarth株式会社',
    '総務経理部',
    'admin',
    '西',
    0
  );

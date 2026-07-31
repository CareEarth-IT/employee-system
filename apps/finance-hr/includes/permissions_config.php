<?php
declare(strict_types=1);

const PERMISSIONS_CONFIG_SETTING_KEY = 'permissions_config';

/**
 * @return array{department_groups:array,inquiry_categories:array,type_permission_matrix:array}
 */
function permissions_config_defaults(): array
{
    $cfg = require __DIR__ . '/../config/app.php';

    return [
        'department_groups' => is_array($cfg['department_groups'] ?? null) ? $cfg['department_groups'] : [],
        'inquiry_categories' => is_array($cfg['inquiry_categories'] ?? null) ? $cfg['inquiry_categories'] : [],
        'type_permission_matrix' => is_array($cfg['type_permission_matrix'] ?? null) ? $cfg['type_permission_matrix'] : [],
    ];
}

/** @return list<string> */
function finance_hr_permission_config_admin_emails(): array
{
    $raw = getenv('FINANCE_HR_PERMISSION_CONFIG_ADMIN_EMAILS');
    if (is_string($raw) && trim($raw) !== '') {
        return array_values(array_filter(array_map(
            static fn (string $v): string => normalize_email($v),
            explode(',', $raw),
        )));
    }

    return finance_hr_designated_admin_emails();
}

function finance_hr_can_manage_permissions(?array $user = null): bool
{
    $user = $user ?? current_user();
    if (! $user) {
        return false;
    }

    if (finance_hr_is_cross_cutting_admin($user)) {
        return true;
    }

    $email = normalize_email((string) ($user['email'] ?? ''));

    return $email !== '' && in_array($email, finance_hr_permission_config_admin_emails(), true);
}

/**
 * @return array{department_groups:array,inquiry_categories:array,type_permission_matrix:array}
 */
function load_permissions_runtime(): array
{
    if (isset($GLOBALS['finance_hr_permissions_runtime_cache']) && is_array($GLOBALS['finance_hr_permissions_runtime_cache'])) {
        return $GLOBALS['finance_hr_permissions_runtime_cache'];
    }

    $defaults = permissions_config_defaults();
    try {
        require_once __DIR__ . '/db.php';
        permissions_config_ensure_table();
        $stored = permissions_config_read_from_db();
        if (is_array($stored)) {
            $GLOBALS['finance_hr_permissions_runtime_cache'] = permissions_config_normalize($stored, $defaults);

            return $GLOBALS['finance_hr_permissions_runtime_cache'];
        }
    } catch (Throwable) {
        // DB 未接続時は app.php 既定値
    }

    $GLOBALS['finance_hr_permissions_runtime_cache'] = permissions_config_normalize($defaults, $defaults);

    return $GLOBALS['finance_hr_permissions_runtime_cache'];
}

function permissions_config_reset_runtime_cache(): void
{
    unset($GLOBALS['finance_hr_permissions_runtime_cache']);
}

function permissions_config_reset_cache(): void
{
    permissions_config_reset_runtime_cache();
    app_config_reset_cache();
}

function permissions_config_ensure_table(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $done = true;

    $pdo = db();
    if (! permissions_config_table_exists($pdo)) {
        $pdo->exec(
            'CREATE TABLE app_settings (
              setting_key VARCHAR(64) NOT NULL PRIMARY KEY,
              setting_value JSON NOT NULL,
              updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
              updated_by VARCHAR(255) NOT NULL DEFAULT \'\'
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    }
}

function permissions_config_table_exists(PDO $pdo): bool
{
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?'
    );
    $stmt->execute(['app_settings']);

    return (int) $stmt->fetchColumn() > 0;
}

function permissions_config_read_from_db(): ?array
{
    $stmt = db()->prepare(
        'SELECT setting_value FROM app_settings WHERE setting_key = ? LIMIT 1'
    );
    $stmt->execute([PERMISSIONS_CONFIG_SETTING_KEY]);
    $row = $stmt->fetch();
    if (! $row) {
        return null;
    }

    $decoded = json_decode((string) ($row['setting_value'] ?? ''), true);

    return is_array($decoded) ? $decoded : null;
}

/**
 * @param array{department_groups?:mixed,inquiry_categories?:mixed,type_permission_matrix?:mixed} $input
 * @return array{department_groups:array,inquiry_categories:array,type_permission_matrix:array}
 */
function permissions_config_normalize(array $input, array $defaults): array
{
    $groups = permissions_config_normalize_groups($input['department_groups'] ?? [], $defaults['department_groups']);
    $categories = permissions_config_normalize_categories($input['inquiry_categories'] ?? [], $defaults['inquiry_categories']);
    $matrix = permissions_config_normalize_matrix(
        $input['type_permission_matrix'] ?? [],
        $categories,
        $groups,
        $defaults['type_permission_matrix'],
    );

    return [
        'department_groups' => $groups,
        'inquiry_categories' => $categories,
        'type_permission_matrix' => $matrix,
    ];
}

function permissions_config_normalize_groups(mixed $raw, array $defaults): array
{
    if (! is_array($raw) || $raw === []) {
        return $defaults;
    }

    $normalized = [];
    foreach ($raw as $key => $group) {
        if (! is_array($group)) {
            continue;
        }
        $id = trim((string) ($group['id'] ?? $key));
        if ($id === '') {
            continue;
        }
        $keywords = $group['department_keywords'] ?? [];
        if (! is_array($keywords)) {
            $keywords = [];
        }
        $keywords = array_values(array_filter(array_map(
            static fn ($v): string => trim((string) $v),
            $keywords,
        ), static fn (string $v): bool => $v !== ''));

        $matchMode = (string) ($group['match_mode'] ?? 'any');
        if (! in_array($matchMode, ['any', 'all'], true)) {
            $matchMode = 'any';
        }

        $normalized[$id] = [
            'id' => $id,
            'label' => trim((string) ($group['label'] ?? $id)) ?: $id,
            'department_keywords' => $keywords,
            'match_mode' => $matchMode,
        ];
    }

    return $normalized !== [] ? $normalized : $defaults;
}

function permissions_config_normalize_categories(mixed $raw, array $defaults): array
{
    if (! is_array($raw) || $raw === []) {
        return $defaults;
    }

    $normalized = [];
    foreach ($raw as $key => $cat) {
        if (! is_array($cat)) {
            continue;
        }
        $catKey = trim((string) ($cat['key'] ?? $key));
        if ($catKey === '') {
            continue;
        }
        $types = $cat['types'] ?? [];
        if (! is_array($types)) {
            $types = [];
        }
        $types = array_values(array_unique(array_filter(array_map(
            static fn ($v): string => trim((string) $v),
            $types,
        ), static fn (string $v): bool => $v !== '')));

        $normalized[$catKey] = [
            'key' => $catKey,
            'sheet_key' => trim((string) ($cat['sheet_key'] ?? $catKey)) ?: $catKey,
            'label' => trim((string) ($cat['label'] ?? $catKey)) ?: $catKey,
            'types' => $types,
        ];
    }

    return $normalized !== [] ? $normalized : $defaults;
}

function permissions_config_normalize_matrix(
    mixed $raw,
    array $categories,
    array $groups,
    array $defaults,
): array {
    $groupIds = array_keys($groups);
    $allTypes = [];
    foreach ($categories as $cat) {
        foreach ($cat['types'] ?? [] as $type) {
            $allTypes[] = (string) $type;
        }
    }

    $matrix = [];
    $source = is_array($raw) && $raw !== [] ? $raw : $defaults;

    foreach ($allTypes as $type) {
        $row = is_array($source[$type] ?? null) ? $source[$type] : [];
        $normalizedRow = [];
        foreach ($groupIds as $groupId) {
            $access = (string) ($row[$groupId] ?? 'none');
            if (! in_array($access, ['edit', 'view', 'none'], true)) {
                $access = 'none';
            }
            if ($access !== 'none') {
                $normalizedRow[$groupId] = $access;
            }
        }
        $matrix[$type] = $normalizedRow;
    }

    return $matrix;
}

/**
 * @param array{department_groups:array,inquiry_categories:array,type_permission_matrix:array} $config
 */
function save_permissions_runtime(array $config, array $user): array
{
    if (! finance_hr_can_manage_permissions($user)) {
        return ['ok' => false, 'message' => '権限設定の編集権限がありません。'];
    }

    $defaults = permissions_config_defaults();
    $normalized = permissions_config_normalize($config, $defaults);

    permissions_config_ensure_table();
    $json = json_encode($normalized, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    $email = (string) ($user['email'] ?? '');

    $stmt = db()->prepare(
        'INSERT INTO app_settings (setting_key, setting_value, updated_by)
         VALUES (?, ?, ?)
         ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_by = VALUES(updated_by)'
    );
    $stmt->execute([PERMISSIONS_CONFIG_SETTING_KEY, $json, $email]);

    app_config_reset_cache();

    return ['ok' => true, 'config' => load_permissions_runtime()];
}

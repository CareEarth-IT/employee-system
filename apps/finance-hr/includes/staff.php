<?php
declare(strict_types=1);

function app_config(): array
{
    static $cfg = null;
    if ($cfg === null) {
        $cfg = require __DIR__ . '/../config/app.php';
    }
    return $cfg;
}

function normalize_email(string $s): string
{
    return strtolower(trim($s));
}

function normalize_name(string $s): string
{
    $s = str_replace("\u{3000}", ' ', $s);
    $s = preg_replace('/\s+/u', ' ', $s) ?? $s;
    return trim($s);
}

/**
 * @return array<string, array{key:string,sheet_key:string,label:string,types:list<string>}>
 */
function inquiry_categories(): array
{
    $cats = app_config()['inquiry_categories'] ?? [];
    return is_array($cats) ? $cats : [];
}

function category_for_key(string $key): ?array
{
    $cats = inquiry_categories();
    return isset($cats[$key]) && is_array($cats[$key]) ? $cats[$key] : null;
}

function sheet_key_for_category(string $categoryKey): string
{
    $cat = category_for_key($categoryKey);
    if ($cat === null) {
        return 'main';
    }
    return (string) ($cat['sheet_key'] ?? 'main');
}

function category_key_for_sheet(string $sheetKey): string
{
    $sheetKey = $sheetKey !== '' ? $sheetKey : 'main';
    foreach (inquiry_categories() as $key => $cat) {
        if (($cat['sheet_key'] ?? '') === $sheetKey) {
            return (string) $key;
        }
    }
    return $sheetKey === 'hr' ? 'hr' : ($sheetKey === 'is' ? 'is' : 'finance');
}

function category_label_for_sheet(string $sheetKey): string
{
    $key = category_key_for_sheet($sheetKey);
    $cat = category_for_key($key);
    return (string) ($cat['label'] ?? $key);
}

/** @return list<string> */
function types_for_category(string $categoryKey): array
{
    $cat = category_for_key($categoryKey);
    if ($cat === null) {
        return [];
    }
    $types = $cat['types'] ?? [];
    return is_array($types) ? array_values(array_map('strval', $types)) : [];
}

function category_key_for_type(string $type): string
{
    $type = trim($type);
    foreach (inquiry_categories() as $key => $cat) {
        $types = $cat['types'] ?? [];
        if (is_array($types) && in_array($type, $types, true)) {
            return (string) $key;
        }
    }
    return 'finance';
}

function is_type_in_category(string $type, string $categoryKey): bool
{
    return in_array(trim($type), types_for_category($categoryKey), true);
}

function is_hr_inquiry_type(string $type): bool
{
    return category_key_for_type($type) === 'hr';
}

function sheet_key_for_type(string $type): string
{
    return sheet_key_for_category(category_key_for_type($type));
}

function is_onboarding_doc_type(string $type): bool
{
    return trim($type) === (string) app_config()['onboarding_doc_type'];
}

/**
 * @return array<string, array{id:string,label:string,department_keywords:list<string>}>
 */
function department_groups(): array
{
    $groups = app_config()['department_groups'] ?? [];
    return is_array($groups) ? $groups : [];
}

/**
 * ユーザー所属文字列から部署グループ ID を解決する。
 *
 * @return list<string>
 */
function resolve_department_group_ids(array $user): array
{
    $dept = (string) ($user['department'] ?? '');
    if ($dept === '') {
        return [];
    }

    $matched = [];
    foreach (department_groups() as $id => $group) {
        $keywords = $group['department_keywords'] ?? [];
        if (! is_array($keywords)) {
            continue;
        }
        foreach ($keywords as $keyword) {
            $keyword = (string) $keyword;
            if ($keyword !== '' && str_contains($dept, $keyword)) {
                $matched[] = (string) ($group['id'] ?? $id);
                break;
            }
        }
    }

    // 課が取れず「経理部」のみの場合は経理・総務の両方を付与
    if ($matched === [] && str_contains($dept, '経理部')) {
        $matched = ['keiri', 'soumu'];
    }

    return array_values(array_unique($matched));
}

/**
 * @deprecated 部署グループ化後は resolve_department_group_ids() を利用
 */
function resolve_staff_id(array $user, string $group = 'department_groups'): string
{
    $ids = resolve_department_group_ids($user);
    if ($ids === []) {
        return '';
    }

    if ($group === 'hr_staff') {
        return in_array('hr', $ids, true) ? 'hr' : '';
    }
    if ($group === 'is_staff') {
        return in_array('is', $ids, true) ? 'is' : '';
    }
    if ($group === 'inquiry_staff') {
        foreach (['keiri', 'soumu'] as $id) {
            if (in_array($id, $ids, true)) {
                return $id;
            }
        }
        return '';
    }

    return $ids[0];
}

/** @return list<string> */
function finance_hr_designated_admin_emails(): array
{
    $emails = app_config()['admin_emails'] ?? [];
    if (! is_array($emails)) {
        return ['ginga_fukui@careearth.info'];
    }

    return array_values(array_filter(array_map(
        static fn ($v): string => normalize_email((string) $v),
        $emails,
    )));
}

function finance_hr_email_is_designated_admin(string $email): bool
{
    $email = normalize_email($email);

    return $email !== '' && in_array($email, finance_hr_designated_admin_emails(), true);
}

function finance_hr_is_cross_cutting_admin(array $user): bool
{
    if (finance_hr_email_is_designated_admin((string) ($user['email'] ?? ''))) {
        return true;
    }

    $dept = (string) ($user['department'] ?? '');

    return $dept !== '' && str_contains($dept, '役員');
}

/**
 * SSO で渡された所属文字列（例: 「経理部 / ○○課」）から担当者画面の可否を推定。
 */
function finance_hr_affiliation_allows_admin(array $user): bool
{
    $dept = (string) ($user['department'] ?? '');
    if ($dept === '') {
        return false;
    }

    if (str_contains($dept, '人事課')) {
        return true;
    }
    if (str_contains($dept, '経理部') || str_contains($dept, '経理課') || str_contains($dept, '総務課')) {
        return true;
    }
    if (str_contains($dept, '情報システム')) {
        return true;
    }
    if (str_contains($dept, '役員')) {
        return true;
    }

    return resolve_department_group_ids($user) !== [];
}

/**
 * 担当者画面の閲覧・操作可否（人事課・経理部・情シス・役員・指定メール）。
 */
function finance_hr_can_admin(?array $user = null): bool
{
    if (! empty($_SESSION['finance_hr_can_admin'])) {
        return true;
    }

    $user = $user ?? current_user();
    if (! $user) {
        return false;
    }

    if (($user['role'] ?? '') === 'admin') {
        return true;
    }

    if (finance_hr_email_is_designated_admin((string) ($user['email'] ?? ''))) {
        return true;
    }

    return finance_hr_affiliation_allows_admin($user);
}

function get_admin_session_for_user(array $user): array
{
    $groupIds = resolve_department_group_ids($user);
    $groups = department_groups();
    $labels = [];

    foreach ($groupIds as $id) {
        $labels[] = (string) ($groups[$id]['label'] ?? $id);
    }

    $canAdmin = finance_hr_can_admin($user);
    $staffLabel = implode(' / ', $labels);
    if ($canAdmin && $staffLabel === '') {
        $staffLabel = '管理者';
    }

    $primaryId = $groupIds[0] ?? '';
    $hrStaffId = in_array('hr', $groupIds, true) ? 'hr' : '';
    $isStaffId = in_array('is', $groupIds, true) ? 'is' : '';

    return [
        'email' => (string) ($user['email'] ?? ''),
        'fullName' => (string) ($user['full_name'] ?? ''),
        'staffId' => $primaryId,
        'departmentGroupIds' => $groupIds,
        'hrStaffId' => $hrStaffId,
        'isStaffId' => $isStaffId,
        'staffLabel' => $staffLabel,
        'isRegistered' => $canAdmin,
        'canAdmin' => $canAdmin,
        'isHrStaff' => $hrStaffId !== '',
        'isIsStaff' => $isStaffId !== '',
    ];
}

function inquiry_access_for_department_groups(string $type, array $groupIds): string
{
    if ($groupIds === []) {
        return 'none';
    }

    $matrix = app_config()['type_permission_matrix'][trim($type)] ?? null;
    if (! is_array($matrix)) {
        return 'none';
    }

    $best = 'none';
    foreach ($groupIds as $groupId) {
        $access = (string) ($matrix[$groupId] ?? 'none');
        if ($access === 'edit') {
            return 'edit';
        }
        if ($access === 'view') {
            $best = 'view';
        }
    }

    return $best;
}

/**
 * @deprecated 部署グループ化後は inquiry_access_for_department_groups() を利用
 */
function inquiry_access_for_staff(string $type, string $staffId): string
{
    if ($staffId === '') {
        return 'none';
    }

    return inquiry_access_for_department_groups($type, [$staffId]);
}

function resolve_inquiry_access(array $user, string $sheetKey, string $type): string
{
    if (! finance_hr_can_admin($user)) {
        return 'none';
    }

    // 役員・指定メールは全分類 edit
    if (finance_hr_is_cross_cutting_admin($user)) {
        return 'edit';
    }

    $groupIds = resolve_department_group_ids($user);
    if ($groupIds === []) {
        return 'none';
    }

    return inquiry_access_for_department_groups($type, $groupIds);
}

function assert_can_edit_inquiry(array $user, string $sheetKey, string $type): array
{
    $access = resolve_inquiry_access($user, $sheetKey, $type);
    if ($access === 'edit') {
        return ['ok' => true];
    }

    return [
        'ok' => false,
        'message' => '担当者画面の編集権限がありません。',
    ];
}

function admin_page_url(?int $rowNum = null, string $sheetKey = 'main'): string
{
    $base = rtrim((string) app_config()['web_app_url'], '/');
    $url = $base.'/admin.php';
    $params = [];
    if ($rowNum) {
        $params['row'] = (string) $rowNum;
    }
    if (in_array($sheetKey, ['hr', 'is'], true)) {
        $params['sheet'] = $sheetKey;
    }
    if ($params) {
        $url .= '?'.http_build_query($params);
    }

    return $url;
}

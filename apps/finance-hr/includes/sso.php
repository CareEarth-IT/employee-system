<?php
declare(strict_types=1);

/**
 * 社員ポータル（Laravel）からの SSO トークン検証・ユーザー同期
 */

function finance_hr_sso_secret(): string
{
    $explicit = getenv('FINANCE_HR_SSO_SECRET');
    if ($explicit !== false && $explicit !== '') {
        return $explicit;
    }

    $key = getenv('APP_KEY');
    if ($key === false || $key === '') {
        return '';
    }

    if (str_starts_with($key, 'base64:')) {
        $decoded = base64_decode(substr($key, 7), true);
        if ($decoded !== false && $decoded !== '') {
            return $decoded;
        }
    }

    return $key;
}

function finance_hr_base64url_decode(string $data): string|false
{
    $remainder = strlen($data) % 4;
    if ($remainder > 0) {
        $data .= str_repeat('=', 4 - $remainder);
    }

    return base64_decode(strtr($data, '-_', '+/'), true);
}

function finance_hr_sso_signature_valid(string $payloadB64, string $sig): bool
{
    $secrets = [];
    $primary = finance_hr_sso_secret();
    if ($primary !== '') {
        $secrets[] = $primary;
    }

    // 互換: APP_KEY をデコードせず使っていた場合にも通す
    $rawKey = getenv('APP_KEY');
    if (is_string($rawKey) && $rawKey !== '' && ! in_array($rawKey, $secrets, true)) {
        $secrets[] = $rawKey;
    }

    foreach ($secrets as $secret) {
        $expected = hash_hmac('sha256', $payloadB64, $secret);
        if (hash_equals($expected, $sig)) {
            return true;
        }
    }

    return false;
}

/**
 * @return array{email: string, name: string, employee_id: string, company: string, department: string, can_admin: bool}|null
 */
function finance_hr_verify_sso_token(string $token): ?array
{
    if ($token === '') {
        return null;
    }

    $parts = explode('.', $token, 2);
    if (count($parts) !== 2) {
        return null;
    }

    [$payloadB64, $sig] = $parts;
    if (! finance_hr_sso_signature_valid($payloadB64, $sig)) {
        return null;
    }

    $json = finance_hr_base64url_decode($payloadB64);
    if ($json === false) {
        return null;
    }

    $payload = json_decode($json, true);
    if (! is_array($payload)) {
        return null;
    }

    $exp = (int) ($payload['exp'] ?? 0);
    if ($exp < time()) {
        return null;
    }

    $email = normalize_email((string) ($payload['email'] ?? ''));
    if ($email === '' || ! is_allowed_email_domain($email)) {
        return null;
    }

    return [
        'email' => $email,
        'name' => trim((string) ($payload['name'] ?? '')),
        'employee_id' => trim((string) ($payload['employee_id'] ?? '')),
        'company' => trim((string) ($payload['company'] ?? '')),
        'department' => trim((string) ($payload['department'] ?? '')),
        'can_admin' => ! empty($payload['can_admin']),
    ];
}

/**
 * @param array{email: string, name: string, employee_id?: string, company: string, department: string, can_admin?: bool} $identity
 */
function finance_hr_upsert_sso_user(array $identity): array
{
    finance_hr_ensure_users_employee_id_column();

    $email = $identity['email'];
    $name = $identity['name'] !== '' ? $identity['name'] : $email;
    $employeeId = trim((string) ($identity['employee_id'] ?? ''));
    $company = $identity['company'] !== ''
        ? $identity['company']
        : (string) (app_config()['default_company'] ?? '');
    $department = $identity['department'];
    $canAdmin = ! empty($identity['can_admin'])
        || finance_hr_email_is_designated_admin($email)
        || finance_hr_affiliation_allows_admin(['department' => $department]);

    $stmt = db()->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    $existing = $stmt->fetch();

    $probe = [
        'email' => $email,
        'full_name' => $name,
        'department' => $department,
        'role' => $canAdmin ? 'admin' : 'user',
    ];
    $session = get_admin_session_for_user($probe);
    $staffLabel = (string) ($session['staffLabel'] ?? '');
    if ($canAdmin && $staffLabel === '') {
        $staffLabel = '管理者';
    }
    if (! $canAdmin) {
        $staffLabel = '';
    }
    $isHrStaff = ! empty($session['isHrStaff']) ? 1 : 0;
    $role = $canAdmin ? 'admin' : 'user';

    if ($existing) {
        $update = db()->prepare(
            'UPDATE users
             SET full_name = ?, employee_id = ?, company = ?, department = ?,
                 staff_label = ?, is_hr_staff = ?, role = ?
             WHERE id = ?'
        );
        $update->execute([
            $name,
            $employeeId,
            $company,
            $department,
            $staffLabel,
            $isHrStaff,
            $role,
            (int) $existing['id'],
        ]);

        $stmt->execute([$email]);
        $user = $stmt->fetch();

        return is_array($user) ? $user : $existing;
    }

    $passwordHash = password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT);
    $insert = db()->prepare(
        'INSERT INTO users (email, employee_id, password_hash, full_name, company, department, role, staff_label, is_hr_staff)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $insert->execute([
        $email,
        $employeeId,
        $passwordHash,
        $name,
        $company,
        $department,
        $role,
        $staffLabel,
        $isHrStaff,
    ]);

    $stmt->execute([$email]);
    $user = $stmt->fetch();
    if (! is_array($user)) {
        throw new RuntimeException('SSO user create failed');
    }

    return $user;
}

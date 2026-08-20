<?php

namespace App\Support;

use App\Models\User;

class DepartmentPortal
{
    public const EMPLOYEE_PORTAL_HEADER = 'X-Employee-Portal';

    public const EMPLOYEE_PORTAL_TAB_HEADER = 'X-Employee-Portal-Tab';

    public const EMPLOYEE_PORTAL_PROXY_SECRET_HEADER = 'X-Employee-Portal-Proxy-Secret';

    public const EMPLOYEE_PORTAL_USER_EMAIL_HEADER = 'X-Employee-Portal-User-Email';

    public const EMPLOYEE_PORTAL_USER_NAME_HEADER = 'X-Employee-Portal-User-Name';

    public const EMPLOYEE_PORTAL_USER_ID_HEADER = 'X-Employee-Portal-User-Id';

    /**
     * @return array<string, array<string, mixed>>
     */
    public static function all(): array
    {
        /** @var array<string, array<string, mixed>> $portals */
        $portals = config('department_portals', []);

        return $portals;
    }

    public static function find(string $tabKey): ?array
    {
        $portal = self::all()[$tabKey] ?? null;

        return is_array($portal) ? $portal : null;
    }

    public static function isConfigured(string $tabKey): bool
    {
        $portal = self::find($tabKey);

        return $portal !== null && trim((string) ($portal['internal_url'] ?? '')) !== '';
    }

    /**
     * @return list<string>
     */
    public static function configuredTabKeys(): array
    {
        return array_values(array_filter(
            array_keys(self::all()),
            static fn (string $tabKey) => self::isConfigured($tabKey),
        ));
    }

    public static function findByProxyPath(string $proxyPath): ?array
    {
        foreach (self::all() as $tabKey => $portal) {
            if (($portal['proxy_path'] ?? null) === $proxyPath) {
                return [
                    ...$portal,
                    'tab_key' => $tabKey,
                ];
            }
        }

        return null;
    }

    public static function canAccess(User $user, string $tabKey): bool
    {
        if ($tabKey === 'common') {
            return false;
        }

        if (! self::isConfigured($tabKey)) {
            return false;
        }

        $tab = DashboardTab::findByKey($tabKey);

        if ($tab === null) {
            return false;
        }

        return DashboardTab::userMatchesTab($user, $tab);
    }

    public static function entryUrl(string $tabKey): ?string
    {
        if (! self::isConfigured($tabKey)) {
            return null;
        }

        $portal = self::find($tabKey);
        $proxyPath = trim((string) ($portal['proxy_path'] ?? ''), '/');
        $entryPath = trim((string) ($portal['entry_path'] ?? ''), '/');

        if ($entryPath !== '') {
            return url($proxyPath.'/'.$entryPath);
        }

        return url($proxyPath);
    }

    public static function label(string $tabKey): string
    {
        return (string) (self::find($tabKey)['label'] ?? '社内サイト');
    }

    public static function shouldUseIdentityToken(string $tabKey): bool
    {
        $value = self::find($tabKey)['use_identity_token'] ?? null;

        if ($value !== null && $value !== '') {
            return filter_var($value, FILTER_VALIDATE_BOOL);
        }

        if (self::proxySecret($tabKey) !== null) {
            return false;
        }

        return app()->environment('production');
    }

    public static function proxySecret(string $tabKey): ?string
    {
        $portal = self::find($tabKey);
        if ($portal === null) {
            return null;
        }

        $secret = trim((string) ($portal['proxy_secret'] ?? ''));

        return $secret !== '' ? $secret : null;
    }
}

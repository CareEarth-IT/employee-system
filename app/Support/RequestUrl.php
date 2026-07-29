<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;

/**
 * アクセス元（リクエスト / Referer / セッション）に合わせた URL 生成。
 * Cloud Run URL・カスタムドメイン・employee.local など、開いた URL ごとにリンクを揃える。
 */
class RequestUrl
{
    public const SESSION_KEY = 'app.url_root';

    public static function root(?Request $request = null): string
    {
        $request ??= request();

        if ($request === null || app()->runningInConsole()) {
            return self::configuredRoot();
        }

        if ($request->hasSession()) {
            $stored = $request->session()->get(self::SESSION_KEY);
            if (is_string($stored) && $stored !== '') {
                return rtrim($stored, '/');
            }
        }

        $detected = self::detectRoot($request);
        if ($detected !== null) {
            return $detected;
        }

        return self::configuredRoot();
    }

    public static function remember(?Request $request = null): void
    {
        $request ??= request();

        if ($request === null || ! $request->hasSession()) {
            return;
        }

        $detected = self::detectRoot($request);
        if ($detected !== null) {
            $request->session()->put(self::SESSION_KEY, $detected);
        }
    }

    public static function applyRoot(?Request $request = null): void
    {
        URL::forceRootUrl(self::root($request));
    }

    /**
     * @param  mixed  $parameters
     */
    public static function route(string $name, $parameters = [], bool $absolute = true): string
    {
        self::applyRoot();

        if (! $absolute && ! app()->runningInConsole()) {
            return route($name, $parameters, false);
        }

        return route($name, $parameters);
    }

    public static function captureRoot(): string
    {
        return self::root();
    }

    public static function withRoot(string $root, callable $callback): mixed
    {
        $previous = URL::formatRoot(URL::formatScheme(), false);
        URL::forceRootUrl(rtrim($root, '/'));

        try {
            return $callback();
        } finally {
            URL::forceRootUrl($previous);
        }
    }

    private static function detectRoot(Request $request): ?string
    {
        $fromRequest = self::rootFromRequest($request);
        if ($fromRequest !== '') {
            return rtrim($fromRequest, '/');
        }

        return self::rootFromReferer($request);
    }

    private static function rootFromRequest(Request $request): string
    {
        return $request->getSchemeAndHttpHost().$request->getBaseUrl();
    }

    private static function rootFromReferer(Request $request): ?string
    {
        $referer = $request->headers->get('referer');
        if (! is_string($referer) || $referer === '') {
            return null;
        }

        $parsed = parse_url($referer);
        if (! is_array($parsed) || ! isset($parsed['scheme'], $parsed['host'])) {
            return null;
        }

        $port = isset($parsed['port']) ? ':'.$parsed['port'] : '';
        $root = $parsed['scheme'].'://'.strtolower($parsed['host']).$port.self::inferBasePathFromReferer($parsed, $request);

        if (! self::isAllowedRoot($root)) {
            return null;
        }

        return rtrim($root, '/');
    }

    /**
     * @param  array<string, mixed>  $parsed
     */
    private static function inferBasePathFromReferer(array $parsed, Request $request): string
    {
        $path = $parsed['path'] ?? '/';
        $refererHost = strtolower((string) $parsed['host']);

        $configured = parse_url((string) config('app.url'));
        if (is_array($configured) && isset($configured['host'], $configured['path'])) {
            $configuredHost = strtolower((string) $configured['host']);
            $configuredPath = rtrim((string) $configured['path'], '/');

            if ($configuredPath !== ''
                && $configuredHost === $refererHost
                && str_starts_with($path, $configuredPath.'/')) {
                return $configuredPath;
            }
        }

        $requestBase = $request->getBaseUrl();
        if ($requestBase !== '' && strtolower($request->getHost()) === $refererHost) {
            return $requestBase;
        }

        return '';
    }

    private static function isAllowedRoot(string $root): bool
    {
        $host = parse_url($root, PHP_URL_HOST);

        return is_string($host) && $host !== '' && self::isAllowedHost($host);
    }

    private static function isAllowedHost(string $host): bool
    {
        $host = strtolower($host);

        foreach (self::allowedHosts() as $allowed) {
            $allowed = strtolower($allowed);

            if ($allowed === $host) {
                return true;
            }

            if (str_starts_with($allowed, '*.')) {
                $suffix = substr($allowed, 1);
                if (str_ends_with($host, $suffix)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @return list<string>
     */
    private static function allowedHosts(): array
    {
        $hosts = [];

        $configuredHost = parse_url((string) config('app.url'), PHP_URL_HOST);
        if (is_string($configuredHost) && $configuredHost !== '') {
            $hosts[] = $configuredHost;
        }

        $extra = config('app.allowed_url_hosts');
        if (is_string($extra) && $extra !== '') {
            $hosts = array_merge($hosts, array_map('trim', explode(',', $extra)));
        }

        $hosts = array_merge($hosts, [
            'localhost',
            '127.0.0.1',
            'employee.local',
            'employee.careearth.net',
            '*.run.app',
        ]);

        return array_values(array_unique(array_filter($hosts)));
    }

    private static function configuredRoot(): string
    {
        return rtrim((string) config('app.url'), '/');
    }
}

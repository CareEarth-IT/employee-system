<?php

namespace App\Services\DepartmentPortalProxy;

use App\Models\User;
use App\Services\RealEstatePortalSsoHandoff;
use App\Support\DepartmentPortal;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response as ClientResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Response;

/**
 * 不動産ポータル専用: SSO handoff、logout、セッション Cookie 処理。
 *
 * ブラウザ Cookie ではなく社員サイトのセッションへ不動産ポータル Cookie を保持する。
 * プロキシ path 不一致による Cookie ループを防ぐ。
 */
class RealEstatePortalProxyHandler
{
    public const PORTAL_SESSION_COOKIE_KEY = 'department_portal.real-estate.cookies';

    private const PORTAL_COOKIE_NAMES = ['real_estate_portal_session', 'XSRF-TOKEN'];

    public function __construct(
        private RealEstatePortalSsoHandoff $realEstateSsoHandoff,
        private DepartmentPortalUpstreamClient $upstreamClient,
        private DepartmentPortalResponseRewriter $responseRewriter,
    ) {}

    public function shouldInlineSso(Request $request, string $tabKey, string $targetPath, string $portalPath): bool
    {
        if ($tabKey !== 'real-estate' || ! $request->isMethod('GET')) {
            return false;
        }

        if ($this->hasPortalSessionCookie($request, $portalPath)) {
            return false;
        }

        $normalized = strtolower(trim($targetPath, '/'));

        return ! str_starts_with($normalized, 'auth/portal/callback');
    }

    public function shouldRetrySso(Request $request, string $tabKey, string $targetPath): bool
    {
        if ($tabKey !== 'real-estate' || ! $request->isMethod('GET')) {
            return false;
        }

        $normalized = strtolower(trim($targetPath, '/'));

        return ! str_starts_with($normalized, 'auth/portal/callback');
    }

    public function isLogoutPath(string $targetPath): bool
    {
        return strtolower(trim($targetPath, '/')) === 'logout';
    }

    public function shouldRefreshPortalSession(
        Request $request,
        ClientResponse $upstream,
        string $portalPath,
        string $internalBase,
    ): bool {
        if (! $request->isMethod('GET')) {
            return false;
        }

        if ($upstream->status() === 401) {
            if (strtoupper((string) ($upstream->header('X-Portal-Session-Required') ?? '')) === '1') {
                return true;
            }

            return str_contains(strtolower($upstream->body()), 'portal session required');
        }

        return $this->isStalePortalSessionRedirect($request, $upstream, $portalPath, $internalBase);
    }

    public function refreshPortalSessionAndProxy(
        Request $request,
        User $user,
        string $targetUrl,
        string $internalBase,
        string $portalPath,
    ): Response {
        $this->clearPortalSessionCookies($request);

        return $this->proxyWithEstablishedSession($request, $user, $targetUrl, $internalBase, $portalPath);
    }

    public function proxyWithEstablishedSession(
        Request $request,
        User $user,
        string $targetUrl,
        string $internalBase,
        string $portalPath,
    ): Response {
        try {
            $result = $this->realEstateSsoHandoff->authenticateAndFetch($user, $targetUrl);
        } catch (RuntimeException $e) {
            abort(502, DepartmentPortal::label('real-estate').'へのログインに失敗しました。'.$e->getMessage());
        }

        $upstream = $result['upstream'];

        if ($upstream->status() === 404) {
            Log::warning('Real estate portal still returned 404 after SSO', [
                'target' => $targetUrl,
            ]);
            abort(502, DepartmentPortal::label('real-estate').'へのログイン後もページを表示できませんでした。');
        }

        if ($upstream->status() === 403) {
            abort(503, DepartmentPortal::label('real-estate').'へ接続できません。不動産側 Cloud Run の --no-invoker-iam-check と EMPLOYEE_PORTAL_PROXY_SECRET の設定を確認してください（deploy\\setup-realestate-proxy.cmd）。');
        }

        return $this->buildSsoProxiedResponse($request, $upstream, $internalBase, $portalPath, $result['set_cookies']);
    }

    public function finishLogout(
        Request $request,
        string $targetUrl,
        string $internalBase,
        string $portalPath,
        string $tabKey,
    ): Response {
        if ($request->isMethod('POST')) {
            try {
                $this->upstreamClient->send($request, $tabKey, $targetUrl, $internalBase, $portalPath);
            } catch (RuntimeException|ConnectionException $e) {
                Log::warning('Real estate portal logout upstream failed', [
                    'target' => $targetUrl,
                    'message' => $e->getMessage(),
                ]);
            }
        }

        $this->clearPortalSessionCookies($request);

        $response = redirect()->route('dashboard', ['tab' => 'real-estate']);

        return $this->expireBrowserPortalSessionCookies($response, $portalPath);
    }

    public function portalSessionCookieHeader(Request $request): ?string
    {
        $pairs = [];
        foreach (self::PORTAL_COOKIE_NAMES as $name) {
            $value = $this->portalSessionValue($request, $name);
            if ($value !== null) {
                $pairs[] = $name.'='.$value;
            }
        }

        return $pairs === [] ? null : implode('; ', $pairs);
    }

    public function hasPortalSessionCookie(Request $request, string $portalPath): bool
    {
        if (trim($portalPath, '/') === 'realestate-portal') {
            return $this->portalSessionValue($request, 'real_estate_portal_session') !== null;
        }

        $cookieHeader = $request->headers->get('Cookie');
        if (! is_string($cookieHeader) || $cookieHeader === '') {
            return false;
        }

        foreach (array_filter(array_map('trim', explode(';', $cookieHeader))) as $pair) {
            if (! str_contains($pair, '=')) {
                continue;
            }

            [$name] = explode('=', $pair, 2);
            if ($name === 'real_estate_portal_session') {
                return true;
            }
        }

        return false;
    }

    public function finalizeProxiedResponse(Request $request, ClientResponse $upstream, Response $response, string $portalPath): Response
    {
        $this->capturePortalSessionCookiesFromUpstream($request, $upstream);
        $this->stripPortalSessionCookieHeaders($response);

        return $this->expireBrowserPortalSessionCookies($response, $portalPath);
    }

    /**
     * @param  list<string>  $setCookieHeaders
     */
    public function storePortalSessionCookies(Request $request, array $setCookieHeaders): void
    {
        $parsed = $this->parseCookieHeaderPairs($setCookieHeaders);
        if ($parsed === []) {
            return;
        }

        $existing = $request->session()->get(self::PORTAL_SESSION_COOKIE_KEY, []);
        if (! is_array($existing)) {
            $existing = [];
        }

        $request->session()->put(self::PORTAL_SESSION_COOKIE_KEY, [
            ...$existing,
            ...$parsed,
        ]);
        $request->session()->save();
    }

    public function clearPortalSessionCookies(Request $request): void
    {
        $request->session()->forget(self::PORTAL_SESSION_COOKIE_KEY);
    }

    public function capturePortalSessionCookiesFromUpstream(Request $request, ClientResponse $upstream): void
    {
        $headers = $upstream->headers();
        $rawCookies = $headers['Set-Cookie'] ?? $headers['set-cookie'] ?? [];

        if (! is_array($rawCookies)) {
            $rawCookies = [$rawCookies];
        }

        $parsed = [];
        foreach ($rawCookies as $cookieHeader) {
            if (! is_string($cookieHeader) || ! str_contains($cookieHeader, '=')) {
                continue;
            }

            [$name] = explode('=', $cookieHeader, 2);
            if (! in_array($name, self::PORTAL_COOKIE_NAMES, true)) {
                continue;
            }

            $value = trim(explode(';', explode('=', $cookieHeader, 2)[1], 2)[0]);
            if ($value !== '') {
                $parsed[$name] = $value;
            }
        }

        if ($parsed === []) {
            return;
        }

        $existing = $request->session()->get(self::PORTAL_SESSION_COOKIE_KEY, []);
        if (! is_array($existing)) {
            $existing = [];
        }

        $request->session()->put(self::PORTAL_SESSION_COOKIE_KEY, [
            ...$existing,
            ...$parsed,
        ]);
        $request->session()->save();
    }

    private function portalSessionValue(Request $request, string $name): ?string
    {
        $cookies = $request->session()->get(self::PORTAL_SESSION_COOKIE_KEY);
        if (! is_array($cookies)) {
            return null;
        }

        $value = $cookies[$name] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }

    private function isStalePortalSessionRedirect(
        Request $request,
        ClientResponse $upstream,
        string $portalPath,
        string $internalBase,
    ): bool {
        if (! in_array($upstream->status(), [301, 302, 303, 307, 308], true)) {
            return false;
        }

        $location = $upstream->header('Location');
        if (! is_string($location) || $location === '') {
            return false;
        }

        $proxyBase = rtrim(url($portalPath), '/');
        $rewritten = $this->responseRewriter->rewriteUrl($location, $internalBase, $proxyBase);
        $current = rtrim($request->fullUrl(), '/');
        $target = rtrim($rewritten, '/');

        if ($target === $current) {
            return true;
        }

        $homeUrl = rtrim($proxyBase, '/').'/home';

        return $target === rtrim($homeUrl, '/');
    }

    /**
     * @param  list<string>  $setCookieHeaders
     */
    private function buildSsoProxiedResponse(
        Request $request,
        ClientResponse $upstream,
        string $internalBase,
        string $portalPath,
        array $setCookieHeaders,
    ): Response {
        $this->storePortalSessionCookies($request, $setCookieHeaders);
        $request->session()->save();

        $response = $this->responseRewriter->toProxiedResponse($upstream, $request, $internalBase, $portalPath);
        $this->stripPortalSessionCookieHeaders($response);

        return $this->expireBrowserPortalSessionCookies($response, $portalPath);
    }

    /**
     * @param  list<string>  $setCookieHeaders
     * @return array<string, string>
     */
    private function parseCookieHeaderPairs(array $setCookieHeaders): array
    {
        $parsed = [];

        foreach ($setCookieHeaders as $cookieHeader) {
            if (! is_string($cookieHeader) || ! str_contains($cookieHeader, '=')) {
                continue;
            }

            [$name] = explode('=', $cookieHeader, 2);
            if (! in_array($name, self::PORTAL_COOKIE_NAMES, true)) {
                continue;
            }

            $value = trim(explode(';', explode('=', $cookieHeader, 2)[1], 2)[0]);
            if ($value !== '') {
                $parsed[$name] = $value;
            }
        }

        return $parsed;
    }

    private function stripPortalSessionCookieHeaders(Response $response): void
    {
        foreach ($response->headers->getCookies() as $cookie) {
            if (! in_array($cookie->getName(), self::PORTAL_COOKIE_NAMES, true)) {
                continue;
            }

            $response->headers->removeCookie(
                $cookie->getName(),
                $cookie->getPath(),
                $cookie->getDomain(),
            );
        }
    }

    private function expireBrowserPortalSessionCookies(Response $response, string $portalPath): Response
    {
        $path = '/'.trim($portalPath, '/');
        $secure = request()->isSecure();

        foreach (self::PORTAL_COOKIE_NAMES as $name) {
            $response->headers->setCookie(Cookie::create(
                name: $name,
                value: '',
                expire: 1,
                path: $path,
                secure: $secure,
                httpOnly: $name !== 'XSRF-TOKEN',
                sameSite: Cookie::SAMESITE_LAX,
            ));
        }

        return $response;
    }
}

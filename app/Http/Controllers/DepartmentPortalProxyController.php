<?php

namespace App\Http\Controllers;

use App\Services\DepartmentPortalIdentityToken;
use App\Services\RealEstatePortalSsoHandoff;
use App\Support\DepartmentPortal;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response as ClientResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Response;

class DepartmentPortalProxyController extends Controller
{
    public function __construct(
        private DepartmentPortalIdentityToken $identityToken,
        private RealEstatePortalSsoHandoff $realEstateSsoHandoff,
    ) {}

    public function __invoke(Request $request, ?string $path = null): Response
    {
        $portalPath = (string) ($request->route('portal') ?? '');
        $portalConfig = DepartmentPortal::findByProxyPath($portalPath);

        if ($portalConfig === null) {
            abort(404);
        }

        $tabKey = (string) $portalConfig['tab_key'];
        $user = $request->user();

        if (! $user || ! DepartmentPortal::canAccess($user, $tabKey)) {
            abort(403, DepartmentPortal::label($tabKey).'を利用する権限がありません。');
        }

        $internalBase = rtrim((string) $portalConfig['internal_url'], '/');
        if ($internalBase === '') {
            abort(503, DepartmentPortal::label($tabKey).'の接続先が設定されていません。');
        }

        $targetPath = $this->resolveUpstreamPath($path, $portalPath);
        $targetUrl = $internalBase.$targetPath;
        if ($request->getQueryString()) {
            $targetUrl .= '?'.$request->getQueryString();
        }

        if ($tabKey === 'real-estate' && $this->isPortalLogoutPath($targetPath)) {
            return $this->finishRealEstatePortalLogout(
                $request,
                $targetUrl,
                $internalBase,
                $portalPath,
                $tabKey,
            );
        }

        if ($this->shouldInlineRealEstateSso($request, $tabKey, $targetPath, $portalPath)) {
            return $this->proxyRealEstateWithEstablishedSession(
                $request,
                $user,
                $targetUrl,
                $internalBase,
                $portalPath,
            );
        }

        try {
            $upstream = $this->sendRequest($request, $tabKey, $targetUrl, $internalBase, $portalPath);
        } catch (RuntimeException $e) {
            Log::error('Department portal identity token failed', [
                'tab' => $tabKey,
                'target' => $targetUrl,
                'message' => $e->getMessage(),
            ]);
            abort(502, DepartmentPortal::label($tabKey).'への認証に失敗しました。管理者にお問い合わせください。');
        } catch (ConnectionException $e) {
            Log::error('Department portal upstream unreachable', [
                'tab' => $tabKey,
                'target' => $targetUrl,
                'message' => $e->getMessage(),
            ]);
            abort(502, DepartmentPortal::label($tabKey).'に接続できません。接続先 URL の設定を確認してください。');
        }

        if ($upstream->status() === 403) {
            Log::warning('Department portal upstream denied access', [
                'tab' => $tabKey,
                'target' => $targetUrl,
            ]);
            $message = DepartmentPortal::proxySecret($tabKey) !== null
                ? DepartmentPortal::label($tabKey).'へ接続できません。不動産側 Cloud Run の --no-invoker-iam-check と EMPLOYEE_PORTAL_PROXY_SECRET の設定を確認してください（deploy\\setup-realestate-proxy.cmd）。'
                : DepartmentPortal::label($tabKey).'へのアクセス権がありません。GCP 管理者に real-estate への run.invoker 付与（deploy\\grant-realestate-invoker.cmd）を依頼してください。';
            abort(503, $message);
        }

        if (
            $tabKey === 'real-estate'
            && $request->isMethod('GET')
            && $upstream->status() === 404
            && $this->shouldRetryRealEstateSso($request, $tabKey, $targetPath)
        ) {
            return $this->proxyRealEstateWithEstablishedSession(
                $request,
                $user,
                $targetUrl,
                $internalBase,
                $portalPath,
            );
        }

        return $this->toProxiedResponse($upstream, $request, $internalBase, $portalPath);
    }

    private function sendRequest(Request $request, string $tabKey, string $targetUrl, string $audience, string $portalPath): ClientResponse
    {
        $client = Http::timeout(60)
            ->withoutRedirecting()
            ->withHeaders($this->forwardHeaders($request, $tabKey, $portalPath));

        $token = $this->identityToken->token($tabKey, $audience);
        if ($token) {
            $client = $client->withToken($token);
        }

        return match ($request->method()) {
            'POST' => $client->asForm()->post($targetUrl, $request->all()),
            'PUT' => $client->asForm()->put($targetUrl, $request->all()),
            'PATCH' => $client->asForm()->patch($targetUrl, $request->all()),
            'DELETE' => $client->delete($targetUrl, $request->all()),
            default => $client->get($targetUrl),
        };
    }

    /**
     * @return array<string, string>
     */
    private function forwardHeaders(Request $request, string $tabKey, string $portalPath): array
    {
        $headers = [
            DepartmentPortal::EMPLOYEE_PORTAL_HEADER => '1',
            DepartmentPortal::EMPLOYEE_PORTAL_TAB_HEADER => $tabKey,
        ];

        $proxySecret = DepartmentPortal::proxySecret($tabKey);
        if ($proxySecret !== null) {
            $headers[DepartmentPortal::EMPLOYEE_PORTAL_PROXY_SECRET_HEADER] = $proxySecret;
        }

        $user = $request->user();
        if ($user !== null) {
            $headers[DepartmentPortal::EMPLOYEE_PORTAL_USER_EMAIL_HEADER] = (string) $user->email;

            $formalName = trim(implode(' ', array_filter([
                trim((string) ($user->last_name ?? '')),
                trim((string) ($user->first_name ?? '')),
            ], static fn (string $part): bool => $part !== '' && $part !== '未設定')));

            if ($formalName === '') {
                $formalName = trim((string) ($user->name ?? ''));
            }

            if ($formalName !== '') {
                $headers[DepartmentPortal::EMPLOYEE_PORTAL_USER_NAME_HEADER] = $formalName;
            }

            $employeeId = trim((string) ($user->employee_id ?? ''));
            if ($employeeId !== '') {
                $headers[DepartmentPortal::EMPLOYEE_PORTAL_USER_ID_HEADER] = $employeeId;
            }
        }

        foreach (['Accept', 'Accept-Language', 'Content-Type', 'Referer', 'X-CSRF-TOKEN', 'X-XSRF-TOKEN'] as $name) {
            $value = $request->headers->get($name);
            if (is_string($value) && $value !== '') {
                $headers[$name] = $value;
            }
        }

        $cookie = $this->forwardCookieHeader($request->headers->get('Cookie'), $portalPath);
        if ($cookie !== null) {
            $headers['Cookie'] = $cookie;
        }

        if (! isset($headers['Referer'])) {
            $headers['Referer'] = $request->fullUrl();
        }

        return $headers;
    }

    private function toProxiedResponse(
        ClientResponse $upstream,
        Request $request,
        string $internalBase,
        string $proxyPath,
    ): Response {
        $proxyBase = rtrim(url($proxyPath), '/');
        $body = $upstream->body();
        $contentType = $upstream->header('Content-Type') ?? '';

        if (str_contains(strtolower($contentType), 'text/html') || str_contains(strtolower($contentType), 'text/css')) {
            $body = $this->rewriteBody($body, $internalBase, $proxyBase);
        }

        $response = response($body, $upstream->status());

        foreach ($upstream->headers() as $name => $values) {
            $lowerName = strtolower($name);

            if (in_array($lowerName, ['transfer-encoding', 'connection', 'content-encoding', 'content-length'], true)) {
                continue;
            }

            if ($lowerName === 'location') {
                $values = array_map(
                    fn (string $value) => $this->rewriteUrl($value, $internalBase, $proxyBase),
                    $values,
                );
            }

            if ($lowerName === 'set-cookie') {
                $values = array_map(
                    fn (string $value) => $this->rewriteCookiePath($value, $proxyPath),
                    $values,
                );
            }

            $response->headers->set($name, $values, false);
        }

        if (! $response->headers->has('Content-Type') && $contentType !== '') {
            $response->headers->set('Content-Type', $contentType);
        }

        return $response;
    }

    private function proxyRealEstateWithEstablishedSession(
        Request $request,
        \App\Models\User $user,
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

        $response = $this->toProxiedResponse($upstream, $request, $internalBase, $portalPath);

        return $this->attachPortalSetCookies($response, $result['set_cookies']);
    }

    private function isPortalLogoutPath(string $targetPath): bool
    {
        return strtolower(trim($targetPath, '/')) === 'logout';
    }

    private function finishRealEstatePortalLogout(
        Request $request,
        string $targetUrl,
        string $internalBase,
        string $portalPath,
        string $tabKey,
    ): Response {
        if ($request->isMethod('POST')) {
            try {
                $this->sendRequest($request, $tabKey, $targetUrl, $internalBase, $portalPath);
            } catch (RuntimeException|ConnectionException $e) {
                Log::warning('Real estate portal logout upstream failed', [
                    'target' => $targetUrl,
                    'message' => $e->getMessage(),
                ]);
            }
        }

        $response = redirect()->route('dashboard', ['tab' => 'real-estate']);

        return $this->expirePortalSessionCookies($response, $portalPath);
    }

    private function expirePortalSessionCookies(Response $response, string $portalPath): Response
    {
        $path = '/'.trim($portalPath, '/');
        $secure = request()->isSecure();

        foreach (['real_estate_portal_session', 'XSRF-TOKEN'] as $name) {
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

    private function attachPortalSetCookies(Response $response, array $setCookieHeaders): Response
    {
        foreach ($setCookieHeaders as $cookieHeader) {
            $response->headers->setCookie(Cookie::fromString($cookieHeader));
        }

        return $response;
    }

    private function shouldInlineRealEstateSso(
        Request $request,
        string $tabKey,
        string $targetPath,
        string $portalPath,
    ): bool {
        if ($tabKey !== 'real-estate' || ! $request->isMethod('GET')) {
            return false;
        }

        if ($this->hasPortalSessionCookie($request, $portalPath)) {
            return false;
        }

        $normalized = strtolower(trim($targetPath, '/'));

        return ! str_starts_with($normalized, 'auth/portal/callback');
    }

    private function shouldRetryRealEstateSso(
        Request $request,
        string $tabKey,
        string $targetPath,
    ): bool {
        if ($tabKey !== 'real-estate' || ! $request->isMethod('GET')) {
            return false;
        }

        $normalized = strtolower(trim($targetPath, '/'));

        return ! str_starts_with($normalized, 'auth/portal/callback');
    }

    private function hasPortalSessionCookie(Request $request, string $portalPath): bool
    {
        $cookieHeader = $request->headers->get('Cookie');
        if (! is_string($cookieHeader) || $cookieHeader === '') {
            return false;
        }

        $allowedNames = match (trim($portalPath, '/')) {
            'realestate-portal' => ['real_estate_portal_session'],
            default => [],
        };

        if ($allowedNames === []) {
            return false;
        }

        foreach (array_filter(array_map('trim', explode(';', $cookieHeader))) as $pair) {
            if (! str_contains($pair, '=')) {
                continue;
            }

            [$name] = explode('=', $pair, 2);
            if (in_array($name, $allowedNames, true)) {
                return true;
            }
        }

        return false;
    }

    private function resolveUpstreamPath(?string $path, string $proxyPath): string
    {
        $normalized = trim((string) $path, '/');
        $proxyPath = trim($proxyPath, '/');

        if ($normalized === '' || $normalized === $proxyPath) {
            return '/';
        }

        if ($proxyPath !== '' && str_starts_with($normalized, $proxyPath.'/')) {
            $normalized = substr($normalized, strlen($proxyPath) + 1);
        }

        return '/'.ltrim($normalized, '/');
    }

    private function rewriteBody(string $body, string $internalBase, string $proxyBase): string
    {
        $replacements = [
            rtrim($internalBase, '/') => $proxyBase,
            'https://realestate.careearth.net' => $proxyBase,
            'http://realestate.careearth.net' => $proxyBase,
        ];

        $body = str_replace(array_keys($replacements), array_values($replacements), $body);

        return $this->rewriteRootRelativeUrls($body, $proxyBase);
    }

    /**
     * upstream が /admin/... などルート相対 URL を返す場合、プロキシ prefix を付与する。
     */
    private function rewriteRootRelativeUrls(string $body, string $proxyBase): string
    {
        $proxyPath = '/'.trim((string) parse_url($proxyBase, PHP_URL_PATH), '/');
        if ($proxyPath === '/' || $proxyPath === '') {
            return $body;
        }

        $escapedProxySegment = preg_quote(ltrim($proxyPath, '/'), '/');

        $rewritten = preg_replace_callback(
            '/\b(href|action|src)\s*=\s*(["\'])\/(?!'.$escapedProxySegment.'\/)([^"\']*)/i',
            static fn (array $matches): string => $matches[1].'='.$matches[2].$proxyPath.'/'.$matches[3],
            $body,
        );

        return is_string($rewritten) ? $rewritten : $body;
    }

    private function rewriteUrl(string $url, string $internalBase, string $proxyBase): string
    {
        if (str_starts_with($url, '/')) {
            $proxyPath = '/'.trim((string) parse_url($proxyBase, PHP_URL_PATH), '/');

            if ($proxyPath !== '/' && ($url === $proxyPath || str_starts_with($url, $proxyPath.'/'))) {
                $suffix = substr($url, strlen($proxyPath));

                return rtrim($proxyBase, '/').($suffix === '' ? '/' : $suffix);
            }

            return rtrim($proxyBase, '/').$url;
        }

        return $this->rewriteBody($url, $internalBase, $proxyBase);
    }

    /**
     * 社員サイト側 Cookie を upstream へ渡さない（ポータル専用 Cookie のみ転送）。
     */
    private function forwardCookieHeader(?string $cookieHeader, string $portalPath): ?string
    {
        if (! is_string($cookieHeader) || $cookieHeader === '') {
            return null;
        }

        $allowedNames = match (trim($portalPath, '/')) {
            'realestate-portal' => ['real_estate_portal_session', 'XSRF-TOKEN'],
            default => [],
        };

        if ($allowedNames === []) {
            return $cookieHeader;
        }

        $pairs = array_filter(array_map('trim', explode(';', $cookieHeader)));
        $forwarded = [];

        foreach ($pairs as $pair) {
            if (! str_contains($pair, '=')) {
                continue;
            }

            [$name] = explode('=', $pair, 2);
            if (in_array($name, $allowedNames, true)) {
                $forwarded[] = $pair;
            }
        }

        if ($forwarded === []) {
            return null;
        }

        return implode('; ', $forwarded);
    }

    private function rewriteCookiePath(string $cookie, string $proxyPath): string
    {
        $path = '/'.trim($proxyPath, '/');

        if (preg_match('/;\s*path=([^;]+)/i', $cookie)) {
            return preg_replace('/;\s*path=([^;]+)/i', '; path='.$path, $cookie) ?? $cookie;
        }

        return rtrim($cookie, ';').'; path='.$path;
    }
}

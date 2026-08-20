<?php

namespace App\Services;

use App\Models\User;
use App\Support\DepartmentPortal;
use GuzzleHttp\Cookie\CookieJar;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response as ClientResponse;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * GitHub login_look の handoff API を社員サイトプロキシ内から呼び出す。
 * ブラウザを callback URL へはリダイレクトせず、サーバー側でセッションを確立する。
 */
class RealEstatePortalSsoHandoff
{
    public function __construct(
        private DepartmentPortalIdentityToken $identityToken,
    ) {}

    /**
     * handoff → callback → 目的 URL まで同一 Cookie Jar で取得する。
     *
     * @return array{upstream: ClientResponse, set_cookies: list<string>}
     */
    public function authenticateAndFetch(User $user, string $targetUrl): array
    {
        $portal = DepartmentPortal::find('real-estate');
        if (! is_array($portal)) {
            throw new RuntimeException('不動産ポータルが設定されていません。');
        }

        $internalBase = rtrim((string) ($portal['internal_url'] ?? ''), '/');
        if ($internalBase === '') {
            throw new RuntimeException('不動産ポータルの接続先が設定されていません。');
        }

        $proxyPath = trim((string) ($portal['proxy_path'] ?? ''), '/');

        $handoffResponse = $this->requestHandoff($user, $internalBase);
        $internalCallbackUrl = $this->resolveInternalCallbackUrl(
            trim((string) ($handoffResponse->json('redirect_url') ?? '')),
            $internalBase,
        );

        $cookieJar = new CookieJar;

        try {
            $callbackResponse = $this->upstreamClient($internalBase, $cookieJar)
                ->get($internalCallbackUrl);
        } catch (ConnectionException $e) {
            Log::error('Real estate SSO callback unreachable', [
                'target' => $internalCallbackUrl,
                'message' => $e->getMessage(),
            ]);

            throw new RuntimeException('不動産ポータルへの SSO 接続に失敗しました。', previous: $e);
        } catch (RuntimeException $e) {
            Log::error('Real estate SSO callback identity token failed', [
                'target' => $internalCallbackUrl,
                'message' => $e->getMessage(),
            ]);

            throw new RuntimeException('不動産ポータルへの SSO 認証に失敗しました。', previous: $e);
        }

        if (! in_array($callbackResponse->status(), [200, 302], true)) {
            Log::warning('Real estate SSO callback failed', [
                'target' => $internalCallbackUrl,
                'status' => $callbackResponse->status(),
                'body' => $callbackResponse->body(),
            ]);

            throw new RuntimeException('不動産ポータルへの SSO 完了に失敗しました。');
        }

        $setCookies = $this->browserSetCookiesFromJar($cookieJar, $proxyPath);
        $sessionCookieHeader = $this->sessionCookieHeaderFromJar($cookieJar);

        if ($sessionCookieHeader === null) {
            throw new RuntimeException('不動産ポータルのセッション Cookie を取得できませんでした。');
        }

        try {
            // Cookie Jar は path 不一致で /home に Cookie を付けないため、明示的に付与する。
            $upstream = $this->upstreamClient($internalBase)
                ->withHeaders(['Cookie' => $sessionCookieHeader])
                ->get($targetUrl);
        } catch (ConnectionException $e) {
            Log::error('Real estate SSO upstream fetch unreachable', [
                'target' => $targetUrl,
                'message' => $e->getMessage(),
            ]);

            throw new RuntimeException('不動産ポータルへの接続に失敗しました。', previous: $e);
        } catch (RuntimeException $e) {
            Log::error('Real estate SSO upstream fetch identity token failed', [
                'target' => $targetUrl,
                'message' => $e->getMessage(),
            ]);

            throw new RuntimeException('不動産ポータルへの認証に失敗しました。', previous: $e);
        }

        return [
            'upstream' => $upstream,
            'set_cookies' => $setCookies,
        ];
    }

    private function resolveInternalCallbackUrl(string $redirectUrl, string $internalBase): string
    {
        if ($redirectUrl === '') {
            throw new RuntimeException('不動産ポータルから SSO 応答を取得できませんでした。');
        }

        if (str_starts_with($redirectUrl, '/')) {
            return $internalBase.$redirectUrl;
        }

        return $redirectUrl;
    }

    /**
     * @return list<string>
     */
    private function browserSetCookiesFromJar(CookieJar $jar, string $proxyPath): array
    {
        $allowedNames = ['real_estate_portal_session', 'XSRF-TOKEN'];
        $path = '/'.trim($proxyPath, '/');
        $setCookies = [];

        foreach ($jar->toArray() as $cookie) {
            $name = (string) ($cookie['Name'] ?? '');
            if (! in_array($name, $allowedNames, true)) {
                continue;
            }

            $parts = [$name.'='.($cookie['Value'] ?? ''), 'path='.$path];

            if (! empty($cookie['HttpOnly'])) {
                $parts[] = 'httponly';
            }

            if (! empty($cookie['Secure'])) {
                $parts[] = 'secure';
            }

            if (! empty($cookie['SameSite'])) {
                $parts[] = 'samesite='.strtolower((string) $cookie['SameSite']);
            }

            $setCookies[] = implode('; ', $parts);
        }

        if (! collect($setCookies)->contains(fn (string $cookie): bool => str_starts_with($cookie, 'real_estate_portal_session='))) {
            throw new RuntimeException('不動産ポータルのセッション Cookie を取得できませんでした。');
        }

        return $setCookies;
    }

    private function sessionCookieHeaderFromJar(CookieJar $jar): ?string
    {
        $allowedNames = ['real_estate_portal_session', 'XSRF-TOKEN'];
        $pairs = [];

        foreach ($jar->toArray() as $cookie) {
            $name = (string) ($cookie['Name'] ?? '');
            if (in_array($name, $allowedNames, true)) {
                $pairs[] = $name.'='.($cookie['Value'] ?? '');
            }
        }

        if ($pairs === []) {
            return null;
        }

        return implode('; ', $pairs);
    }

    private function requestHandoff(User $user, string $internalBase): ClientResponse
    {
        $handoffUrl = $internalBase.'/internal/portal/sso/handoff';

        $formalName = trim(implode(' ', array_filter([
            trim((string) ($user->last_name ?? '')),
            trim((string) ($user->first_name ?? '')),
        ], static fn (string $part): bool => $part !== '' && $part !== '未設定')));

        if ($formalName === '') {
            $formalName = trim((string) ($user->name ?? ''));
        }

        try {
            $response = $this->upstreamClient($internalBase)
                ->post($handoffUrl, array_filter([
                    'email' => (string) $user->email,
                    'employee_id' => trim((string) ($user->employee_id ?? '')),
                    'name' => $formalName !== '' ? $formalName : null,
                ]));
        } catch (ConnectionException $e) {
            Log::error('Real estate SSO handoff unreachable', [
                'target' => $handoffUrl,
                'message' => $e->getMessage(),
            ]);

            throw new RuntimeException('不動産ポータルへの SSO 接続に失敗しました。', previous: $e);
        } catch (RuntimeException $e) {
            Log::error('Real estate SSO identity token failed', [
                'target' => $handoffUrl,
                'message' => $e->getMessage(),
            ]);

            throw new RuntimeException('不動産ポータルへの SSO 認証に失敗しました。', previous: $e);
        }

        if (! $response->successful()) {
            Log::warning('Real estate SSO handoff denied', [
                'target' => $handoffUrl,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            throw new RuntimeException('不動産ポータルへの SSO 開始に失敗しました。');
        }

        return $response;
    }

    private function upstreamClient(string $internalBase, ?CookieJar $cookieJar = null): \Illuminate\Http\Client\PendingRequest
    {
        $headers = [
            DepartmentPortal::EMPLOYEE_PORTAL_HEADER => '1',
            DepartmentPortal::EMPLOYEE_PORTAL_TAB_HEADER => 'real-estate',
        ];

        $proxySecret = DepartmentPortal::proxySecret('real-estate');
        if ($proxySecret !== null) {
            $headers[DepartmentPortal::EMPLOYEE_PORTAL_PROXY_SECRET_HEADER] = $proxySecret;
        }

        $client = Http::timeout(30)
            ->withoutRedirecting()
            ->withHeaders($headers);

        if ($cookieJar !== null) {
            $client = $client->withOptions(['cookies' => $cookieJar]);
        }

        $token = $this->identityToken->token('real-estate', $internalBase);
        if ($token) {
            $client = $client->withToken($token);
        }

        return $client;
    }
}

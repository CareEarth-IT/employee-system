<?php

namespace App\Services\DepartmentPortalProxy;

use App\Models\User;
use App\Services\RealEstatePortalSsoHandoff;
use App\Support\DepartmentPortal;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Response;

/**
 * 不動産ポータル専用: SSO handoff、logout、セッション Cookie 処理。
 */
class RealEstatePortalProxyHandler
{
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

        $response = $this->responseRewriter->toProxiedResponse($upstream, $request, $internalBase, $portalPath);

        return $this->attachPortalSetCookies($response, $result['set_cookies']);
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

        $response = redirect()->route('dashboard', ['tab' => 'real-estate']);

        return $this->expirePortalSessionCookies($response, $portalPath);
    }

    public function hasPortalSessionCookie(Request $request, string $portalPath): bool
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

    /**
     * @param  list<string>  $setCookieHeaders
     */
    private function attachPortalSetCookies(Response $response, array $setCookieHeaders): Response
    {
        foreach ($setCookieHeaders as $cookieHeader) {
            $response->headers->setCookie(Cookie::fromString($cookieHeader));
        }

        return $response;
    }
}

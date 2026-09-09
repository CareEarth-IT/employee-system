<?php

namespace App\Services\DepartmentPortalProxy;

use App\Services\DepartmentPortalIdentityToken;
use App\Support\DepartmentPortal;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response as ClientResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * employee → 部署ポータル upstream への HTTP 転送。
 * Identity Token 付与と X-Employee-Portal-* ヘッダを担当する。
 */
class DepartmentPortalUpstreamClient
{
    public function __construct(
        private DepartmentPortalIdentityToken $identityToken,
    ) {}

    /**
     * @throws RuntimeException Identity Token 取得失敗
     * @throws ConnectionException upstream 到達不可
     */
    public function send(Request $request, string $tabKey, string $targetUrl, string $audience, string $portalPath): ClientResponse
    {
        $client = Http::timeout(60)
            ->withoutRedirecting()
            ->withHeaders($this->forwardHeaders($request, $tabKey, $portalPath));

        $token = $this->identityToken->token($tabKey, $audience);
        if ($token) {
            $client = $client->withToken($token);
        }

        $method = strtoupper($request->method());

        if (in_array($method, ['POST', 'PUT', 'PATCH'], true) && $this->isMultipartRequest($request)) {
            return $client
                ->withBody($request->getContent(), (string) $request->header('Content-Type'))
                ->send($method, $targetUrl);
        }

        return match ($method) {
            'POST' => $client->asForm()->post($targetUrl, $request->all()),
            'PUT' => $client->asForm()->put($targetUrl, $request->all()),
            'PATCH' => $client->asForm()->patch($targetUrl, $request->all()),
            'DELETE' => $client->delete($targetUrl, $request->all()),
            default => $client->get($targetUrl),
        };
    }

    private function isMultipartRequest(Request $request): bool
    {
        return str_contains(
            strtolower((string) $request->header('Content-Type', '')),
            'multipart/form-data',
        );
    }

    /**
     * @return array<string, string>
     */
    public function forwardHeaders(Request $request, string $tabKey, string $portalPath): array
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

        $cookie = $this->forwardCookieHeader($request, $portalPath);
        if ($cookie !== null) {
            $headers['Cookie'] = $cookie;
        }

        if (! isset($headers['Referer'])) {
            $headers['Referer'] = $request->fullUrl();
        }

        return $headers;
    }

    /**
     * 社員サイト側 Cookie を upstream へ渡さない（ポータル専用 Cookie のみ転送）。
     */
    public function forwardCookieHeader(Request $request, string $portalPath): ?string
    {
        if (trim($portalPath, '/') === 'realestate-portal') {
            return app(RealEstatePortalProxyHandler::class)->portalSessionCookieHeader($request);
        }

        $cookieHeader = $request->headers->get('Cookie');
        if (! is_string($cookieHeader) || $cookieHeader === '') {
            return null;
        }

        return $cookieHeader;
    }
}

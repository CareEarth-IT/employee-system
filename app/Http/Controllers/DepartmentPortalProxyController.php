<?php

namespace App\Http\Controllers;

use App\Services\DepartmentPortalIdentityToken;
use App\Support\DepartmentPortal;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response as ClientResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

class DepartmentPortalProxyController extends Controller
{
    public function __construct(
        private DepartmentPortalIdentityToken $identityToken,
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

        try {
            $upstream = $this->sendRequest($request, $tabKey, $targetUrl, $internalBase);
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

        return $this->toProxiedResponse($upstream, $request, $internalBase, $portalPath);
    }

    private function sendRequest(Request $request, string $tabKey, string $targetUrl, string $audience): ClientResponse
    {
        $client = Http::timeout(60)
            ->withoutRedirecting()
            ->withHeaders($this->forwardHeaders($request, $tabKey));

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
    private function forwardHeaders(Request $request, string $tabKey): array
    {
        $headers = [
            DepartmentPortal::EMPLOYEE_PORTAL_HEADER => '1',
            DepartmentPortal::EMPLOYEE_PORTAL_TAB_HEADER => $tabKey,
        ];

        $proxySecret = DepartmentPortal::proxySecret($tabKey);
        if ($proxySecret !== null) {
            $headers[DepartmentPortal::EMPLOYEE_PORTAL_PROXY_SECRET_HEADER] = $proxySecret;
        }

        foreach (['Accept', 'Accept-Language', 'Content-Type', 'Referer'] as $name) {
            $value = $request->headers->get($name);
            if (is_string($value) && $value !== '') {
                $headers[$name] = $value;
            }
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

        return str_replace(array_keys($replacements), array_values($replacements), $body);
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

    private function rewriteCookiePath(string $cookie, string $proxyPath): string
    {
        $path = '/'.trim($proxyPath, '/');

        if (preg_match('/;\s*path=([^;]+)/i', $cookie)) {
            return preg_replace('/;\s*path=([^;]+)/i', '; path='.$path, $cookie) ?? $cookie;
        }

        return rtrim($cookie, ';').'; path='.$path;
    }
}

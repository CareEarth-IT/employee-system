<?php

namespace App\Services\DepartmentPortalProxy;

use Illuminate\Http\Client\Response as ClientResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * upstream レスポンスの URL / Cookie path を employee プロキシ path へ書き換える。
 */
class DepartmentPortalResponseRewriter
{
    public function toProxiedResponse(
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

    public function resolveUpstreamPath(?string $path, string $proxyPath): string
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

    public function rewriteBody(string $body, string $internalBase, string $proxyBase): string
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
    public function rewriteRootRelativeUrls(string $body, string $proxyBase): string
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

    public function rewriteUrl(string $url, string $internalBase, string $proxyBase): string
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

    public function rewriteCookiePath(string $cookie, string $proxyPath): string
    {
        $path = '/'.trim($proxyPath, '/');

        if (preg_match('/;\s*path=([^;]+)/i', $cookie)) {
            return preg_replace('/;\s*path=([^;]+)/i', '; path='.$path, $cookie) ?? $cookie;
        }

        return rtrim($cookie, ';').'; path='.$path;
    }
}

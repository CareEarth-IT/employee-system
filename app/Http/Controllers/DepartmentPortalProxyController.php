<?php

namespace App\Http\Controllers;

use App\Services\DepartmentPortalProxy\DepartmentPortalResponseRewriter;
use App\Services\DepartmentPortalProxy\DepartmentPortalUpstreamClient;
use App\Services\DepartmentPortalProxy\RealEstatePortalProxyHandler;
use App\Support\DepartmentPortal;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

/**
 * 部署別社内サイトへのリバースプロキシ。
 *
 * 処理概要:
 * 1. DepartmentPortal::canAccess でタブ権限を確認
 * 2. DepartmentPortalUpstreamClient が upstream へ転送（Identity Token / ヘッダ）
 * 3. DepartmentPortalResponseRewriter が URL・Cookie をプロキシ path へ書き換え
 * 4. 不動産のみ RealEstatePortalProxyHandler（SSO handoff / logout / セッション Cookie）
 *
 * @see config/department_portals.php
 * @see docs/architecture.md
 */
class DepartmentPortalProxyController extends Controller
{
    public function __construct(
        private DepartmentPortalUpstreamClient $upstreamClient,
        private DepartmentPortalResponseRewriter $responseRewriter,
        private RealEstatePortalProxyHandler $realEstateHandler,
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

        $targetPath = $this->responseRewriter->resolveUpstreamPath($path, $portalPath);
        $targetUrl = $internalBase.$targetPath;
        if ($request->getQueryString()) {
            $targetUrl .= '?'.$request->getQueryString();
        }

        if ($tabKey === 'real-estate' && $this->realEstateHandler->isLogoutPath($targetPath)) {
            return $this->realEstateHandler->finishLogout(
                $request,
                $targetUrl,
                $internalBase,
                $portalPath,
                $tabKey,
            );
        }

        if ($this->realEstateHandler->shouldInlineSso($request, $tabKey, $targetPath, $portalPath)) {
            return $this->realEstateHandler->proxyWithEstablishedSession(
                $request,
                $user,
                $targetUrl,
                $internalBase,
                $portalPath,
            );
        }

        try {
            $upstream = $this->upstreamClient->send($request, $tabKey, $targetUrl, $internalBase, $portalPath);
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
            && $this->realEstateHandler->shouldRetrySso($request, $tabKey, $targetPath)
        ) {
            return $this->realEstateHandler->proxyWithEstablishedSession(
                $request,
                $user,
                $targetUrl,
                $internalBase,
                $portalPath,
            );
        }

        return $this->responseRewriter->toProxiedResponse($upstream, $request, $internalBase, $portalPath);
    }
}

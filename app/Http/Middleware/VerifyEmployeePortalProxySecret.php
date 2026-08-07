<?php

namespace App\Http\Middleware;

use App\Support\DepartmentPortal;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyEmployeePortalProxySecret
{
    public function handle(Request $request, Closure $next): Response
    {
        $expected = trim((string) config('services.employee_portal.proxy_secret', ''));

        if ($expected === '') {
            abort(503, '社員ディレクトリ API が設定されていません。');
        }

        $provided = trim((string) $request->header(
            DepartmentPortal::EMPLOYEE_PORTAL_PROXY_SECRET_HEADER,
            '',
        ));

        if ($provided === '' || ! hash_equals($expected, $provided)) {
            abort(403);
        }

        return $next($request);
    }
}

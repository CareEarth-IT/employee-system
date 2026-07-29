<?php

namespace App\Http\Middleware;

use App\Support\RequestUrl;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ApplyRequestUrl
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        RequestUrl::remember($request);
        RequestUrl::applyRoot($request);

        return $next($request);
    }
}

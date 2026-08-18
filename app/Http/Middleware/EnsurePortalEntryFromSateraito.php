<?php

namespace App\Http\Middleware;

use App\Services\PortalEntryGate;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePortalEntryFromSateraito
{
    public function __construct(
        private readonly PortalEntryGate $gate,
    ) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->gate->isRequired() || $this->gate->isGranted($request)) {
            return $next($request);
        }

        return redirect()->route('portal.entry-required');
    }
}

<?php

namespace App\Services;

use Illuminate\Http\Request;

class PortalEntryGate
{
    public const SESSION_KEY = 'portal_entry_granted_at';

    public function isRequired(): bool
    {
        return (bool) config('portal_entry.require_sateraito', false);
    }

    public function isGranted(Request $request): bool
    {
        if (! $this->isRequired()) {
            return true;
        }

        $grantedAt = $request->session()->get(self::SESSION_KEY);
        if (! is_int($grantedAt) && ! (is_string($grantedAt) && ctype_digit($grantedAt))) {
            return $this->grantFromReferer($request);
        }

        $grantedAt = (int) $grantedAt;
        $ttl = (int) config('portal_entry.grant_ttl_seconds', 900);
        if ($ttl > 0 && (time() - $grantedAt) > $ttl) {
            $request->session()->forget(self::SESSION_KEY);

            return $this->grantFromReferer($request);
        }

        return true;
    }

    public function grant(Request $request): void
    {
        $request->session()->put(self::SESSION_KEY, time());
    }

    public function grantFromReferer(Request $request): bool
    {
        if (! $this->isRequired()) {
            return true;
        }

        $hosts = config('portal_entry.allowed_referer_hosts', []);
        if (! is_array($hosts) || $hosts === []) {
            return false;
        }

        $referer = (string) $request->headers->get('referer', '');
        if ($referer === '') {
            return false;
        }

        $host = parse_url($referer, PHP_URL_HOST);
        if (! is_string($host) || $host === '') {
            return false;
        }

        $host = strtolower($host);
        foreach ($hosts as $allowed) {
            $allowed = strtolower(trim((string) $allowed));
            if ($allowed === '') {
                continue;
            }
            if ($host === $allowed || str_ends_with($host, '.'.$allowed)) {
                $this->grant($request);

                return true;
            }
        }

        return false;
    }

    public function guestRedirectRoute(): string
    {
        if ($this->isRequired()) {
            return 'portal.entry-required';
        }

        return 'login';
    }
}

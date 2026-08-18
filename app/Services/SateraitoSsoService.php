<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;

class SateraitoSsoService
{
    public function isEnabled(): bool
    {
        if (! (bool) config('sateraito_sso.enabled', false)) {
            return false;
        }

        return trim((string) config('sateraito_sso.secret', '')) !== '';
    }

    public function entryUrl(): ?string
    {
        $explicit = trim((string) config('sateraito_sso.entry_url', ''));
        if ($explicit !== '') {
            return $explicit;
        }

        $ssoId = trim((string) config('sateraito_sso.sso_id', ''));
        $domain = trim((string) config('sateraito_sso.sso_domain', ''));
        if ($ssoId === '' || $domain === '') {
            return null;
        }

        return sprintf(
            'https://sateraito-apps-sso.appspot.com/a/%s/sso/general?ssoid=%s',
            rawurlencode($domain),
            rawurlencode($ssoId),
        );
    }

    /**
     * @return array{user: User, redirect: string}
     */
    public function authenticate(Request $request): array
    {
        if (! $this->isEnabled()) {
            throw new RuntimeException('Sateraito SSO is not configured.');
        }

        $payload = $this->extractPayload($request);
        $email = $payload['email'];

        $this->ensureNotRateLimited($request, $email);
        $this->verifySignature($payload);
        $this->verifyTimestamp($payload['timestamp']);

        $this->grantPortalEntry($request);

        $user = User::query()->where('email', $email)->first();
        if ($user === null) {
            RateLimiter::hit($this->throttleKey($request, $email));
            Log::warning('Sateraito SSO rejected: user not found', ['email' => $email]);

            throw new InvalidArgumentException('User not found.');
        }

        RateLimiter::clear($this->throttleKey($request, $email));

        Auth::login($user, false);
        $request->session()->regenerate();

        return [
            'user' => $user,
            'redirect' => $this->resolveRedirect($payload['redirect']),
        ];
    }

    /**
     * @return array{email: string, timestamp: ?int, signature: string, redirect: string, fields: array<string, string>}
     */
    private function extractPayload(Request $request): array
    {
        $paramNames = (array) config('sateraito_sso.params', []);

        $emailParam = (string) ($paramNames['email'] ?? 'mail');
        $timestampParam = (string) ($paramNames['timestamp'] ?? 'time');
        $signatureParam = (string) ($paramNames['signature'] ?? 'sign');
        $redirectParam = (string) ($paramNames['redirect'] ?? 'redirect');

        $email = $this->normalizeEmail((string) $request->input($emailParam, ''));
        if ($email === '') {
            throw new InvalidArgumentException('Email parameter is missing.');
        }

        if (! $this->isAllowedEmailDomain($email)) {
            throw new InvalidArgumentException('Email domain is not allowed.');
        }

        $timestampRaw = trim((string) $request->input($timestampParam, ''));
        $timestamp = $timestampRaw === '' ? null : $this->parseTimestamp($timestampRaw);

        return [
            'email' => $email,
            'timestamp' => $timestamp,
            'signature' => trim((string) $request->input($signatureParam, '')),
            'redirect' => trim((string) $request->input($redirectParam, '')),
            'fields' => [
                'email' => $email,
                'timestamp' => $timestampRaw,
            ],
        ];
    }

    /**
     * @param  array{email: string, timestamp: ?int, signature: string, redirect: string, fields: array<string, string>}  $payload
     */
    private function verifySignature(array $payload): void
    {
        $signatureConfig = (array) config('sateraito_sso.signature', []);
        if (! (bool) ($signatureConfig['enabled'] ?? true)) {
            return;
        }

        $provided = $payload['signature'];
        if ($provided === '') {
            throw new InvalidArgumentException('Signature parameter is missing.');
        }

        $expected = $this->buildSignature($payload['fields']);
        if (! hash_equals(strtolower($expected), strtolower($provided))) {
            Log::warning('Sateraito SSO rejected: invalid signature', [
                'email' => $payload['email'],
            ]);

            throw new InvalidArgumentException('Invalid signature.');
        }
    }

    /**
     * @param  array<string, string>  $fieldValues
     */
    public function buildSignature(array $fieldValues): string
    {
        $signatureConfig = (array) config('sateraito_sso.signature', []);
        $fieldKeys = $signatureConfig['fields'] ?? ['email', 'timestamp'];
        if (! is_array($fieldKeys) || $fieldKeys === []) {
            $fieldKeys = ['email', 'timestamp'];
        }

        $parts = [];
        foreach ($fieldKeys as $key) {
            $parts[] = (string) ($fieldValues[$key] ?? '');
        }

        $concatenated = implode('', $parts);
        $secret = (string) config('sateraito_sso.secret', '');
        $position = (string) ($signatureConfig['secret_position'] ?? 'prefix');
        $algorithm = strtolower((string) ($signatureConfig['algorithm'] ?? 'md5'));

        $input = match ($position) {
            'suffix' => $concatenated.$secret,
            'none' => $concatenated,
            default => $secret.$concatenated,
        };

        return match ($algorithm) {
            'md5' => md5($input),
            'sha1' => sha1($input),
            'sha256' => hash('sha256', $input),
            'hmac_sha256' => hash_hmac('sha256', $concatenated, $secret),
            default => throw new InvalidArgumentException('Unsupported signature algorithm.'),
        };
    }

    private function verifyTimestamp(?int $timestamp): void
    {
        $maxAge = (int) config('sateraito_sso.max_age_seconds', 120);
        if ($maxAge <= 0 || $timestamp === null) {
            return;
        }

        if (abs(time() - $timestamp) > $maxAge) {
            throw new InvalidArgumentException('Timestamp expired.');
        }
    }

    private function resolveRedirect(string $redirect): string
    {
        $fallback = (string) config('sateraito_sso.default_redirect', '/dashboard');
        if ($redirect === '') {
            return $fallback;
        }

        if (! Str::startsWith($redirect, '/')) {
            return $fallback;
        }

        if (Str::startsWith($redirect, '//')) {
            return $fallback;
        }

        return $redirect;
    }

    private function normalizeEmail(string $email): string
    {
        return strtolower(trim($email));
    }

    private function isAllowedEmailDomain(string $email): bool
    {
        $allowed = config('sateraito_sso.allowed_email_domains', []);
        if (! is_array($allowed) || $allowed === []) {
            return true;
        }

        $domain = Str::after($email, '@');

        return $domain !== '' && in_array($domain, $allowed, true);
    }

    private function parseTimestamp(string $value): int
    {
        if (ctype_digit($value)) {
            $timestamp = (int) $value;
            if ($timestamp > 9999999999) {
                $timestamp = (int) floor($timestamp / 1000);
            }

            return $timestamp;
        }

        $parsed = strtotime($value);
        if ($parsed === false) {
            throw new InvalidArgumentException('Invalid timestamp.');
        }

        return $parsed;
    }

    private function ensureNotRateLimited(Request $request, string $email): void
    {
        $key = $this->throttleKey($request, $email);
        if (! RateLimiter::tooManyAttempts($key, 10)) {
            return;
        }

        throw new InvalidArgumentException('Too many attempts.');
    }

    private function throttleKey(Request $request, string $email): string
    {
        return 'sateraito-sso|'.Str::lower($email).'|'.$request->ip();
    }

    private function grantPortalEntry(Request $request): void
    {
        app(PortalEntryGate::class)->grant($request);
    }
}

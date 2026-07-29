<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class FinanceHrSsoController extends Controller
{
    /**
     * 社員ポータルのログイン済みセッションから finance-hr へ SSO する。
     */
    public function __invoke(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user !== null, 401);

        $affiliation = $user->currentAffiliation();
        $formalName = trim(implode(' ', array_filter([
            trim((string) ($user->last_name ?? '')),
            trim((string) ($user->first_name ?? '')),
        ], static fn (string $part): bool => $part !== '' && $part !== '未設定')));

        if ($formalName === '') {
            $formalName = trim((string) ($user->name ?? ''));
        }

        $company = trim((string) ($affiliation?->company ?? ''));
        if ($company === '') {
            $company = $user->displayCompany();
            if ($company === '—') {
                $company = '';
            }
        }

        $department = trim((string) ($affiliation?->department ?? ''));
        $section = trim((string) ($affiliation?->section ?? ''));
        if ($department !== '' && $section !== '') {
            $department = $department.' / '.$section;
        } elseif ($department === '' && $section !== '') {
            $department = $section;
        }

        $payload = [
            'email' => (string) $user->email,
            'name' => $formalName,
            'employee_id' => (string) ($user->employee_id ?? ''),
            'company' => $company,
            'department' => $department,
            'can_admin' => $user->canAccessFinanceHrAdmin(),
            'exp' => time() + 120,
        ];

        $payloadB64 = rtrim(strtr(base64_encode(json_encode($payload, JSON_UNESCAPED_UNICODE)), '+/', '-_'), '=');
        $secret = $this->ssoSecret();
        abort_if($secret === '', 500, 'FINANCE_HR SSO secret is not configured.');

        $token = $payloadB64.'.'.hash_hmac('sha256', $payloadB64, $secret);

        $query = ['token' => $token];
        $category = trim((string) $request->query('category', ''));
        if (in_array($category, ['hr', 'finance', 'is'], true)) {
            $query['category'] = $category;
        }

        return redirect('/finance-hr/sso.php?'.http_build_query($query));
    }

    private function ssoSecret(): string
    {
        $explicit = trim((string) env('FINANCE_HR_SSO_SECRET', ''));
        if ($explicit !== '') {
            return $explicit;
        }

        // config('app.key') は "base64:..." のまま。Encrypter と同様にデコードして使う。
        $key = (string) config('app.key');
        if (str_starts_with($key, 'base64:')) {
            $decoded = base64_decode(substr($key, 7), true);
            if ($decoded !== false && $decoded !== '') {
                return $decoded;
            }
        }

        return $key;
    }
}

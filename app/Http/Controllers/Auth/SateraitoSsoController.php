<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\PortalEntryGate;
use App\Services\SateraitoSsoService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;
use RuntimeException;

class SateraitoSsoController extends Controller
{
    public function __invoke(
        Request $request,
        SateraitoSsoService $sso,
        PortalEntryGate $gate,
    ): RedirectResponse {
        if (Auth::check()) {
            return redirect()->intended(route('dashboard'));
        }

        if (! $sso->isEnabled()) {
            if ($gate->isRequired()) {
                return redirect()
                    ->route('portal.entry-required')
                    ->with('error', 'シングルサインオンは現在利用できません。管理者にお問い合わせください。');
            }

            return redirect()
                ->route('login')
                ->with('error', 'シングルサインオンは現在利用できません。メールアドレスとパスワードでログインしてください。');
        }

        try {
            $result = $sso->authenticate($request);
        } catch (InvalidArgumentException) {
            if ($gate->isRequired() && ! $gate->isGranted($request)) {
                return redirect()->route('portal.entry-required');
            }

            return redirect()
                ->route('login')
                ->with('error', 'シングルサインオンに失敗しました。メールアドレスとパスワードでログインしてください。');
        } catch (RuntimeException) {
            if ($gate->isRequired()) {
                return redirect()
                    ->route('portal.entry-required')
                    ->with('error', 'シングルサインオンは現在利用できません。管理者にお問い合わせください。');
            }

            return redirect()
                ->route('login')
                ->with('error', 'シングルサインオンは現在利用できません。メールアドレスとパスワードでログインしてください。');
        }

        $user = $result['user'];
        if ($user->must_change_password) {
            return redirect()->route('password.change');
        }

        return redirect()->to($result['redirect']);
    }
}

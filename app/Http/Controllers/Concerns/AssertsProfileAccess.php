<?php

namespace App\Http\Controllers\Concerns;

use App\Models\User;
use App\Support\MissingAffiliationRedirect;
use App\Support\UserRouteHelper;
use Illuminate\Http\Response;

trait AssertsProfileAccess
{
    protected function assertCanEditProfile(User $target): void
    {
        if (! auth()->user()->canEditProfile($target)) {
            abort(403);
        }
    }

    protected function assertCanManageAffiliation(User $target): void
    {
        if (! auth()->user()->canManageAffiliation($target)) {
            abort(403);
        }
    }

    /**
     * 302 だと編集画面が履歴に残り、削除後の「戻る」で 404 になる。
     * location.replace でフォーム履歴をプロフィールに差し替える。
     */
    protected function redirectToProfileEdit(User $target, string $message): Response
    {
        return $this->replaceLocationTo(
            UserRouteHelper::route($target, 'profile.edit', 'users.profile.edit'),
            $target,
            $message,
        );
    }

    protected function replaceLocationTo(string $url, User $target, ?string $message = null): Response
    {
        if ($message !== null) {
            session()->flash('success', $message);
        }

        session()->flash('skip_stale_form', true);
        session([MissingAffiliationRedirect::RETURN_USER_SESSION_KEY => $target->id]);

        return response()
            ->view('partials.location-replace', ['url' => $url])
            ->header('Cache-Control', 'private, no-store, no-cache, must-revalidate')
            ->header('Pragma', 'no-cache');
    }

    protected function rememberAffiliationReturnUser(User $target): void
    {
        session([MissingAffiliationRedirect::RETURN_USER_SESSION_KEY => $target->id]);
    }
}

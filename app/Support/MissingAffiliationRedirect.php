<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class MissingAffiliationRedirect
{
    public const RETURN_USER_SESSION_KEY = 'affiliation_return_user_id';

    public function __invoke(Request $request): RedirectResponse
    {
        $message = '所属部署が見つかりません。削除済みの可能性があります。';
        $userId = session(self::RETURN_USER_SESSION_KEY);
        $user = is_numeric($userId) ? User::query()->find((int) $userId) : null;

        if ($user !== null) {
            return redirect()
                ->to(UserRouteHelper::route($user, 'profile.edit', 'users.profile.edit'))
                ->with('error', $message);
        }

        if (auth()->check()) {
            return redirect()
                ->route('profile.edit')
                ->with('error', $message);
        }

        return redirect()
            ->route('dashboard')
            ->with('error', $message);
    }
}

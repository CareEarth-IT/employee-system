<?php

namespace App\Http\Controllers\Concerns;

use App\Models\User;
use App\Support\UserRouteHelper;
use Illuminate\Http\RedirectResponse;

trait AssertsProfileAccess
{
    protected function assertCanEditProfile(User $target): void
    {
        if (! auth()->user()->canEditProfile($target)) {
            abort(403);
        }
    }

    protected function redirectToProfileEdit(User $target, string $message): RedirectResponse
    {
        return redirect()
            ->to(UserRouteHelper::route($target, 'profile.edit', 'users.profile.edit'))
            ->with('success', $message);
    }
}

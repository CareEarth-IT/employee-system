<?php

namespace App\Support;

use App\Models\User;

class UserRouteHelper
{
    public static function isSelf(User $target): bool
    {
        $authId = auth()->id();

        return $authId !== null && $authId === $target->id;
    }

    public static function route(User $target, string $selfRoute, string $otherRoute): string
    {
        return self::isSelf($target)
            ? route($selfRoute)
            : route($otherRoute, $target);
    }
}

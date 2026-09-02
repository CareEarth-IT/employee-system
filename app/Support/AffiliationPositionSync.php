<?php

namespace App\Support;

use App\Models\AffiliationHistory;
use App\Models\User;

class AffiliationPositionSync
{
    /** @var list<string> 所属の役職欄に入ってはいけないラベル */
    public const INVALID_POSITION_LABELS = [
        '一般',
        ...User::EMPLOYMENT_TYPE_OPTIONS,
    ];

    public static function isInvalidPositionLabel(?string $position): bool
    {
        $position = trim((string) $position);

        return $position !== '' && in_array($position, self::INVALID_POSITION_LABELS, true);
    }

    public static function resolveCorrectedPosition(User $user): ?string
    {
        $fromHr = trim((string) ($user->hrDetail?->position_primary ?? ''));

        if ($fromHr !== '' && ! self::isInvalidPositionLabel($fromHr)) {
            return $fromHr;
        }

        return null;
    }

    /**
     * @return list<array{affiliation: AffiliationHistory, previous: string, next: string}>
     */
    public static function syncForUser(User $user, ?string $csvPosition = null): array
    {
        $affiliation = $user->currentAffiliation();

        if ($affiliation === null || ! self::isInvalidPositionLabel($affiliation->position)) {
            return [];
        }

        $current = trim((string) ($affiliation->position ?? ''));
        $target = self::resolveCorrectedPosition($user);

        if ($target === null && $csvPosition !== null) {
            $csvPosition = trim($csvPosition);

            if ($csvPosition !== '' && ! self::isInvalidPositionLabel($csvPosition)) {
                $target = $csvPosition;
            }
        }

        if ($current === ($target ?? '')) {
            return [];
        }

        $affiliation->update(['position' => $target]);

        return [[
            'affiliation' => $affiliation->fresh(),
            'previous' => $current,
            'next' => $target ?? '',
        ]];
    }
}

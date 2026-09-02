<?php

namespace App\Support;

use App\Models\AffiliationHistory;
use App\Models\User;

class AffiliationStartDateAlignment
{
    /** 一括インポート時に誤って設定された開始日 */
    public const BULK_IMPORT_START_DATE = '2026-06-24';

    /**
     * @return list<array{affiliation: AffiliationHistory, previous: string, next: string}>
     */
    public static function syncForUser(User $user): array
    {
        $joinedAt = $user->profile?->joined_at?->toDateString();

        if ($joinedAt === null) {
            return [];
        }

        $affiliations = $user->affiliationHistories->sortBy('start_date')->values();
        $affiliationCount = $affiliations->count();
        $updated = [];

        foreach ($affiliations as $affiliation) {
            if (! self::shouldAlign($affiliation, $joinedAt, $affiliationCount)) {
                continue;
            }

            $current = $affiliation->start_date->toDateString();

            $affiliation->update(['start_date' => $joinedAt]);

            $updated[] = [
                'affiliation' => $affiliation->fresh(),
                'previous' => $current,
                'next' => $joinedAt,
            ];
        }

        return $updated;
    }

    public static function shouldAlign(
        AffiliationHistory $affiliation,
        string $joinedAt,
        int $affiliationCount,
        bool $isEarliest = false,
    ): bool {
        $start = $affiliation->start_date->toDateString();

        if ($start === $joinedAt) {
            return false;
        }

        if ($start === self::BULK_IMPORT_START_DATE && $joinedAt !== self::BULK_IMPORT_START_DATE) {
            return true;
        }

        return $affiliationCount === 1;
    }
}

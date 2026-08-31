<?php

namespace App\Support;

use App\Models\AffiliationHistory;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

final class EmploymentStatus
{
    /** @var list<string> */
    public const ACTIVE_STORED_VALUES = [
        '在籍',
        '在籍中',
        '在職',
    ];

    /**
     * 詳細情報「状況」を社員一覧用の在籍 / 退職 / 辞退に正規化する。
     */
    public static function normalize(?string $status): string
    {
        $status = trim((string) $status);

        return match ($status) {
            '在職', '在籍中', AffiliationHistory::STATUS_ENROLLED => '在籍',
            '退職済', '離職', AffiliationHistory::STATUS_RESIGNED => '退職',
            default => $status,
        };
    }

    /**
     * @param  Builder<User>  $query
     */
    public static function applyUserStatusFilter(Builder $query, string $status): void
    {
        if ($status === '在籍') {
            $query->where(function (Builder $statusQuery) {
                $statusQuery
                    ->whereHas('hrDetail', fn (Builder $hrDetailQuery) => $hrDetailQuery->whereIn(
                        'employment_status',
                        self::ACTIVE_STORED_VALUES,
                    ))
                    ->orWhere(function (Builder $fallbackQuery) {
                        $fallbackQuery
                            ->where(function (Builder $missingStatusQuery) {
                                $missingStatusQuery
                                    ->whereDoesntHave('hrDetail')
                                    ->orWhereHas('hrDetail', function (Builder $hrDetailQuery) {
                                        $hrDetailQuery
                                            ->whereNull('employment_status')
                                            ->orWhere('employment_status', '');
                                    });
                            })
                            ->whereHas('affiliationHistories', fn (Builder $affiliationQuery) => $affiliationQuery->currentlyActive());
                    });
            });

            return;
        }

        if ($status === '退職') {
            $query->where(function (Builder $statusQuery) {
                $statusQuery
                    ->whereHas('hrDetail', fn (Builder $hrDetailQuery) => $hrDetailQuery->where('employment_status', '退職'))
                    ->orWhereHas('affiliationHistories', fn (Builder $affiliationQuery) => $affiliationQuery
                        ->where('enrollment_status', AffiliationHistory::STATUS_RESIGNED));
            });

            return;
        }

        $query->whereHas('hrDetail', fn (Builder $hrDetailQuery) => $hrDetailQuery->where('employment_status', '辞退'));
    }
}

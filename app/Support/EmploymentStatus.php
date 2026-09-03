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
     * 詳細情報「状況」を 在籍 / 休職 / 退職 に正規化する。
     */
    public static function normalize(?string $status): string
    {
        $status = trim((string) $status);

        return match ($status) {
            '在職', '在籍中', AffiliationHistory::STATUS_ENROLLED => '在籍',
            '退職済', '離職', '辞退', AffiliationHistory::STATUS_RESIGNED => '退職',
            default => $status,
        };
    }

    /**
     * @param  Builder<User>  $query
     */
    public static function applyUserStatusFilter(Builder $query, string $status): void
    {
        if ($status === '全体') {
            $query->where(function (Builder $allStatusQuery) {
                $allStatusQuery
                    ->where(function (Builder $activeQuery) {
                        self::applyActiveStatusFilter($activeQuery);
                    })
                    ->orWhere(function (Builder $onLeaveQuery) {
                        self::applyOnLeaveStatusFilter($onLeaveQuery);
                    })
                    ->orWhere(function (Builder $resignedQuery) {
                        self::applyResignedStatusFilter($resignedQuery);
                    });
            });

            return;
        }

        match ($status) {
            '在籍' => self::applyActiveStatusFilter($query),
            '休職' => self::applyOnLeaveStatusFilter($query),
            '退職' => self::applyResignedStatusFilter($query),
            default => null,
        };
    }

    /**
     * @param  Builder<User>  $query
     */
    private static function applyActiveStatusFilter(Builder $query): void
    {
        $query->whereListedEmployee()->whereHas(
            'hrDetail',
            fn (Builder $hrDetailQuery) => $hrDetailQuery->whereIn(
                'employment_status',
                self::ACTIVE_STORED_VALUES,
            ),
        );
    }

    /**
     * @param  Builder<User>  $query
     */
    private static function applyOnLeaveStatusFilter(Builder $query): void
    {
        $query->whereListedEmployee()->whereHas('hrDetail', fn (Builder $hrDetailQuery) => $hrDetailQuery->where('employment_status', '休職'));
    }

    /**
     * @param  Builder<User>  $query
     */
    private static function applyResignedStatusFilter(Builder $query): void
    {
        $query->whereListedEmployee()->whereHas(
            'hrDetail',
            fn (Builder $hrDetailQuery) => $hrDetailQuery->whereIn('employment_status', ['退職', '辞退']),
        );
    }
}

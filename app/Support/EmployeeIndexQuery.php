<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class EmployeeIndexQuery
{
    public static function hasSearchCriteria(Request $request): bool
    {
        return trim((string) $request->query('keyword', '')) !== ''
            || EmployeeIndexFilters::parseFromRequest($request) !== [];
    }

    public static function resolveStatus(Request $request): string
    {
        $status = trim((string) $request->query('status', '在籍'));

        if (! in_array($status, User::EMPLOYMENT_STATUS_TAB_OPTIONS, true)) {
            return '在籍';
        }

        return $status;
    }

    /**
     * @return Builder<User>
     */
    public static function forExport(Request $request): Builder
    {
        $query = User::query()
            ->with([
                'profile',
                'hrDetail',
                'affiliationHistories' => fn ($affiliationQuery) => $affiliationQuery
                    ->currentlyActive()
                    ->orderByDesc('start_date')
                    ->orderByDesc('id')
                    ->limit(1),
            ]);

        EmployeeIndexFilters::apply($query, EmployeeIndexFilters::parseFromRequest($request));

        $keyword = trim((string) $request->query('keyword', ''));
        if ($keyword !== '') {
            EmployeeKeywordSearch::apply($query, $keyword);
        }

        EmploymentStatus::applyUserStatusFilter($query, self::resolveStatus($request));

        return $query
            ->orderBy('last_name')
            ->orderBy('first_name');
    }

    /**
     * @param  Builder<User>  $query
     */
    public static function applyEmployeeIdSort(Builder $query, string $direction): void
    {
        $castType = DB::connection()->getDriverName() === 'sqlite' ? 'INTEGER' : 'UNSIGNED';

        $query
            ->orderByRaw('CASE WHEN employee_id IS NULL OR employee_id = \'\' THEN 1 ELSE 0 END')
            ->orderByRaw('CAST(employee_id AS '.$castType.') '.$direction)
            ->orderBy('employee_id', $direction)
            ->orderBy('last_name')
            ->orderBy('first_name');
    }
}

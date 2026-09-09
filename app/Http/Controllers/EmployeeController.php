<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\EmployeeHrDetailAccess;
use App\Support\EmployeeIndexFilters;
use App\Support\EmployeeIndexQuery;
use App\Support\EmployeeKeywordSearch;
use App\Support\EmploymentStatus;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EmployeeController extends Controller
{
    public function index(Request $request): View
    {
        $status = trim((string) $request->query('status', '在籍'));
        $keyword = trim((string) $request->query('keyword', ''));
        $sort = trim((string) $request->query('sort', ''));
        $direction = strtolower(trim((string) $request->query('direction', '')));
        $filterConditions = EmployeeIndexFilters::parseFromRequest($request);

        if (! in_array($status, User::EMPLOYMENT_STATUS_TAB_OPTIONS, true)) {
            $status = '在籍';
        }

        if ($sort !== 'employee_id') {
            $sort = '';
        }

        if (! in_array($direction, ['asc', 'desc'], true)) {
            $direction = 'asc';
        }

        $query = User::query()
            ->with([
                'profile',
                'hrDetail',
                'affiliationHistories' => fn ($affiliationQuery) => $affiliationQuery
                    ->orderByDesc('start_date')
                    ->orderByDesc('id'),
            ]);

        if ($sort === 'employee_id') {
            EmployeeIndexQuery::applyEmployeeIdSort($query, $direction);
        } else {
            $query
                ->orderBy('last_name')
                ->orderBy('first_name');
        }

        EmployeeIndexFilters::apply($query, $filterConditions);

        EmploymentStatus::applyUserStatusFilter($query, $status);

        if ($keyword !== '') {
            EmployeeKeywordSearch::apply($query, $keyword);
        }

        return view('employees.index', [
            'employees' => $query->get(),
            'filterConditions' => $filterConditions,
            'filterFieldConfig' => EmployeeIndexFilters::fieldConfig(),
            'status' => $status,
            'statuses' => User::EMPLOYMENT_STATUS_TAB_OPTIONS,
            'keyword' => $keyword,
            'sort' => $sort,
            'direction' => $sort === 'employee_id' ? $direction : '',
            'canExportHrDetails' => EmployeeHrDetailAccess::canExportCsv($request->user()),
            'hrDetailExportUrl' => self::hrDetailExportUrl($request),
            'canImportEmployees' => (bool) $request->user()?->isInformationSystems(),
            'canManageEmployeeRegistry' => (bool) $request->user()?->canManageEmployeeRegistry(),
            'statusTabs' => User::EMPLOYMENT_STATUS_TAB_OPTIONS,
        ]);
    }

    private static function hrDetailExportUrl(Request $request): string
    {
        if (! EmployeeIndexQuery::hasSearchCriteria($request)) {
            return route('hr-details.export', [
                'status' => EmployeeIndexQuery::resolveStatus($request),
            ]);
        }

        $params = array_filter(array_merge(
            EmployeeIndexFilters::toQueryParams(EmployeeIndexFilters::parseFromRequest($request)),
            ['keyword' => trim((string) $request->query('keyword', ''))],
        ), fn ($value) => $value !== '' && $value !== []);

        return route('hr-details.export', $params);
    }
}

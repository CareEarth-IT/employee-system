<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\ScheduledAffiliationChangeApplier;
use App\Support\EmployeeHrDetailAccess;
use App\Support\EmployeeIndexFilters;
use App\Support\EmployeeIndexQuery;
use App\Support\EmployeeKeywordSearch;
use App\Support\EmploymentStatus;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EmployeeController extends Controller
{
    private const LIST_PER_PAGE = 50;

    public function index(Request $request, ScheduledAffiliationChangeApplier $scheduledApplier): View
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

        // 一覧の行ごとチェックを避け、反映日到来分は先にまとめて適用する
        $scheduledApplier->applyDue();

        $query = User::query()
            ->with([
                'profile:id,user_id,english_name,name_kana',
                'hrDetail:id,user_id,employment_status,employment_type,company_phone,jurisdiction,department_primary,section_primary,position_primary',
                'affiliationHistories' => fn ($affiliationQuery) => $affiliationQuery
                    ->currentlyActive()
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

        $employees = $query->paginate(self::LIST_PER_PAGE)->withQueryString();
        $employees->getCollection()->each(
            fn (User $employee) => $employee->skipScheduledAffiliationApply()
        );

        return view('employees.index', [
            'employees' => $employees,
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
            'canViewSocialInsuranceStats' => (bool) $request->user()?->canViewSocialInsuranceStats(),
            'statusTabs' => User::EMPLOYMENT_STATUS_TAB_OPTIONS,
        ]);
    }

    private static function hrDetailExportUrl(Request $request): string
    {
        $params = array_filter(array_merge(
            ['status' => EmployeeIndexQuery::resolveStatus($request)],
            EmployeeIndexFilters::toQueryParams(EmployeeIndexFilters::parseFromRequest($request)),
            ['keyword' => trim((string) $request->query('keyword', ''))],
        ), fn ($value) => $value !== '' && $value !== []);

        return route('hr-details.export', $params);
    }
}

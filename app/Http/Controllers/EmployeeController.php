<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\EmployeeHrDetailAccess;
use App\Support\EmployeeKeywordSearch;
use App\Support\EmploymentStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class EmployeeController extends Controller
{
    public function index(Request $request): View
    {
        $company = trim((string) $request->query('company', ''));
        $status = trim((string) $request->query('status', '在籍'));
        $employeeId = trim((string) $request->query('employee_id', ''));
        $employmentType = trim((string) $request->query('employment_type', ''));
        $keyword = trim((string) $request->query('keyword', ''));
        $sort = trim((string) $request->query('sort', ''));
        $direction = strtolower(trim((string) $request->query('direction', '')));

        if ($company !== '' && ! in_array($company, User::COMPANY_NAMES, true)) {
            $company = '';
        }

        if (! in_array($status, User::EMPLOYMENT_STATUS_OPTIONS, true)) {
            $status = '在籍';
        }

        if ($employmentType !== '' && ! in_array($employmentType, User::EMPLOYMENT_TYPE_OPTIONS, true)) {
            $employmentType = '';
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
            $this->applyEmployeeIdSort($query, $direction);
        } else {
            $query
                ->orderBy('last_name')
                ->orderBy('first_name');
        }

        if ($company !== '') {
            $query->whereDisplayCompany($company);
        }

        EmploymentStatus::applyUserStatusFilter($query, $status);

        if ($employeeId !== '') {
            $query->where('employee_id', 'like', '%'.$employeeId.'%');
        }

        if ($employmentType !== '') {
            $query->where(function (Builder $employmentQuery) use ($employmentType) {
                $employmentQuery
                    ->whereHas('hrDetail', fn (Builder $hrDetailQuery) => $hrDetailQuery->where('employment_type', $employmentType))
                    ->orWhereHas('affiliationHistories', function (Builder $affiliationQuery) use ($employmentType) {
                        $affiliationQuery
                            ->currentlyActive()
                            ->where('position', $employmentType);
                    });
            });
        }

        if ($keyword !== '') {
            EmployeeKeywordSearch::apply($query, $keyword);
        }

        return view('employees.index', [
            'employees' => $query->get(),
            'company' => $company,
            'companies' => User::COMPANY_NAMES,
            'status' => $status,
            'statuses' => User::EMPLOYMENT_STATUS_OPTIONS,
            'employeeId' => $employeeId,
            'employmentType' => $employmentType,
            'employmentTypes' => User::EMPLOYMENT_TYPE_OPTIONS,
            'keyword' => $keyword,
            'sort' => $sort,
            'direction' => $sort === 'employee_id' ? $direction : '',
            'canExportHrDetails' => EmployeeHrDetailAccess::canExportCsv($request->user()),
            'canImportEmployees' => (bool) $request->user()?->isInformationSystems(),
            'canManageEmployeeRegistry' => (bool) $request->user()?->canManageEmployeeRegistry(),
            'statusTabs' => User::EMPLOYMENT_STATUS_OPTIONS,
        ]);
    }

    /**
     * @param  Builder<User>  $query
     */
    private function applyEmployeeIdSort(Builder $query, string $direction): void
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

<?php

namespace App\Http\Controllers;

use App\Models\AffiliationHistory;
use App\Models\User;
use App\Support\EmployeeHrDetailAccess;
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
            ->with(['profile', 'hrDetail', 'affiliationHistories']);

        if ($sort === 'employee_id') {
            $this->applyEmployeeIdSort($query, $direction);
        } else {
            $query
                ->orderBy('last_name')
                ->orderBy('first_name');
        }

        if ($company !== '') {
            $query->whereHas('affiliationHistories', function ($affiliationQuery) use ($company) {
                $affiliationQuery
                    ->currentlyActive()
                    ->where('company', $company);
            });
        }

        $this->applyStatusFilter($query, $status);

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
            $like = '%'.$keyword.'%';
            $query->where(function ($keywordQuery) use ($like, $keyword) {
                $keywordQuery
                    ->where('name', 'like', $like)
                    ->orWhere('email', 'like', $like)
                    ->orWhere('last_name', 'like', $like)
                    ->orWhere('first_name', 'like', $like)
                    ->orWhereHas('profile', function ($profileQuery) use ($like) {
                        $profileQuery
                            ->where('english_name', 'like', $like)
                            ->orWhere('name_kana', 'like', $like);
                    })
                    ->orWhereHas('affiliationHistories', function ($affiliationQuery) use ($like) {
                        $affiliationQuery
                            ->currentlyActive()
                            ->where(function ($activeAffiliationQuery) use ($like) {
                                $activeAffiliationQuery
                                    ->where('department', 'like', $like)
                                    ->orWhere('section', 'like', $like)
                                    ->orWhere('position', 'like', $like);
                            });
                    })
                    ->orWhereHas('hrDetail', function ($hrDetailQuery) use ($like, $keyword) {
                        $hrDetailQuery
                            ->where('company_phone', 'like', $like)
                            ->orWhere('phone', 'like', $like);

                        $digits = preg_replace('/\D/u', '', $keyword) ?? '';
                        if ($digits !== '') {
                            $digitLike = '%'.$digits.'%';
                            $hrDetailQuery
                                ->orWhereRaw(
                                    "REPLACE(REPLACE(REPLACE(REPLACE(COALESCE(company_phone, ''), '-', ''), ' ', ''), '，', ''), '、', '') LIKE ?",
                                    [$digitLike],
                                )
                                ->orWhereRaw(
                                    "REPLACE(REPLACE(REPLACE(REPLACE(COALESCE(phone, ''), '-', ''), ' ', ''), '，', ''), '、', '') LIKE ?",
                                    [$digitLike],
                                );
                        }
                    });
            });
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
            'canEditEmployeeIdentity' => (bool) $request->user()?->canEditEmployeeIdentity(),
            'statusTabs' => ['在籍', '退職'],
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

    /**
     * @param  Builder<User>  $query
     */
    private function applyStatusFilter(Builder $query, string $status): void
    {
        if ($status === '在籍') {
            $query->where(function (Builder $statusQuery) {
                $statusQuery
                    ->whereHas('hrDetail', fn (Builder $hrDetailQuery) => $hrDetailQuery->where('employment_status', '在籍'))
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

<?php

namespace App\Services;

use App\Models\EmployeeHrDetail;
use App\Models\User;
use App\Support\CompanyPhone;
use App\Support\EmployeeHrDetailAccess;
use Illuminate\Database\Eloquent\Builder;

class EmployeeItDeviceListService
{
    /** @var list<string> */
    public const STATUS_TABS = [
        '在籍',
        '退職',
    ];

    /**
     * @return list<array{
     *     employee_id: string,
     *     name: string,
     *     email: string,
     *     location: string,
     *     department: string,
     *     employment_status: string,
     *     employment_type: string,
     *     phone: string,
     *     has_pc: bool|null,
     *     has_mobile: bool|null,
     *     detail_url: string
     * }>
     */
    public function rows(User $viewer, string $status = '在籍', string $keyword = ''): array
    {
        $keyword = trim($keyword);
        $status = trim($status);

        if ($status !== '' && ! in_array($status, self::STATUS_TABS, true)) {
            $status = '在籍';
        }

        $query = User::query()
            ->with(['profile', 'hrDetail', 'affiliationHistories'])
            ->orderBy('last_name')
            ->orderBy('first_name');

        if ($status !== '') {
            $this->applyStatusFilter($query, $status);
        }

        if ($keyword !== '') {
            $like = '%'.$keyword.'%';
            $query->where(function (Builder $keywordQuery) use ($like) {
                $keywordQuery
                    ->where('name', 'like', $like)
                    ->orWhere('email', 'like', $like)
                    ->orWhere('employee_id', 'like', $like)
                    ->orWhere('last_name', 'like', $like)
                    ->orWhere('first_name', 'like', $like)
                    ->orWhereHas('profile', function (Builder $profileQuery) use ($like) {
                        $profileQuery
                            ->where('english_name', 'like', $like)
                            ->orWhere('name_kana', 'like', $like);
                    })
                    ->orWhereHas('affiliationHistories', function (Builder $affiliationQuery) use ($like) {
                        $affiliationQuery
                            ->currentlyActive()
                            ->where(function (Builder $activeAffiliationQuery) use ($like) {
                                $activeAffiliationQuery
                                    ->where('department', 'like', $like)
                                    ->orWhere('section', 'like', $like)
                                    ->orWhere('location', 'like', $like);
                            });
                    });
            });
        }

        return $query
            ->get()
            ->map(fn (User $employee) => $this->row($viewer, $employee))
            ->all();
    }

    /**
     * @return array{
     *     employee_id: string,
     *     name: string,
     *     email: string,
     *     location: string,
     *     department: string,
     *     employment_status: string,
     *     employment_type: string,
     *     phone: string,
     *     has_pc: bool|null,
     *     has_mobile: bool|null,
     *     detail_url: string
     * }
     */
    private function row(User $viewer, User $employee): array
    {
        $affiliation = $employee->currentAffiliation();
        $detail = $employee->hrDetail;
        $canViewIt = EmployeeHrDetailAccess::canViewIt($viewer, $employee);

        return [
            'employee_id' => $employee->employee_id ?? '—',
            'name' => $employee->displayName(),
            'email' => $employee->email ?? '—',
            'location' => $this->displayLocation($affiliation?->location, $detail?->jurisdiction),
            'department' => trim((string) ($affiliation?->department ?? $detail?->department_primary ?? '')) ?: '—',
            'employment_status' => $employee->displayEmploymentStatus(),
            'employment_type' => $employee->displayEmploymentType(),
            'phone' => $canViewIt ? $this->displayPhone($detail) : '—',
            'has_pc' => $canViewIt ? $detail?->has_pc : null,
            'has_mobile' => $canViewIt ? $detail?->has_mobile : null,
            'detail_url' => route('it-devices.show', $employee),
        ];
    }

    private function displayLocation(?string $affiliationLocation, ?string $jurisdiction): string
    {
        $location = trim((string) ($affiliationLocation ?? ''));

        if ($location !== '') {
            return $location;
        }

        $jurisdiction = trim((string) ($jurisdiction ?? ''));

        return $jurisdiction !== '' ? $jurisdiction : '—';
    }

    private function displayPhone(?EmployeeHrDetail $detail): string
    {
        if ($detail === null) {
            return '—';
        }

        return CompanyPhone::display($detail->company_phone)
            ?? CompanyPhone::display($detail->phone)
            ?? '—';
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
                        ->where('enrollment_status', \App\Models\AffiliationHistory::STATUS_RESIGNED));
            });
        }
    }
}

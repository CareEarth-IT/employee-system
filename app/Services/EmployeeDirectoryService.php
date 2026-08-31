<?php

namespace App\Services;

use App\Models\User;
use App\Support\EmploymentStatus;
use Illuminate\Database\Eloquent\Builder;

class EmployeeDirectoryService
{
    public const DEFAULT_STATUS = '在籍';

    public const MAX_RESULTS = 200;

    /**
     * @return list<array{
     *     id: int,
     *     employee_id: string|null,
     *     name: string,
     *     email: string,
     *     employment_status: string,
     *     company: string,
     *     department: string,
     *     section: string,
     *     position: string
     * }>
     */
    public function search(
        string $keyword = '',
        string $status = self::DEFAULT_STATUS,
        ?string $department = null,
    ): array {
        $keyword = trim($keyword);
        $status = trim($status);
        $department = $department !== null ? trim($department) : null;

        if ($status !== '' && ! in_array($status, User::EMPLOYMENT_STATUS_OPTIONS, true)) {
            $status = self::DEFAULT_STATUS;
        }

        $query = User::query()
            ->with(['profile', 'hrDetail', 'affiliationHistories'])
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->limit(self::MAX_RESULTS);

        if ($status !== '') {
            EmploymentStatus::applyUserStatusFilter($query, $status);
        }

        if ($department !== null && $department !== '') {
            if (! $this->shouldSkipDepartmentFilterForKeyword($keyword)) {
                $query->whereHas('affiliationHistories', function (Builder $affiliationQuery) use ($department) {
                    $affiliationQuery
                        ->currentlyActive()
                        ->where('department', 'like', '%'.$department.'%');
                });
            }
        }

        if ($keyword !== '') {
            $like = '%'.$keyword.'%';
            $query->where(function (Builder $keywordQuery) use ($like) {
                $keywordQuery
                    ->where('name', 'like', $like)
                    ->orWhere('email', 'like', $like)
                    ->orWhere('last_name', 'like', $like)
                    ->orWhere('first_name', 'like', $like)
                    ->orWhere('employee_id', 'like', $like)
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
                                    ->orWhere('position', 'like', $like)
                                    ->orWhere('company', 'like', $like);
                            });
                    });
            });
        }

        return $query
            ->get()
            ->map(fn (User $user) => $this->toDirectoryEntry($user))
            ->values()
            ->all();
    }

    /**
     * @return array{
     *     id: int,
     *     employee_id: string|null,
     *     name: string,
     *     email: string,
     *     employment_status: string,
     *     company: string,
     *     department: string,
     *     section: string,
     *     position: string
     * }
     */
    private function toDirectoryEntry(User $user): array
    {
        $affiliation = $user->currentAffiliation();

        return [
            'id' => (int) $user->id,
            'employee_id' => $user->employee_id !== null && $user->employee_id !== ''
                ? (string) $user->employee_id
                : null,
            'name' => $this->formDisplayName($user),
            'email' => (string) $user->email,
            'employment_status' => $user->displayEmploymentStatus(),
            'company' => (string) ($affiliation?->company ?? $user->displayCompany()),
            'department' => (string) ($affiliation?->department ?? ''),
            'section' => (string) ($affiliation?->section ?? ''),
            'position' => (string) ($affiliation?->position ?? ''),
        ];
    }

    private function formDisplayName(User $user): string
    {
        $fromParts = trim("{$user->last_name} {$user->first_name}");

        if ($fromParts !== '' && $user->last_name !== '未設定') {
            return $fromParts;
        }

        $name = trim((string) $user->name);

        return $name !== '' ? $name : $user->displayName();
    }

    /**
     * 不動産 SSO handoff 等、メール／社員IDで個人を特定する参照では
     * ポータル側の default_department（不動産）フィルタをかけない。
     */
    private function shouldSkipDepartmentFilterForKeyword(string $keyword): bool
    {
        if ($keyword === '') {
            return false;
        }

        if (str_contains($keyword, '@')) {
            return true;
        }

        return (bool) preg_match('/^[A-Za-z0-9_-]+$/', $keyword);
    }
}

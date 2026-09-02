<?php

namespace App\Services;

use App\Models\AffiliationHistory;
use App\Models\EmployeeHrDetail;
use App\Models\EmployeeProfile;
use App\Models\User;
use App\Support\EmployeeIdRules;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EmployeeRegistryService
{
    public function __construct(
        private DriveStaffSyncService $driveStaffSync,
    ) {}

    /**
     * @param  array{
     *     name: string,
     *     email: string,
     *     password: string,
     *     employee_id: string,
     *     department: string,
     *     company: string,
     *     section?: string|null,
     *     team?: string|null,
     *     location: string,
     *     employment_type: string,
     *     employment_status?: string,
     *     name_kana?: string|null,
     *     english_name?: string|null,
     *     abbreviated_name?: string|null,
     *     birth_date?: string|null,
     *     joined_at?: string|null,
     *     nationality?: string|null,
     *     gender?: string|null,
     *     remarks?: string|null,
     * }  $data
     */
    public function create(array $data): User
    {
        return DB::transaction(function () use ($data): User {
            $this->assertEmployeeIdIsAvailable($data['employee_id']);

            [$lastName, $firstName, $displayName] = $this->splitName($data['name']);
            $affiliationOrg = \App\Support\RegistryDepartmentOptions::resolveAffiliation(
                $data['department'],
                $data['section'] ?? null,
            );
            [$section, $team] = array_slice(
                \App\Support\RegistryOrgAssignment::resolveForStorage(
                    $data['department'],
                    $data['location'],
                    $data['section'] ?? null,
                    $data['team'] ?? null,
                ),
                0,
                2,
            );
            $affiliationSection = \App\Support\RegistryOrgAssignment::combine($section, $team);
            $hrOrgPrimary = \App\Support\RegistryOrgAssignment::hrDetailPrimaryFromAffiliation(
                $affiliationOrg['department'],
                $data['location'],
                $affiliationSection,
            );

            $user = User::create([
                'employee_id' => $data['employee_id'],
                'email' => $data['email'],
                'password' => $data['password'],
                'must_change_password' => true,
                'role' => User::ROLE_EMPLOYEE,
                'last_name' => $lastName,
                'first_name' => $firstName,
                'name' => $displayName,
                'import_locked' => true,
            ]);

            EmployeeProfile::create([
                'user_id' => $user->id,
                ...$this->profileAttributes($data, $displayName),
            ]);

            EmployeeHrDetail::create([
                'user_id' => $user->id,
                'employment_type' => $data['employment_type'],
                'employment_status' => $data['employment_status'] ?? '在籍',
                'department_primary' => $hrOrgPrimary['department_primary'],
                'section_primary' => $hrOrgPrimary['section_primary'],
                ...$this->hrDetailAttributes($data),
            ]);

            $joinedAt = $this->joinedAt($data);

            $affiliation = AffiliationHistory::create([
                'user_id' => $user->id,
                'start_date' => $joinedAt,
                'enrollment_status' => AffiliationHistory::STATUS_ENROLLED,
                'company' => $data['company'],
                'location' => $data['location'],
                'department' => $affiliationOrg['department'],
                'section' => $affiliationSection,
                'position' => null,
                'import_locked' => true,
            ]);

            $user->closeOtherEnrolledAffiliations($affiliation);
            $user->syncRoleFromAffiliation();
            $this->driveStaffSync->syncUser($user->fresh());

            return $user->fresh(['profile', 'hrDetail', 'affiliationHistories']);
        });
    }

    /**
     * @param  array{
     *     name: string,
     *     email: string,
     *     password?: string|null,
     *     employee_id: string,
     *     department: string,
     *     company: string,
     *     section?: string|null,
     *     team?: string|null,
     *     location: string,
     *     employment_type: string,
     *     employment_status?: string,
     *     name_kana?: string|null,
     *     english_name?: string|null,
     *     abbreviated_name?: string|null,
     *     birth_date?: string|null,
     *     joined_at?: string|null,
     *     nationality?: string|null,
     *     gender?: string|null,
     *     remarks?: string|null,
     * }  $data
     */
    public function update(User $user, array $data): User
    {
        return DB::transaction(function () use ($user, $data): User {
            $this->assertEmployeeIdIsAvailable($data['employee_id'], $user->id);

            [$lastName, $firstName, $displayName] = $this->splitName($data['name']);
            $affiliationOrg = \App\Support\RegistryDepartmentOptions::resolveAffiliation(
                $data['department'],
                $data['section'] ?? null,
            );
            [$section, $team] = array_slice(
                \App\Support\RegistryOrgAssignment::resolveForStorage(
                    $data['department'],
                    $data['location'],
                    $data['section'] ?? null,
                    $data['team'] ?? null,
                ),
                0,
                2,
            );
            $affiliationSection = \App\Support\RegistryOrgAssignment::combine($section, $team);
            $hrOrgPrimary = \App\Support\RegistryOrgAssignment::hrDetailPrimaryFromAffiliation(
                $affiliationOrg['department'],
                $data['location'],
                $affiliationSection,
            );

            $user->fill([
                'employee_id' => $data['employee_id'],
                'email' => $data['email'],
                'last_name' => $lastName,
                'first_name' => $firstName,
                'name' => $displayName,
                'import_locked' => true,
            ]);

            if (! empty($data['password'])) {
                $user->password = $data['password'];
                $user->must_change_password = true;
            }

            $user->save();

            EmployeeProfile::updateOrCreate(
                ['user_id' => $user->id],
                [
                    ...$this->profileAttributes($data, $displayName, $user->profile),
                    'import_locked' => true,
                ],
            );

            EmployeeHrDetail::updateOrCreate(
                ['user_id' => $user->id],
                [
                    'employment_type' => $data['employment_type'],
                    'employment_status' => $data['employment_status'] ?? $user->hrDetail?->employment_status ?: '在籍',
                    'department_primary' => $hrOrgPrimary['department_primary'],
                    'section_primary' => $hrOrgPrimary['section_primary'],
                    ...$this->hrDetailAttributes($data, $user->hrDetail),
                ],
            );

            $joinedAt = $this->joinedAt($data, $user->profile?->joined_at?->format('Y-m-d'));

            $affiliationData = [
                'enrollment_status' => AffiliationHistory::STATUS_ENROLLED,
                'company' => $data['company'],
                'location' => $data['location'],
                'department' => $affiliationOrg['department'],
                'section' => $affiliationSection,
                'import_locked' => true,
                'start_date' => $joinedAt,
            ];

            $currentAffiliation = $user->currentAffiliation();

            if ($currentAffiliation) {
                $currentAffiliation->update($affiliationData);
                $user->closeOtherEnrolledAffiliations($currentAffiliation);
            } else {
                $affiliation = AffiliationHistory::create([
                    'user_id' => $user->id,
                    ...$affiliationData,
                ]);
                $user->closeOtherEnrolledAffiliations($affiliation);
            }

            $user->syncRoleFromAffiliation();
            $this->driveStaffSync->syncUser($user->fresh());

            return $user->fresh(['profile', 'hrDetail', 'affiliationHistories']);
        });
    }

    /**
     * @return array{0: ?string, 1: ?string, 2: string}
     */
    public function splitName(string $name): array
    {
        $name = trim(preg_replace('/\s+/u', ' ', $name) ?? '');

        if ($name === '') {
            return [null, null, ''];
        }

        $parts = explode(' ', $name, 2);
        $lastName = $parts[0];
        $firstName = $parts[1] ?? null;

        return [$lastName, $firstName, trim($lastName.' '.($firstName ?? ''))];
    }

    public function displayName(User $user): string
    {
        return trim($user->name ?: $user->displayName());
    }

    private function assertEmployeeIdIsAvailable(string $employeeId, ?int $ignoreUserId = null): void
    {
        if (! EmployeeIdRules::isValid($employeeId)) {
            return;
        }

        $query = User::query()->where('employee_id', $employeeId);

        if ($ignoreUserId !== null) {
            $query->whereKeyNot($ignoreUserId);
        }

        if ($query->exists()) {
            throw ValidationException::withMessages([
                'employee_id' => 'この社員IDは既に使用されています。',
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function profileAttributes(array $data, string $displayName, ?EmployeeProfile $existing = null): array
    {
        $attributes = [
            'name_kana' => str_replace(' ', '', $displayName),
            'joined_at' => $existing?->joined_at?->format('Y-m-d') ?? now()->toDateString(),
            'import_locked' => true,
        ];

        if (! empty($data['name_kana'])) {
            $attributes['name_kana'] = $data['name_kana'];
        }

        foreach (['english_name', 'nationality'] as $field) {
            if (array_key_exists($field, $data)) {
                $attributes[$field] = $data[$field];
            }
        }

        if (! empty($data['joined_at'])) {
            $attributes['joined_at'] = $data['joined_at'];
        }

        return $attributes;
    }

    /**
     * @return array<string, mixed>
     */
    private function hrDetailAttributes(array $data, ?EmployeeHrDetail $existing = null): array
    {
        $attributes = [];

        if (! empty($data['name_kana'])) {
            $attributes['name_kana_fullwidth'] = $data['name_kana'];
        }

        foreach (['gender', 'remarks', 'birth_date'] as $field) {
            if (array_key_exists($field, $data)) {
                $attributes[$field] = $data[$field];
            }
        }

        return $attributes;
    }

    private function joinedAt(array $data, ?string $fallback = null): string
    {
        if (! empty($data['joined_at'])) {
            return $data['joined_at'];
        }

        return $fallback ?? now()->toDateString();
    }
}

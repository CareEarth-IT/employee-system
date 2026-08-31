<?php

namespace App\Services;

use App\Models\AffiliationHistory;
use App\Models\EmployeeHrDetail;
use App\Models\EmployeeProfile;
use App\Models\User;
use Illuminate\Support\Facades\DB;

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
     *     location: string,
     *     employment_type: string
     * }  $data
     */
    public function create(array $data): User
    {
        return DB::transaction(function () use ($data): User {
            [$lastName, $firstName, $displayName] = $this->splitName($data['name']);

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
                'name_kana' => str_replace(' ', '', $displayName),
                'joined_at' => now()->toDateString(),
                'import_locked' => true,
            ]);

            EmployeeHrDetail::create([
                'user_id' => $user->id,
                'employment_type' => $data['employment_type'],
                'employment_status' => '在籍',
            ]);

            $affiliation = AffiliationHistory::create([
                'user_id' => $user->id,
                'start_date' => now()->toDateString(),
                'enrollment_status' => AffiliationHistory::STATUS_ENROLLED,
                'company' => User::COMPANY_NAMES[0] ?? 'CareEarth',
                'location' => $data['location'],
                'department' => $data['department'],
                'position' => $data['employment_type'],
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
     *     location: string,
     *     employment_type: string
     * }  $data
     */
    public function update(User $user, array $data): User
    {
        return DB::transaction(function () use ($user, $data): User {
            [$lastName, $firstName, $displayName] = $this->splitName($data['name']);

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
                    'name_kana' => str_replace(' ', '', $displayName),
                    'import_locked' => true,
                ],
            );

            EmployeeHrDetail::updateOrCreate(
                ['user_id' => $user->id],
                [
                    'employment_type' => $data['employment_type'],
                    'employment_status' => $user->hrDetail?->employment_status ?: '在籍',
                ],
            );

            $affiliationData = [
                'enrollment_status' => AffiliationHistory::STATUS_ENROLLED,
                'company' => $user->currentAffiliation()?->company ?: (User::COMPANY_NAMES[0] ?? 'CareEarth'),
                'location' => $data['location'],
                'department' => $data['department'],
                'position' => $data['employment_type'],
                'import_locked' => true,
            ];

            $currentAffiliation = $user->currentAffiliation();

            if ($currentAffiliation) {
                $currentAffiliation->update($affiliationData);
                $user->closeOtherEnrolledAffiliations($currentAffiliation);
            } else {
                $affiliation = AffiliationHistory::create([
                    'user_id' => $user->id,
                    'start_date' => $user->profile?->joined_at?->format('Y-m-d') ?? now()->toDateString(),
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
}

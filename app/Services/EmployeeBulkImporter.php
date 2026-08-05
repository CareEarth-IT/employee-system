<?php

namespace App\Services;

use App\Models\AffiliationHistory;
use App\Models\EmployeeHrDetail;
use App\Models\EmployeeProfile;
use App\Models\User;
use App\Support\EmployeeIdRules;

class EmployeeBulkImporter
{
    /** @var array<string, string> */
    private array $claimedEmployeeIds = [];

    /** @var list<string> */
    private const SKIPPED_EMAILS = [
        'admin@careearth.info',
        'external_sharing@careearth.info',
    ];

    /**
     * @param  array{
     *     dry_run?: bool,
     *     sync_users?: bool,
     *     force_users?: bool,
     *     sync_profiles?: bool,
     *     force_profiles?: bool,
     *     sync_affiliations?: bool,
     *     force_affiliations?: bool
     * }  $options
     */
    public function import(string $path, array $options = []): EmployeeBulkImportResult
    {
        if (! is_readable($path)) {
            throw new \InvalidArgumentException("CSVが見つかりません: {$path}");
        }

        $dryRun = (bool) ($options['dry_run'] ?? false);
        $this->claimedEmployeeIds = [];

        $rows = $this->readCsv($path);
        $results = [];
        $errors = [];
        $created = 0;
        $skippedExisting = 0;
        $updated = 0;

        foreach ($rows as $index => $row) {
            $csvLine = $index + 2;

            try {
                $payload = $this->normalizeRow($row);

                if ($this->shouldSkipEmail($payload['email'])) {
                    continue;
                }

                $this->validateRow($payload);
                $this->validateEmployeeId(
                    $payload['email'],
                    $payload['employee_id'],
                    $csvLine,
                );

                if ($dryRun) {
                    $userExists = User::query()->where('email', $payload['email'])->exists();
                    $action = $userExists
                        ? ($this->hasExplicitSyncOption($options) ? '更新予定' : '既存（変更なし）')
                        : '新規登録';
                    $results[] = $this->resultRow($payload, $action);

                    continue;
                }

                $user = $this->importRow($payload, $options);
                $action = $user->wasRecentlyCreated
                    ? '新規登録'
                    : ($this->hasExplicitSyncOption($options) ? '更新' : '既存（変更なし）');

                if ($user->wasRecentlyCreated) {
                    $created++;
                } elseif ($this->hasExplicitSyncOption($options)) {
                    $updated++;
                } else {
                    $skippedExisting++;
                }

                $results[] = [
                    $payload['email'],
                    $user->displayName(),
                    $user->employee_id ?: '—',
                    $payload['department'],
                    $payload['section'] ?: '—',
                    $payload['position'] ?: '—',
                    $action,
                ];
            } catch (\InvalidArgumentException $e) {
                $errors[] = "行 {$csvLine}: {$e->getMessage()}";
            }
        }

        return new EmployeeBulkImportResult(
            rows: $results,
            errors: $errors,
            created: $created,
            skippedExisting: $skippedExisting,
            updated: $updated,
            dryRun: $dryRun,
        );
    }

    /**
     * @return list<array<string, string>>
     */
    private function readCsv(string $path): array
    {
        $contents = file_get_contents($path);

        if ($contents === false) {
            throw new \RuntimeException("CSVを開けません: {$path}");
        }

        $contents = $this->decodeCsvContents($contents);

        $handle = fopen('php://memory', 'rb+');

        if ($handle === false) {
            throw new \RuntimeException("CSVを読み込めません: {$path}");
        }

        fwrite($handle, $contents);
        rewind($handle);

        $header = fgetcsv($handle);

        if ($header === false) {
            fclose($handle);

            return [];
        }

        $header = array_map(fn ($cell) => $this->normalizeHeader((string) $cell), $header);
        $rows = [];

        while (($data = fgetcsv($handle)) !== false) {
            if ($this->isEmptyRow($data)) {
                continue;
            }

            $row = [];
            foreach ($header as $index => $column) {
                $row[$column] = trim((string) ($data[$index] ?? ''));
            }

            $rows[] = $row;
        }

        fclose($handle);

        return $rows;
    }

    private function decodeCsvContents(string $contents): string
    {
        if (str_starts_with($contents, "\xEF\xBB\xBF")) {
            return substr($contents, 3);
        }

        if (mb_check_encoding($contents, 'UTF-8')) {
            return $contents;
        }

        $converted = mb_convert_encoding($contents, 'UTF-8', 'SJIS-win');

        return $converted !== false ? $converted : $contents;
    }

    private function normalizeHeader(string $header): string
    {
        $header = ltrim($header, "\xEF\xBB\xBF");

        return match ($header) {
            'Email Address [Required]', 'email address [required]' => 'email',
            'First Name [Required]', 'first name [required]' => '名',
            'Last Name [Required]', 'last name [required]' => '姓',
            'Employee ID' => '社員番号',
            'Employee Type' => '雇用区分',
            'Employee Title' => '役職',
            'Department' => 'Department',
            'Org Unit Path [Required]', 'org unit path [required]' => 'Org Unit Path',
            'Building ID' => 'Building ID',
            'name' => '氏名',
            'last_name', 'lastname' => '姓',
            'first_name', 'firstname' => '名',
            'employee_id', 'employeeId' => '社員番号',
            'department' => '部',
            'section' => '課',
            'position' => '役職',
            'employment_type' => '雇用区分',
            '雇用形態' => '雇用区分',
            'location' => '拠点',
            'company' => '会社',
            'password', 'パスワード' => 'パスワード',
            'phone', '電話番号', 'tel' => '電話番号',
            default => $header,
        };
    }

    /**
     * @param  list<string|null>  $row
     */
    private function isEmptyRow(array $row): bool
    {
        foreach ($row as $cell) {
            if (trim((string) $cell) !== '') {
                return false;
            }
        }

        return true;
    }

    private function shouldSkipEmail(string $email): bool
    {
        return in_array(strtolower(trim($email)), self::SKIPPED_EMAILS, true);
    }

    /**
     * @param  array<string, string>  $row
     * @return array<string, string>
     */
    private function normalizeRow(array $row): array
    {
        if (isset($row['Department']) || isset($row['Org Unit Path'])) {
            return $this->normalizeGoogleRow($row);
        }

        $name = $row['氏名'] ?? '';
        $lastName = $row['姓'] ?? '';
        $firstName = $row['名'] ?? '';

        if ($name === '' && ($lastName !== '' || $firstName !== '')) {
            $name = trim("{$lastName} {$firstName}");
        }

        if ($lastName === '' && $firstName === '' && $name !== '') {
            if (str_contains($name, ' ')) {
                [$lastName, $firstName] = array_pad(explode(' ', $name, 2), 2, '');
            } else {
                $lastName = $name;
            }
        }

        [$department, $section] = $this->splitDepartment(
            trim($row['部'] ?? ''),
            trim($row['課'] ?? ''),
        );

        return [
            'email' => strtolower(trim($row['email'] ?? '')),
            'name' => $name,
            'last_name' => $lastName,
            'first_name' => $firstName,
            'employee_id' => trim($row['社員番号'] ?? ''),
            'department' => $department !== '' ? $department : '未設定',
            'section' => $section,
            'position' => $this->resolvePosition(trim($row['役職'] ?? '')),
            'employment_type' => $this->resolveEmploymentType(trim($row['雇用区分'] ?? '')),
            'location' => trim($row['拠点'] ?? '') ?: '大阪',
            'company' => trim($row['会社'] ?? '') ?: 'CareEarth',
            'password' => trim($row['パスワード'] ?? ''),
            'phone' => trim($row['電話番号'] ?? ''),
        ];
    }

    /**
     * @param  array<string, string>  $row
     * @return array<string, string>
     */
    private function normalizeGoogleRow(array $row): array
    {
        $lastName = trim($row['姓'] ?? '');
        $firstName = trim($row['名'] ?? '');
        $name = trim("{$lastName} {$firstName}");
        $departmentRaw = trim($row['Department'] ?? '');
        $orgPath = trim($row['Org Unit Path'] ?? '');

        if ($departmentRaw === '') {
            $departmentRaw = $this->departmentFromOrgPath($orgPath);
        }

        [$department, $section] = $this->splitDepartment($departmentRaw, '');

        return [
            'email' => strtolower(trim($row['email'] ?? '')),
            'name' => $name,
            'last_name' => $lastName,
            'first_name' => $firstName,
            'employee_id' => trim($row['社員番号'] ?? ''),
            'department' => $department ?: '未設定',
            'section' => $section,
            'position' => $this->resolvePosition(trim($row['役職'] ?? '')),
            'employment_type' => $this->resolveEmploymentType(trim($row['雇用区分'] ?? '')),
            'location' => $this->resolveLocation($orgPath, trim($row['Building ID'] ?? '')),
            'company' => 'CareEarth',
            'password' => trim($row['パスワード'] ?? ''),
            'phone' => trim($row['電話番号'] ?? ''),
        ];
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function splitDepartment(string $department, string $section): array
    {
        if ($section !== '') {
            return [$department, $section];
        }

        if ($department === '') {
            return ['', ''];
        }

        if (str_contains($department, ' ')) {
            [$dept, $sect] = array_pad(explode(' ', $department, 2), 2, '');

            return [trim($dept), trim($sect)];
        }

        if (preg_match('/^(.+部)(.+課.*)$/u', $department, $matches)) {
            return [trim($matches[1]), trim($matches[2])];
        }

        return [$department, ''];
    }

    private function departmentFromOrgPath(string $path): string
    {
        $segments = array_values(array_filter(explode('/', $path)));

        if ($segments === []) {
            return '';
        }

        return (string) end($segments);
    }

    private function resolveLocation(string $orgPath, string $buildingId): string
    {
        foreach ([$buildingId, $orgPath] as $source) {
            if ($source === '') {
                continue;
            }

            if (str_contains($source, '福岡')) {
                return '福岡';
            }

            if (str_contains($source, '東京') || str_contains(strtolower($source), 'tokyo')) {
                return '東京';
            }

            if (str_contains($source, '名古屋')) {
                return '名古屋';
            }

            if (str_contains($source, 'ベトナム')) {
                return 'ベトナム';
            }

            if (str_contains($source, '大阪') || str_contains(strtolower($source), 'osaka')) {
                return '大阪';
            }
        }

        return '大阪';
    }

    private function resolvePosition(string $employeeTitle): string
    {
        return trim($employeeTitle);
    }

    private function resolveEmploymentType(string $employeeType): string
    {
        $employeeType = trim($employeeType);

        return $employeeType !== '' ? $employeeType : '正社員';
    }

    private function validateEmployeeId(string $email, string $requestedId, int $csvLine): void
    {
        if ($requestedId === '') {
            return;
        }

        if (! EmployeeIdRules::isValid($requestedId)) {
            throw new \InvalidArgumentException(
                "社員番号 {$requestedId} は5桁の数字で入力してください（行 {$csvLine}）。"
            );
        }

        $claimedBy = $this->claimedEmployeeIds[$requestedId] ?? null;
        if ($claimedBy !== null && $claimedBy !== $email) {
            throw new \InvalidArgumentException(
                "社員番号 {$requestedId} がCSV内で重複しています（行 {$csvLine}、{$claimedBy} と {$email}）。"
            );
        }

        $existingUser = User::query()
            ->where('employee_id', $requestedId)
            ->where('email', '!=', $email)
            ->value('email');

        if ($existingUser) {
            throw new \InvalidArgumentException(
                "社員番号 {$requestedId} は既に {$existingUser} に登録されています（行 {$csvLine}）。"
            );
        }

        $this->claimedEmployeeIds[$requestedId] = $email;
    }

    /**
     * @param  array<string, string>  $payload
     */
    private function validateRow(array $payload): void
    {
        if ($payload['email'] === '' || ! filter_var($payload['email'], FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('メールアドレスが不正です。');
        }

        if ($payload['name'] === '' && $payload['last_name'] === '') {
            throw new \InvalidArgumentException('氏名（または姓・名）が未入力です。');
        }
    }

    /**
     * @param  array<string, string>  $payload
     * @return list<string>
     */
    private function resultRow(array $payload, string $action): array
    {
        return [
            $payload['email'],
            $payload['name'] ?: trim("{$payload['last_name']} {$payload['first_name']}"),
            $payload['employee_id'] ?: '—',
            $payload['department'],
            $payload['section'] ?: '—',
            $payload['position'] ?: '—',
            $action,
        ];
    }

    /**
     * @param  array<string, bool>  $options
     */
    private function hasExplicitSyncOption(array $options): bool
    {
        return ($options['sync_users'] ?? false)
            || ($options['force_users'] ?? false)
            || ($options['sync_profiles'] ?? false)
            || ($options['force_profiles'] ?? false)
            || ($options['sync_affiliations'] ?? false)
            || ($options['force_affiliations'] ?? false);
    }

    /**
     * @param  array<string, string>  $payload
     * @param  array<string, bool>  $options
     */
    private function importRow(array $payload, array $options): User
    {
        $user = User::firstOrNew(['email' => $payload['email']]);

        if ($user->exists) {
            return $this->updateExistingUser($user, $payload, $options);
        }

        return $this->createNewUser($user, $payload, $options);
    }

    /**
     * @param  array<string, string>  $payload
     * @param  array<string, bool>  $options
     */
    private function createNewUser(User $user, array $payload, array $options): User
    {
        $displayName = $payload['name'] ?: trim("{$payload['last_name']} {$payload['first_name']}");
        $employeeId = $payload['employee_id'];
        $password = trim((string) ($payload['password'] ?? ''));

        if ($password !== '') {
            $user->password = $password;
            $user->must_change_password = false;
        } else {
            // New hires: fixed initial password; must change on first login.
            $user->password = 'password';
            $user->must_change_password = true;
        }

        $user->role = User::ROLE_EMPLOYEE;
        $user->fill([
            'employee_id' => $employeeId !== '' ? $employeeId : null,
            'last_name' => $payload['last_name'] ?: null,
            'first_name' => $payload['first_name'] ?: null,
            'name' => $displayName,
        ]);
        $user->save();

        EmployeeProfile::updateOrCreate(
            ['user_id' => $user->id],
            [
                'name_kana' => str_replace(' ', '', $displayName),
                'nationality' => '日本',
                'joined_at' => now()->toDateString(),
            ],
        );

        $this->syncHrDetailFromImport($user, $payload);
        $this->syncAffiliation($user, $this->affiliationPayload($payload), $options);

        return $user;
    }

    /**
     * @param  array<string, string>  $payload
     * @param  array<string, bool>  $options
     */
    private function updateExistingUser(User $user, array $payload, array $options): User
    {
        if (! $this->hasExplicitSyncOption($options)) {
            return $user;
        }

        if ($this->shouldUpdateUserFromImport($user, $options)) {
            $displayName = $payload['name'] ?: trim("{$payload['last_name']} {$payload['first_name']}");
            $employeeId = $payload['employee_id'];

            $user->fill([
                'employee_id' => $employeeId !== '' ? $employeeId : null,
                'last_name' => $payload['last_name'] ?: null,
                'first_name' => $payload['first_name'] ?: null,
                'name' => $displayName,
            ]);
            $user->save();
        }

        if ($this->shouldUpdateProfileFromImport($user, $options)) {
            $displayName = $payload['name'] ?: trim("{$payload['last_name']} {$payload['first_name']}");

            EmployeeProfile::updateOrCreate(
                ['user_id' => $user->id],
                [
                    'name_kana' => str_replace(' ', '', $displayName),
                    'nationality' => '日本',
                ],
            );
        }

        $this->syncHrDetailFromImport($user, $payload);
        $this->syncAffiliation($user, $this->affiliationPayload($payload), $options);

        return $user;
    }

    /**
     * @param  array<string, string>  $payload
     */
    private function syncHrDetailFromImport(User $user, array $payload): void
    {
        $updates = [];

        $employmentType = trim((string) ($payload['employment_type'] ?? ''));
        if ($employmentType !== '') {
            $updates['employment_type'] = $employmentType;
        }

        $department = trim((string) ($payload['department'] ?? ''));
        if ($department !== '' && $department !== '未設定') {
            $updates['department_primary'] = $department;
        }

        $companyPhone = \App\Support\CompanyPhone::normalize((string) ($payload['phone'] ?? ''));
        if ($companyPhone !== null) {
            $updates['company_phone'] = $companyPhone;
        }

        if ($updates === []) {
            return;
        }

        EmployeeHrDetail::query()->updateOrCreate(
            ['user_id' => $user->id],
            $updates,
        );
    }

    /**
     * @param  array<string, string>  $payload
     * @return array<string, mixed>
     */
    private function affiliationPayload(array $payload): array
    {
        return [
            'enrollment_status' => AffiliationHistory::STATUS_ENROLLED,
            'company' => $payload['company'],
            'location' => $payload['location'],
            'department' => $payload['department'],
            'section' => $payload['section'] ?: null,
            'position' => ($payload['position'] ?? '') !== '' ? $payload['position'] : null,
            'job_description' => null,
        ];
    }

    /**
     * @param  array<string, bool>  $options
     */
    private function shouldUpdateUserFromImport(User $user, array $options): bool
    {
        if ($options['force_users'] ?? false) {
            return true;
        }

        if ($options['sync_users'] ?? false) {
            return ! $user->import_locked;
        }

        return false;
    }

    /**
     * @param  array<string, bool>  $options
     */
    private function shouldUpdateProfileFromImport(User $user, array $options): bool
    {
        if ($options['force_profiles'] ?? false) {
            return true;
        }

        if (! ($options['sync_profiles'] ?? false)) {
            return false;
        }

        $profile = $user->profile;

        return $profile === null || ! $profile->import_locked;
    }

    /**
     * @param  array<string, mixed>  $affiliationData
     * @param  array<string, bool>  $options
     */
    private function syncAffiliation(User $user, array $affiliationData, array $options): void
    {
        $currentAffiliation = AffiliationHistory::query()
            ->where('user_id', $user->id)
            ->currentlyActive()
            ->orderByDesc('start_date')
            ->first();

        if ($currentAffiliation) {
            if ($this->shouldUpdateAffiliationFromImport($currentAffiliation, $options)) {
                $currentAffiliation->update($affiliationData);
                $user->closeOtherEnrolledAffiliations($currentAffiliation);
            }

            $user->syncRoleFromAffiliation();

            return;
        }

        $affiliation = AffiliationHistory::create([
            'user_id' => $user->id,
            'start_date' => $this->resolveAffiliationStartDate($user),
            ...$affiliationData,
        ]);
        $user->closeOtherEnrolledAffiliations($affiliation);
        $user->syncRoleFromAffiliation();
    }

    /**
     * @param  array<string, bool>  $options
     */
    private function shouldUpdateAffiliationFromImport(AffiliationHistory $affiliation, array $options): bool
    {
        if ($options['force_affiliations'] ?? false) {
            return true;
        }

        if ($options['sync_affiliations'] ?? false) {
            return ! $affiliation->import_locked;
        }

        return false;
    }

    private function resolveAffiliationStartDate(User $user): string
    {
        return $user->profile?->joined_at?->toDateString() ?? now()->toDateString();
    }
}

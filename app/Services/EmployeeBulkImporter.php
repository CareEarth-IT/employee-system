<?php

namespace App\Services;

use App\Models\AffiliationHistory;
use App\Models\EmployeeHrDetail;
use App\Models\EmployeeProfile;
use App\Models\User;
use App\Support\EmployeeIdRules;

class EmployeeBulkImporter
{
    /** @var list<string> */
    private const ENGLISH_REGISTRATION_HEADERS = [
        'employee_code',
        'name',
        'email',
        'password',
        'department',
        'position',
        'joined_date',
        'phone',
        'employment_type',
        'status',
    ];

    /** @var list<string> 人事システム出力形式（余分な列は取り込み時に無視） */
    private const HR_EXPORT_HEADERS = [
        '社員コード',
        '社員名',
        '社員略名',
        '社員名カナ',
        'E-MAIL',
        '所属1部門コード',
        '所属1部門名',
        '所属1役職コード',
        '所属1役職名',
        '所属2部門コード',
        '所属2部門名',
        '所属2役職コード',
        '所属2役職名',
        '所属3部門コード',
        '所属3部門名',
        '所属3役職コード',
        '所属3役職名',
        '権限（コード）',
        '権限（名称）',
        '在職区分（名称）',
    ];

    /** @return list<string> */
    public static function hrExportCsvHeaders(): array
    {
        return self::HR_EXPORT_HEADERS;
    }

    /** @return list<string> */
    public static function hrExportCsvSampleRow(): array
    {
        return [
            'A001',
            'テスト太郎',
            'テスト',
            'テストタロウ',
            'test-taro@example.com',
            'D01',
            '情報システム部',
            'P01',
            '一般',
            '',
            '',
            '',
            '',
            '',
            '',
            '',
            '',
            '',
            '',
            '在籍',
        ];
    }

    /** @var array<string, string> */
    private array $claimedEmployeeIds = [];

    private bool $usesRegistrationFormat = false;

    private string $registrationFormat = 'english';

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
        $this->usesRegistrationFormat = false;
        $this->registrationFormat = 'english';

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
                    $this->isRegistrationPayload($payload),
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

                $results[] = $this->resultRow($payload, $action, $user);
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

        $rawHeader = array_map(fn ($cell) => trim(ltrim((string) $cell, "\xEF\xBB\xBF")), $header);

        if ($this->isJapaneseRegistrationHeader($rawHeader)) {
            $this->usesRegistrationFormat = true;
            $this->registrationFormat = 'japanese';
            $header = array_map(fn ($cell) => $this->normalizeJapaneseRegistrationHeader((string) $cell), $rawHeader);
            $this->validateRegistrationCsvHeader($header);
        } elseif ($this->isEnglishRegistrationHeader($rawHeader)) {
            $this->usesRegistrationFormat = true;
            $this->registrationFormat = 'english';
            $header = array_map(fn ($cell) => $this->normalizeRegistrationHeader((string) $cell), $rawHeader);
            $this->validateRegistrationCsvHeader($header);
        } else {
            $header = array_map(fn ($cell) => $this->normalizeHeader((string) $cell), $rawHeader);
        }

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

    private function normalizeRegistrationHeader(string $header): string
    {
        $header = strtolower(trim(ltrim($header, "\xEF\xBB\xBF")));

        return match ($header) {
            'employee code', 'employee_code', 'employeecode' => 'employee_code',
            'joined date', 'joined_date', 'joineddate', 'hire_date', 'hire date' => 'joined_date',
            'employment type', 'employment_type', 'employmenttype' => 'employment_type',
            default => $header,
        };
    }

    /**
     * @param  list<string>  $header
     */
    private function isEnglishRegistrationHeader(array $header): bool
    {
        $normalized = array_map(
            fn (string $cell) => $this->normalizeRegistrationHeader($cell),
            $header,
        );

        return in_array('employee_code', $normalized, true)
            && in_array('email', $normalized, true);
    }

    /**
     * @param  list<string>  $header
     */
    private function isJapaneseRegistrationHeader(array $header): bool
    {
        $normalized = array_map(
            fn (string $cell) => $this->normalizeJapaneseRegistrationHeader($cell),
            $header,
        );

        return in_array('社員コード', $normalized, true)
            && in_array('E-MAIL', $normalized, true);
    }

    private function normalizeJapaneseRegistrationHeader(string $header): string
    {
        $header = mb_convert_kana(trim(ltrim($header, "\xEF\xBB\xBF")), 'as', 'UTF-8');
        $header = str_replace(['(', ')'], ['（', '）'], $header);
        $header = str_replace(['１', '２', '３'], ['1', '2', '3'], $header);
        $compact = str_replace([' ', '　', '-', '_'], '', $header);
        $upper = strtoupper($header);

        if ($compact === '社員コード' || str_starts_with($compact, '社員コ')) {
            return '社員コード';
        }

        if ($compact === '社員名カナ' || str_starts_with($compact, '社員名カ')) {
            return '社員名カナ';
        }

        if ($compact === '社員略名' || $compact === '社員略称名' || $compact === '社員略称' || str_starts_with($compact, '社員略')) {
            return '社員略名';
        }

        if ($compact === '社員名') {
            return '社員名';
        }

        if ($upper === 'E-MAIL' || $upper === 'EMAIL' || $header === 'メールアドレス') {
            return 'E-MAIL';
        }

        return match ($compact) {
            '所属1部門コード' => '所属1部門コード',
            '所属1部門名称', '所属1部門名', '所属1部門' => '所属1部門名',
            '所属1役職コード' => '所属1役職コード',
            '所属1役職名称', '所属1役職名', '所属1役職', '所属役職' => '所属1役職名',
            '所属2部門コード' => '所属2部門コード',
            '所属2部門名称', '所属2部門名', '所属2部門' => '所属2部門名',
            '所属2役職コード' => '所属2役職コード',
            '所属2役職名称', '所属2役職名', '所属2役職' => '所属2役職名',
            '所属3部門コード' => '所属3部門コード',
            '所属3部門名称', '所属3部門名', '所属3部門' => '所属3部門名',
            '所属3役職コード' => '所属3役職コード',
            '所属3役職名称', '所属3役職名', '所属3役職' => '所属3役職名',
            '権限（コード）', '権限コード' => '権限（コード）',
            '権限（名称）', '権限名称' => '権限（名称）',
            '在職区分（名称）', '在職区分名称' => '在職区分（名称）',
            default => $header,
        };
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
            '社員ID' => '社員ID',
            '氏名' => '氏名',
            'メールアドレス' => 'メールアドレス',
            '部署' => '部署',
            '生年月日' => '生年月日',
            '入社年月日' => '入社年月日',
            '備考' => '備考',
            'department' => '部',
            'section' => '課',
            'position' => '役職',
            'employment_type' => '雇用区分',
            '雇用形態' => '雇用形態',
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
        if ($this->usesRegistrationFormat) {
            return $this->normalizeRegistrationRow($row);
        }

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
            'format' => 'legacy',
            'email' => strtolower(trim($row['email'] ?? '')),
            'name' => $name,
            'last_name' => $lastName,
            'first_name' => $firstName,
            'employee_id' => trim($row['社員番号'] ?? ''),
            'department' => $department !== '' ? $department : '未設定',
            'section' => $section,
            'position' => $this->resolvePosition(trim($row['役職'] ?? '')),
            'employment_type' => $this->resolveEmploymentType(trim($row['雇用区分'] ?? $row['雇用形態'] ?? '')),
            'employment_status' => '',
            'location' => trim($row['拠点'] ?? '') ?: '大阪',
            'company' => trim($row['会社'] ?? '') ?: 'CareEarth',
            'password' => trim($row['パスワード'] ?? ''),
            'phone' => trim($row['電話番号'] ?? ''),
            'has_pc' => null,
            'has_mobile' => null,
        ];
    }

    /**
     * @param  array<string, string>  $row
     * @return array<string, mixed>
     */
    private function normalizeRegistrationRow(array $row): array
    {
        if ($this->registrationFormat === 'japanese') {
            return $this->normalizeJapaneseRegistrationRow($row);
        }

        $name = trim($row['name'] ?? '');
        $lastName = '';
        $firstName = '';

        if ($name !== '') {
            if (str_contains($name, ' ')) {
                [$lastName, $firstName] = array_pad(explode(' ', $name, 2), 2, '');
            } else {
                $lastName = $name;
            }
        }

        [$department, $section] = $this->splitDepartment(trim($row['department'] ?? ''), '');

        return [
            'format' => 'registration',
            'email' => strtolower(trim($row['email'] ?? '')),
            'name' => $name,
            'last_name' => $lastName,
            'first_name' => $firstName,
            'employee_id' => trim($row['employee_code'] ?? ''),
            'department' => $department,
            'section' => $section,
            'position' => $this->resolvePosition(trim($row['position'] ?? '')),
            'joined_at' => $this->parseOptionalDate((string) ($row['joined_date'] ?? ''), 'joined_date'),
            'phone' => trim($row['phone'] ?? ''),
            'password' => trim($row['password'] ?? ''),
            'employment_type' => trim($row['employment_type'] ?? ''),
            'employment_status' => $this->normalizeEmploymentStatus(trim($row['status'] ?? '')) ?: '在籍',
            'registration_variant' => 'english',
        ];
    }

    /**
     * @param  array<string, string>  $row
     * @return array<string, mixed>
     */
    private function normalizeJapaneseRegistrationRow(array $row): array
    {
        $name = trim($row['社員名'] ?? '');
        $lastName = '';
        $firstName = '';

        if ($name !== '') {
            if (str_contains($name, ' ')) {
                [$lastName, $firstName] = array_pad(explode(' ', $name, 2), 2, '');
            } else {
                $lastName = $name;
            }
        }

        [$department, $section] = $this->splitDepartment(trim($row['所属1部門名'] ?? ''), '');

        return [
            'format' => 'registration',
            'registration_variant' => 'japanese',
            'email' => strtolower(trim($row['E-MAIL'] ?? '')),
            'name' => $name,
            'last_name' => $lastName,
            'first_name' => $firstName,
            'employee_id' => trim($row['社員コード'] ?? ''),
            'abbreviated_name' => trim($row['社員略名'] ?? ''),
            'department' => $department,
            'section' => $section,
            'position' => $this->resolvePosition(trim($row['所属1役職名'] ?? '')),
            'employment_status' => '在籍',
            'password' => '',
            'employment_type' => '',
            'joined_at' => null,
            'phone' => '',
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
            'format' => 'legacy',
            'email' => strtolower(trim($row['email'] ?? '')),
            'name' => $name,
            'last_name' => $lastName,
            'first_name' => $firstName,
            'employee_id' => trim($row['社員番号'] ?? ''),
            'department' => $department ?: '未設定',
            'section' => $section,
            'position' => $this->resolvePosition(trim($row['役職'] ?? '')),
            'employment_type' => $this->resolveEmploymentType(trim($row['雇用区分'] ?? $row['雇用形態'] ?? '')),
            'employment_status' => '',
            'location' => $this->resolveLocation($orgPath, trim($row['Building ID'] ?? '')),
            'company' => 'CareEarth',
            'password' => trim($row['パスワード'] ?? ''),
            'phone' => trim($row['電話番号'] ?? ''),
            'has_pc' => null,
            'has_mobile' => null,
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

    private function validateEmployeeId(
        string $email,
        string $requestedId,
        int $csvLine,
        bool $flexibleFormat = false,
    ): void {
        if ($requestedId === '') {
            return;
        }

        $label = $flexibleFormat ? ($this->registrationFormat === 'japanese' ? '社員コード' : 'employee_code') : '社員番号';

        if ($flexibleFormat) {
            if (! preg_match('/^[A-Za-z0-9_-]{1,32}$/', $requestedId)) {
                throw new \InvalidArgumentException(
                    "{$label} {$requestedId} の形式が不正です（行 {$csvLine}）。英数字・ハイフン・アンダースコアが使えます。"
                );
            }
        } elseif (! EmployeeIdRules::isValid($requestedId)) {
            throw new \InvalidArgumentException(
                "{$label} {$requestedId} は5桁の数字で入力してください（行 {$csvLine}）。"
            );
        }

        $claimedBy = $this->claimedEmployeeIds[$requestedId] ?? null;
        if ($claimedBy !== null && $claimedBy !== $email) {
            throw new \InvalidArgumentException(
                "{$label} {$requestedId} がCSV内で重複しています（行 {$csvLine}、{$claimedBy} と {$email}）。"
            );
        }

        $existingUser = User::query()
            ->where('employee_id', $requestedId)
            ->where('email', '!=', $email)
            ->value('email');

        if ($existingUser) {
            throw new \InvalidArgumentException(
                "{$label} {$requestedId} は既に {$existingUser} に登録されています（行 {$csvLine}）。"
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
            $fieldLabel = ($payload['registration_variant'] ?? '') === 'japanese' ? '社員名' : 'name';
            throw new \InvalidArgumentException("{$fieldLabel} が未入力です。");
        }

        if ($this->isRegistrationPayload($payload)) {
            $this->validateRegistrationRow($payload);
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function validateRegistrationRow(array $payload): void
    {
        if (($payload['registration_variant'] ?? '') === 'english' && trim((string) ($payload['password'] ?? '')) === '') {
            throw new \InvalidArgumentException('password が未入力です。');
        }

        $employmentType = trim((string) ($payload['employment_type'] ?? ''));
        if ($employmentType !== '' && ! in_array($employmentType, User::EMPLOYMENT_TYPE_OPTIONS, true)) {
            throw new \InvalidArgumentException(
                "employment_type「{$employmentType}」が不正です（".implode(' / ', User::EMPLOYMENT_TYPE_OPTIONS).'）。'
            );
        }

        $employmentStatus = trim((string) ($payload['employment_status'] ?? ''));
        if ($employmentStatus !== '' && ! in_array($employmentStatus, User::EMPLOYMENT_STATUS_OPTIONS, true)) {
            throw new \InvalidArgumentException(
                "在職区分「{$employmentStatus}」が不正です（在職/在籍、退職、辞退 など）。"
            );
        }
    }

    /**
     * @param  list<string>  $header
     */
    private function validateRegistrationCsvHeader(array $header): void
    {
        if ($this->registrationFormat === 'japanese') {
            $missing = $this->missingRequiredJapaneseHeaders($header);

            if ($missing !== []) {
                throw new \InvalidArgumentException(
                    'CSVの列が正しくありません。不足している列: '.implode('、', $missing).'。人事システムから出力したCSV、またはテンプレートの列名を確認してください。'
                );
            }

            return;
        }

        $missing = array_diff(self::ENGLISH_REGISTRATION_HEADERS, $header);

        if ($missing !== []) {
            throw new \InvalidArgumentException(
                'CSVの列が正しくありません。テンプレートをダウンロードして列名を確認してください。'
            );
        }
    }

    /**
     * @param  list<string>  $header
     * @return list<string>
     */
    private function missingRequiredJapaneseHeaders(array $header): array
    {
        /** @var array<string, list<string>> $required */
        $required = [
            '社員コード' => ['社員コード'],
            '社員名' => ['社員名'],
            '社員略名' => ['社員略名', '社員略称名', '社員略称'],
            'E-MAIL' => ['E-MAIL'],
            '所属1部門名' => ['所属1部門名', '所属1部門名称'],
            '所属1役職名' => ['所属1役職名', '所属1役職名称', '所属役職'],
        ];

        $missing = [];

        foreach ($required as $label => $candidates) {
            if (array_intersect($candidates, $header) === []) {
                $missing[] = $label;
            }
        }

        return $missing;
    }

    private function normalizeEmploymentStatus(string $status): string
    {
        return match ($status) {
            '在職', '在籍中' => '在籍',
            '退職済', '離職' => '退職',
            default => $status,
        };
    }

    private function parseOptionalDate(string $value, string $label): ?string
    {
        $value = trim($value);

        if ($value === '') {
            return null;
        }

        $value = str_replace('/', '-', $value);

        if (! preg_match('/^\d{4}-\d{1,2}-\d{1,2}$/', $value)) {
            throw new \InvalidArgumentException(
                "{$label}「{$value}」の形式が不正です（YYYY-MM-DD または YYYY/MM/DD）。"
            );
        }

        [$year, $month, $day] = array_map('intval', explode('-', $value));

        if (! checkdate($month, $day, $year)) {
            throw new \InvalidArgumentException("{$label}「{$value}」が不正です。");
        }

        return sprintf('%04d-%02d-%02d', $year, $month, $day);
    }

    /**
     * @param  array<string, string>  $payload
     * @return list<string>
     */
    private function resultRow(array $payload, string $action, ?User $user = null): array
    {
        if ($this->isRegistrationPayload($payload)) {
            return [
                $payload['email'],
                $user?->displayName() ?? ($payload['name'] ?: trim("{$payload['last_name']} {$payload['first_name']}")),
                $payload['employee_id'] ?: '—',
                $payload['department'] ?: '—',
                $payload['position'] ?: '—',
                $action,
            ];
        }

        return [
            $payload['email'],
            $user?->displayName() ?? ($payload['name'] ?: trim("{$payload['last_name']} {$payload['first_name']}")),
            $payload['employee_id'] ?: '—',
            $payload['department'],
            $payload['section'] ?: '—',
            $payload['position'] ?: '—',
            $action,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function isRegistrationPayload(array $payload): bool
    {
        return ($payload['format'] ?? '') === 'registration';
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
        if ($this->isRegistrationPayload($payload)) {
            return $this->createNewUserFromRegistrationImport($user, $payload);
        }

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
     * @param  array<string, mixed>  $payload
     */
    private function createNewUserFromRegistrationImport(User $user, array $payload): User
    {
        $displayName = $payload['name'] ?: trim("{$payload['last_name']} {$payload['first_name']}");
        $employeeId = trim((string) ($payload['employee_id'] ?? ''));

        $this->applyInitialPassword($user, $payload, requireChangeOnFirstLogin: true);
        $user->role = User::ROLE_EMPLOYEE;
        $user->fill([
            'employee_id' => $employeeId !== '' ? $employeeId : null,
            'last_name' => $payload['last_name'] ?: null,
            'first_name' => $payload['first_name'] ?: null,
            'name' => $displayName,
        ]);
        $user->save();

        $profileData = array_filter([
            'joined_at' => trim((string) ($payload['joined_at'] ?? '')) ?: null,
            'abbreviated_name' => trim((string) ($payload['abbreviated_name'] ?? '')) ?: null,
        ], fn ($value) => $value !== null && $value !== '');

        if ($profileData !== []) {
            EmployeeProfile::updateOrCreate(['user_id' => $user->id], $profileData);
        }

        $this->syncHrDetailFromRegistrationImport($user, $payload);
        $this->syncAffiliation($user, $this->registrationAffiliationPayload($payload), []);

        return $user;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function applyInitialPassword(User $user, array $payload, bool $requireChangeOnFirstLogin): void
    {
        $password = trim((string) ($payload['password'] ?? ''));

        if ($password === '') {
            $password = 'password';
        }

        $user->password = $password;
        $user->must_change_password = $requireChangeOnFirstLogin;
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

        if ($this->isRegistrationPayload($payload)) {
            return $this->updateExistingUserFromRegistrationImport($user, $payload, $options);
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
     * @param  array<string, mixed>  $payload
     * @param  array<string, bool>  $options
     */
    private function updateExistingUserFromRegistrationImport(User $user, array $payload, array $options): User
    {
        if ($this->shouldUpdateUserFromImport($user, $options)) {
            $displayName = $payload['name'] ?: trim("{$payload['last_name']} {$payload['first_name']}");
            $employeeId = trim((string) ($payload['employee_id'] ?? ''));

            $user->fill([
                'employee_id' => $employeeId !== '' ? $employeeId : null,
                'last_name' => $payload['last_name'] ?: null,
                'first_name' => $payload['first_name'] ?: null,
                'name' => $displayName,
            ]);
            $user->save();
        }

        if ($this->shouldUpdateProfileFromImport($user, $options)) {
            $profileData = array_filter([
                'joined_at' => trim((string) ($payload['joined_at'] ?? '')) ?: null,
                'abbreviated_name' => trim((string) ($payload['abbreviated_name'] ?? '')) ?: null,
            ], fn ($value) => $value !== null && $value !== '');

            if ($profileData !== []) {
                EmployeeProfile::updateOrCreate(['user_id' => $user->id], $profileData);
            }
        }

        $this->syncHrDetailFromRegistrationImport($user, $payload);
        $this->syncAffiliation($user, $this->registrationAffiliationPayload($payload), $options);

        return $user;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function syncHrDetailFromRegistrationImport(User $user, array $payload): void
    {
        $updates = [
            'employment_type' => trim((string) ($payload['employment_type'] ?? '')) ?: null,
            'employment_status' => trim((string) ($payload['employment_status'] ?? '')) ?: '在籍',
            'department_primary' => trim((string) ($payload['department'] ?? '')) ?: null,
            'section_primary' => trim((string) ($payload['section'] ?? '')) ?: null,
            'position_primary' => trim((string) ($payload['position'] ?? '')) ?: null,
            'phone' => trim((string) ($payload['phone'] ?? '')) ?: null,
        ];

        $companyPhone = \App\Support\CompanyPhone::normalize((string) ($payload['phone'] ?? ''));
        if ($companyPhone !== null) {
            $updates['company_phone'] = $companyPhone;
        }

        EmployeeHrDetail::query()->updateOrCreate(
            ['user_id' => $user->id],
            $updates,
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function registrationAffiliationPayload(array $payload): array
    {
        $employmentStatus = trim((string) ($payload['employment_status'] ?? ''));

        return [
            'enrollment_status' => $this->mapEmploymentStatusToEnrollment($employmentStatus),
            'company' => null,
            'location' => null,
            'department' => trim((string) ($payload['department'] ?? '')) ?: null,
            'section' => trim((string) ($payload['section'] ?? '')) ?: null,
            'position' => trim((string) ($payload['position'] ?? '')) ?: null,
            'job_description' => null,
        ];
    }

    private function mapEmploymentStatusToEnrollment(string $status): string
    {
        return match ($status) {
            '退職', '辞退' => AffiliationHistory::STATUS_RESIGNED,
            default => AffiliationHistory::STATUS_ENROLLED,
        };
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

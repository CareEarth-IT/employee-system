<?php

namespace App\Support;

use App\Models\User;

class EmployeeRosterCsv
{
    /** @var list<string> */
    public const EMAIL_SKIP_VALUES = ['―', '-', '—', '–'];

    /** @var array<string, string> 社員名簿 CSV「所属」コード → 所属会社名 */
    public const AFFILIATION_CODE_TO_COMPANY = [
        'CE' => 'CareEarth',
        'GT' => 'GrowTEC',
        'EM' => 'Earth Management',
        'MD' => 'MidEarth',
        'ME' => 'MidEarth',
    ];

    /**
     * @return list<array{
     *     line: int,
     *     name: string,
     *     english_name: string,
     *     abbreviated_name: string,
     *     email: string,
     *     joined_at: string|null
     * }>
     */
    public static function readRows(string $path): array
    {
        $handle = self::openCsvHandle($path);
        $header = fgetcsv($handle);

        if ($header === false) {
            fclose($handle);

            return [];
        }

        $header = array_map(
            fn ($cell) => ltrim((string) $cell, "\xEF\xBB\xBF"),
            $header,
        );

        $indexes = self::columnIndexes($header);
        $rows = [];
        $line = 1;

        while (($data = fgetcsv($handle)) !== false) {
            $line++;

            if (self::isEmptyRow($data)) {
                continue;
            }

            $email = self::normalizeEmail((string) ($data[$indexes['email']] ?? ''));

            if ($email === null) {
                continue;
            }

            $joinedAt = self::resolveJoinedAt(
                (string) ($data[$indexes['joined_at']] ?? ''),
                $indexes['planned_joined_at'] !== null
                    ? (string) ($data[$indexes['planned_joined_at']] ?? '')
                    : '',
            );

            if ($joinedAt === null) {
                continue;
            }

            $rows[] = [
                'line' => $line,
                'name' => trim((string) ($data[$indexes['name']] ?? '')),
                'english_name' => trim((string) ($data[$indexes['english_name']] ?? '')),
                'abbreviated_name' => $indexes['abbreviated_name'] !== null
                    ? trim((string) ($data[$indexes['abbreviated_name']] ?? ''))
                    : '',
                'email' => $email,
                'joined_at' => $joinedAt,
            ];
        }

        fclose($handle);

        return $rows;
    }

    /**
     * @return list<array{
     *     line: int,
     *     name: string,
     *     english_name: string,
     *     abbreviated_name: string,
     *     email: string,
     *     affiliation_code: string,
     *     company: string
     * }>
     */
    public static function readAffiliationRows(string $path): array
    {
        $handle = self::openCsvHandle($path);
        $header = fgetcsv($handle);

        if ($header === false) {
            fclose($handle);

            return [];
        }

        $header = array_map(
            fn ($cell) => ltrim((string) $cell, "\xEF\xBB\xBF"),
            $header,
        );

        $indexes = self::affiliationColumnIndexes($header);
        $rows = [];
        $line = 1;

        while (($data = fgetcsv($handle)) !== false) {
            $line++;

            if (self::isEmptyRow($data)) {
                continue;
            }

            $email = self::normalizeEmail((string) ($data[$indexes['email']] ?? ''));

            if ($email === null) {
                continue;
            }

            $affiliationCode = strtoupper(trim((string) ($data[$indexes['affiliation_code']] ?? '')));

            if ($affiliationCode === '') {
                continue;
            }

            $company = self::mapAffiliationCodeToCompany($affiliationCode);

            if ($company === null) {
                continue;
            }

            $rows[] = [
                'line' => $line,
                'name' => trim((string) ($data[$indexes['name']] ?? '')),
                'english_name' => trim((string) ($data[$indexes['english_name']] ?? '')),
                'abbreviated_name' => $indexes['abbreviated_name'] !== null
                    ? trim((string) ($data[$indexes['abbreviated_name']] ?? ''))
                    : '',
                'email' => $email,
                'affiliation_code' => $affiliationCode,
                'company' => $company,
            ];
        }

        fclose($handle);

        return $rows;
    }

    /**
     * @return list<array{
     *     line: int,
     *     name: string,
     *     english_name: string,
     *     abbreviated_name: string,
     *     email: string,
     *     employment_status: string,
     *     employment_type: string
     * }>
     */
    public static function readHrDetailRows(string $path): array
    {
        $handle = self::openCsvHandle($path);
        $header = fgetcsv($handle);

        if ($header === false) {
            fclose($handle);

            return [];
        }

        $header = array_map(
            fn ($cell) => ltrim((string) $cell, "\xEF\xBB\xBF"),
            $header,
        );

        $indexes = self::hrDetailColumnIndexes($header);
        $rows = [];
        $line = 1;

        while (($data = fgetcsv($handle)) !== false) {
            $line++;

            if (self::isEmptyRow($data)) {
                continue;
            }

            $email = self::normalizeEmail((string) ($data[$indexes['email']] ?? ''));

            if ($email === null) {
                continue;
            }

            $employmentStatus = trim((string) ($data[$indexes['employment_status']] ?? ''));
            $employmentType = trim((string) ($data[$indexes['employment_type']] ?? ''));

            if ($employmentStatus === '' && $employmentType === '') {
                continue;
            }

            $rows[] = [
                'line' => $line,
                'name' => trim((string) ($data[$indexes['name']] ?? '')),
                'english_name' => trim((string) ($data[$indexes['english_name']] ?? '')),
                'abbreviated_name' => $indexes['abbreviated_name'] !== null
                    ? trim((string) ($data[$indexes['abbreviated_name']] ?? ''))
                    : '',
                'email' => $email,
                'employment_status' => $employmentStatus,
                'employment_type' => $employmentType,
            ];
        }

        fclose($handle);

        return $rows;
    }

    /**
     * @return list<array{
     *     line: int,
     *     name: string,
     *     english_name: string,
     *     abbreviated_name: string,
     *     email: string,
     *     affiliation_code: string,
     *     department_primary: string,
     *     section_primary: string,
     *     position_primary: string
     * }>
     */
    public static function readHrDetailOrgPrimaryRows(string $path): array
    {
        $handle = self::openCsvHandle($path);
        $header = fgetcsv($handle);

        if ($header === false) {
            fclose($handle);

            return [];
        }

        $header = array_map(
            fn ($cell) => ltrim((string) $cell, "\xEF\xBB\xBF"),
            $header,
        );

        $indexes = self::hrDetailOrgPrimaryColumnIndexes($header);
        $rows = [];
        $line = 1;

        while (($data = fgetcsv($handle)) !== false) {
            $line++;

            if (self::isEmptyRow($data)) {
                continue;
            }

            $email = self::normalizeEmail((string) ($data[$indexes['email']] ?? ''));

            if ($email === null) {
                continue;
            }

            $affiliationCode = self::normalizeTextField((string) ($data[$indexes['affiliation_code']] ?? ''));
            $departmentPrimary = self::normalizeTextField((string) ($data[$indexes['department_primary']] ?? ''));
            $sectionPrimary = self::normalizeTextField((string) ($data[$indexes['section_primary']] ?? ''));
            $positionPrimary = self::normalizeTextField((string) ($data[$indexes['position_primary']] ?? ''));

            if (
                $affiliationCode === ''
                && $departmentPrimary === ''
                && $sectionPrimary === ''
                && $positionPrimary === ''
            ) {
                continue;
            }

            $rows[] = [
                'line' => $line,
                'name' => trim((string) ($data[$indexes['name']] ?? '')),
                'english_name' => trim((string) ($data[$indexes['english_name']] ?? '')),
                'abbreviated_name' => $indexes['abbreviated_name'] !== null
                    ? trim((string) ($data[$indexes['abbreviated_name']] ?? ''))
                    : '',
                'email' => $email,
                'affiliation_code' => $affiliationCode,
                'department_primary' => $departmentPrimary,
                'section_primary' => $sectionPrimary,
                'position_primary' => $positionPrimary,
            ];
        }

        fclose($handle);

        return $rows;
    }

    public static function normalizeTextField(string $value): string
    {
        $value = trim($value);

        if ($value === '' || in_array($value, self::EMAIL_SKIP_VALUES, true)) {
            return '';
        }

        return $value;
    }

    /**
     * @return list<array{
     *     line: int,
     *     name: string,
     *     english_name: string,
     *     abbreviated_name: string,
     *     email: string,
     *     company_phone: string
     * }>
     */
    public static function readCompanyPhoneRows(string $path): array
    {
        $handle = self::openCsvHandle($path);
        $header = fgetcsv($handle);

        if ($header === false) {
            fclose($handle);

            return [];
        }

        $header = array_map(
            fn ($cell) => ltrim((string) $cell, "\xEF\xBB\xBF"),
            $header,
        );

        $indexes = self::companyPhoneColumnIndexes($header);
        $rows = [];
        $line = 1;

        while (($data = fgetcsv($handle)) !== false) {
            $line++;

            if (self::isEmptyRow($data)) {
                continue;
            }

            $email = self::normalizeEmail((string) ($data[$indexes['email']] ?? ''));

            if ($email === null) {
                continue;
            }

            $companyPhone = self::normalizePhone((string) ($data[$indexes['company_phone']] ?? ''));

            if ($companyPhone === null) {
                continue;
            }

            $rows[] = [
                'line' => $line,
                'name' => trim((string) ($data[$indexes['name']] ?? '')),
                'english_name' => trim((string) ($data[$indexes['english_name']] ?? '')),
                'abbreviated_name' => $indexes['abbreviated_name'] !== null
                    ? trim((string) ($data[$indexes['abbreviated_name']] ?? ''))
                    : '',
                'email' => $email,
                'company_phone' => $companyPhone,
            ];
        }

        fclose($handle);

        return $rows;
    }

    public static function normalizePhone(string $phone): ?string
    {
        return CompanyPhone::normalize($phone);
    }

    public static function mapAffiliationCodeToCompany(string $code): ?string
    {
        $code = strtoupper(trim($code));

        if ($code === '') {
            return null;
        }

        return self::AFFILIATION_CODE_TO_COMPANY[$code] ?? null;
    }

    /**
     * @return resource
     */
    private static function openCsvHandle(string $path)
    {
        $contents = file_get_contents($path);

        if ($contents === false) {
            throw new \RuntimeException("CSVを開けません: {$path}");
        }

        $contents = self::decodeContents($contents);

        $handle = fopen('php://memory', 'rb+');

        if ($handle === false) {
            throw new \RuntimeException("CSVを読み込めません: {$path}");
        }

        fwrite($handle, $contents);
        rewind($handle);

        return $handle;
    }

    /**
     * @param  list<string|null>  $row
     */
    private static function isEmptyRow(array $row): bool
    {
        foreach ($row as $cell) {
            if (trim((string) $cell) !== '') {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  list<string>  $header
     * @return array{
     *     name: int,
     *     english_name: int,
     *     abbreviated_name: int|null,
     *     email: int,
     *     joined_at: int,
     *     planned_joined_at: int|null
     * }
     */
    private static function columnIndexes(array $header): array
    {
        $find = static function (array $candidates, bool $required = true) use ($header): ?int {
            foreach ($candidates as $candidate) {
                $index = array_search($candidate, $header, true);

                if ($index !== false) {
                    return $index;
                }
            }

            if ($required) {
                throw new \InvalidArgumentException(
                    '社員名簿 CSV のヘッダーが不正です。列: '.implode(', ', $candidates).' が見つかりません。',
                );
            }

            return null;
        };

        return [
            'name' => $find(['名前', '氏名']),
            'english_name' => $find(['Name', 'name']),
            'abbreviated_name' => $find(['短縮表示'], false),
            'email' => $find(['社用アドレス', 'email', 'メールアドレス']),
            'joined_at' => $find(['入社日']),
            'planned_joined_at' => $find(['入社予定日'], false),
        ];
    }

    /**
     * @param  list<string>  $header
     * @return array{
     *     name: int,
     *     english_name: int,
     *     abbreviated_name: int|null,
     *     email: int,
     *     affiliation_code: int
     * }
     */
    private static function affiliationColumnIndexes(array $header): array
    {
        $find = static function (array $candidates, bool $required = true) use ($header): ?int {
            foreach ($candidates as $candidate) {
                $index = array_search($candidate, $header, true);

                if ($index !== false) {
                    return $index;
                }
            }

            if ($required) {
                throw new \InvalidArgumentException(
                    '社員名簿 CSV のヘッダーが不正です。列: '.implode(', ', $candidates).' が見つかりません。',
                );
            }

            return null;
        };

        return [
            'name' => $find(['名前', '氏名']),
            'english_name' => $find(['Name', 'name']),
            'abbreviated_name' => $find(['短縮表示'], false),
            'email' => $find(['社用アドレス', 'email', 'メールアドレス']),
            'affiliation_code' => $find(['所属']),
        ];
    }

    /**
     * @param  list<string>  $header
     * @return array{
     *     name: int,
     *     english_name: int,
     *     abbreviated_name: int|null,
     *     email: int,
     *     employment_status: int,
     *     employment_type: int
     * }
     */
    private static function hrDetailColumnIndexes(array $header): array
    {
        $find = static function (array $candidates, bool $required = true) use ($header): ?int {
            foreach ($candidates as $candidate) {
                $index = array_search($candidate, $header, true);

                if ($index !== false) {
                    return $index;
                }
            }

            if ($required) {
                throw new \InvalidArgumentException(
                    '社員名簿 CSV のヘッダーが不正です。列: '.implode(', ', $candidates).' が見つかりません。',
                );
            }

            return null;
        };

        return [
            'name' => $find(['名前', '氏名']),
            'english_name' => $find(['Name', 'name']),
            'abbreviated_name' => $find(['短縮表示'], false),
            'email' => $find(['社用アドレス', 'email', 'メールアドレス']),
            'employment_status' => $find(['状況']),
            'employment_type' => $find(['雇用形態', '雇用区分']),
        ];
    }

    /**
     * @param  list<string>  $header
     * @return array{
     *     name: int,
     *     english_name: int,
     *     abbreviated_name: int|null,
     *     email: int,
     *     company_phone: int
     * }
     */
    private static function companyPhoneColumnIndexes(array $header): array
    {
        $find = static function (array $candidates, bool $required = true) use ($header): ?int {
            foreach ($candidates as $candidate) {
                $index = array_search($candidate, $header, true);

                if ($index !== false) {
                    return $index;
                }
            }

            if ($required) {
                throw new \InvalidArgumentException(
                    '社員名簿 CSV のヘッダーが不正です。列: '.implode(', ', $candidates).' が見つかりません。',
                );
            }

            return null;
        };

        return [
            'name' => $find(['名前', '氏名']),
            'english_name' => $find(['Name', 'name']),
            'abbreviated_name' => $find(['短縮表示'], false),
            'email' => $find(['社用アドレス', 'email', 'メールアドレス']),
            'company_phone' => $find(['電話番号', '社用電話番号', '社用の電話番号']),
        ];
    }

    /**
     * @param  list<string>  $header
     * @return array{
     *     name: int,
     *     english_name: int,
     *     abbreviated_name: int|null,
     *     email: int,
     *     affiliation_code: int,
     *     department_primary: int,
     *     section_primary: int,
     *     position_primary: int
     * }
     */
    private static function hrDetailOrgPrimaryColumnIndexes(array $header): array
    {
        $find = static function (array $candidates, bool $required = true) use ($header): ?int {
            foreach ($candidates as $candidate) {
                $index = array_search($candidate, $header, true);

                if ($index !== false) {
                    return $index;
                }
            }

            if ($required) {
                throw new \InvalidArgumentException(
                    '社員名簿 CSV のヘッダーが不正です。列: '.implode(', ', $candidates).' が見つかりません。',
                );
            }

            return null;
        };

        return [
            'name' => $find(['名前', '氏名']),
            'english_name' => $find(['Name', 'name']),
            'abbreviated_name' => $find(['短縮表示'], false),
            'email' => $find(['社用アドレス', 'email', 'メールアドレス']),
            'affiliation_code' => $find(['所属']),
            'department_primary' => $find(['部署*', '部署']),
            'section_primary' => $find(['課/チーム*', '課/チーム', '課']),
            'position_primary' => $find(['役職【選択】', '役職']),
        ];
    }

    public static function resolveJoinedAt(string $joinedAt, string $plannedJoinedAt): ?string
    {
        $joinedAt = self::parseDate($joinedAt);

        if ($joinedAt !== null) {
            return $joinedAt;
        }

        return self::parseDate($plannedJoinedAt);
    }

    private static function decodeContents(string $contents): string
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

    public static function normalizeEmail(string $email): ?string
    {
        $email = trim($email);

        if ($email === '' || in_array($email, self::EMAIL_SKIP_VALUES, true)) {
            return null;
        }

        $email = strtolower($email);

        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return null;
        }

        return $email;
    }

    public static function parseDate(string $value): ?string
    {
        $value = trim($value);

        if ($value === '' || in_array($value, self::EMAIL_SKIP_VALUES, true)) {
            return null;
        }

        if (preg_match('/^(\d{4})\/(\d{1,2})\/(\d{1,2})$/', $value, $matches)) {
            return sprintf('%04d-%02d-%02d', (int) $matches[1], (int) $matches[2], (int) $matches[3]);
        }

        if (preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/', $value, $matches)) {
            return sprintf('%04d-%02d-%02d', (int) $matches[3], (int) $matches[1], (int) $matches[2]);
        }

        if (preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})$/', $value, $matches)) {
            return sprintf('%04d-%02d-%02d', (int) $matches[1], (int) $matches[2], (int) $matches[3]);
        }

        return null;
    }

    public static function normalizeName(?string $name): string
    {
        if ($name === null) {
            return '';
        }

        $name = trim($name);

        if ($name === '') {
            return '';
        }

        $name = mb_convert_kana($name, 'as', 'UTF-8');
        $name = preg_replace('/\s+/u', '', $name) ?? '';

        return mb_strtolower($name);
    }

    /**
     * @param  array{name: string, english_name: string, abbreviated_name?: string}  $row
     */
    public static function nameMatchesUser(array $row, User $user): bool
    {
        $csvNames = array_filter([
            self::normalizeName($row['name'] ?? ''),
            self::normalizeName($row['english_name'] ?? ''),
            self::normalizeName($row['abbreviated_name'] ?? ''),
        ]);

        if ($csvNames === []) {
            return false;
        }

        $profile = $user->relationLoaded('profile') ? $user->profile : $user->profile;

        $userNames = array_filter([
            self::normalizeName($user->name),
            self::normalizeName(trim("{$user->last_name} {$user->first_name}")),
            self::normalizeName($profile?->name_kana),
            self::normalizeName($profile?->english_name),
            self::normalizeName($profile?->abbreviated_name),
        ]);

        foreach ($csvNames as $csvName) {
            foreach ($userNames as $userName) {
                if ($csvName !== '' && $csvName === $userName) {
                    return true;
                }
            }
        }

        return false;
    }
}

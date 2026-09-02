<?php

namespace App\Support;

use App\Models\User;

class EmployeeRosterCsv
{
    /** @var list<string> */
    public const EMAIL_SKIP_VALUES = ['―', '-', '—', '–'];

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

        $header = self::normalizeHeader($header);

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
                self::cellValue($data, $indexes['joined_at']),
                self::cellValue($data, $indexes['planned_joined_at']),
            );

            if ($joinedAt === null) {
                continue;
            }

            $rows[] = [
                'line' => $line,
                ...self::identityRowFields($data, $indexes),
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
     *     name_kana: string,
     *     employee_id: string,
     *     gender: string,
     *     nationality: string,
     *     remarks: string,
     *     jurisdiction: string,
     *     birth_date: string|null
     * }>
     */
    public static function readRegistryIdentityRows(string $path): array
    {
        $handle = self::openCsvHandle($path);
        $header = fgetcsv($handle);

        if ($header === false) {
            fclose($handle);

            return [];
        }

        $header = self::normalizeHeader($header);
        $indexes = self::registryIdentityColumnIndexes($header);
        $rows = [];
        $line = 1;

        while (($data = fgetcsv($handle)) !== false) {
            $line++;

            if (self::isEmptyRow($data)) {
                continue;
            }

            $email = self::normalizeEmail(self::cellValue($data, $indexes['email']));

            if ($email === null) {
                continue;
            }

            $fields = self::registryIdentityFields($data, $indexes);
            $identity = self::identityRowFields($data, $indexes);

            if (
                $fields === null
                && $identity['name'] === ''
                && $identity['english_name'] === ''
                && $identity['abbreviated_name'] === ''
            ) {
                continue;
            }

            $rows[] = [
                'line' => $line,
                ...$identity,
                'email' => $email,
                ...($fields ?? [
                    'name_kana' => '',
                    'employee_id' => '',
                    'gender' => '',
                    'nationality' => '',
                    'remarks' => '',
                    'jurisdiction' => '',
                    'birth_date' => null,
                ]),
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
     *     department: string,
     *     section: string,
     *     location: string,
     *     position: string,
     *     employment_type: string
     * }>
     */
    public static function readAffiliationOrgRows(string $path): array
    {
        $handle = self::openCsvHandle($path);
        $header = fgetcsv($handle);

        if ($header === false) {
            fclose($handle);

            return [];
        }

        $header = self::normalizeHeader($header);
        $indexes = self::affiliationOrgColumnIndexes($header);
        $rows = [];
        $line = 1;

        while (($data = fgetcsv($handle)) !== false) {
            $line++;

            if (self::isEmptyRow($data)) {
                continue;
            }

            $email = self::normalizeEmail(self::cellValue($data, $indexes['email']));

            if ($email === null) {
                continue;
            }

            $department = self::normalizeTextField(self::cellValue($data, $indexes['department']));
            $section = self::normalizeTextField(self::cellValue($data, $indexes['section']));
            $location = self::normalizeTextField(self::cellValue($data, $indexes['location']));
            $position = self::normalizeTextField(self::cellValue($data, $indexes['position']));
            $employmentType = self::normalizeTextField(self::cellValue($data, $indexes['employment_type']));

            if (
                $department === ''
                && $section === ''
                && $location === ''
                && $position === ''
                && $employmentType === ''
            ) {
                continue;
            }

            $rows[] = [
                'line' => $line,
                ...self::identityRowFields($data, $indexes),
                'email' => $email,
                'department' => $department,
                'section' => $section,
                'location' => $location,
                'position' => $position,
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

        $header = self::normalizeHeader($header);

        if (self::affiliationColumnIndexes($header)['affiliation_code'] === null) {
            fclose($handle);

            return [];
        }

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

            $affiliationCode = User::canonicalAffiliationCode(self::normalizeTextField(
                self::cellValue($data, $indexes['affiliation_code']),
            )) ?? '';

            if ($affiliationCode === '') {
                continue;
            }

            $company = self::mapAffiliationCodeToCompany($affiliationCode);

            if ($company === null) {
                continue;
            }

            $rows[] = [
                'line' => $line,
                ...self::identityRowFields($data, $indexes),
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

        $header = self::normalizeHeader($header);

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

            $employmentStatus = self::normalizeTextField(
                self::cellValue($data, $indexes['employment_status']),
            );
            $employmentType = self::normalizeTextField(
                self::cellValue($data, $indexes['employment_type']),
            );

            if ($employmentStatus === '' && $employmentType === '') {
                continue;
            }

            $rows[] = [
                'line' => $line,
                ...self::identityRowFields($data, $indexes),
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

        $header = self::normalizeHeader($header);

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

            $affiliationCode = User::canonicalAffiliationCode(
                self::normalizeTextField(self::cellValue($data, $indexes['affiliation_code'])),
            ) ?? '';
            $departmentPrimary = self::normalizeTextField(self::cellValue($data, $indexes['department_primary']));
            $sectionPrimary = self::normalizeTextField(self::cellValue($data, $indexes['section_primary']));
            $positionPrimary = self::normalizeTextField(self::cellValue($data, $indexes['position_primary']));

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
                ...self::identityRowFields($data, $indexes),
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

        $header = self::normalizeHeader($header);
        $indexes = self::companyPhoneColumnIndexes($header);

        if ($indexes['company_phone'] === null) {
            fclose($handle);

            return [];
        }
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

            $companyPhone = self::normalizePhone(self::cellValue($data, $indexes['company_phone']));

            if ($companyPhone === null) {
                continue;
            }

            $rows[] = [
                'line' => $line,
                ...self::identityRowFields($data, $indexes),
                'email' => $email,
                'company_phone' => $companyPhone,
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
     *     gmail_address: string
     * }>
     */
    public static function readGmailAddressRows(string $path): array
    {
        $handle = self::openCsvHandle($path);
        $header = fgetcsv($handle);

        if ($header === false) {
            fclose($handle);

            return [];
        }

        $header = self::normalizeHeader($header);
        $indexes = self::gmailAddressColumnIndexes($header);

        if ($indexes['gmail_address'] === null) {
            fclose($handle);

            return [];
        }
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

            $gmailAddress = self::normalizeEmail(self::cellValue($data, $indexes['gmail_address']));

            if ($gmailAddress === null) {
                continue;
            }

            $rows[] = [
                'line' => $line,
                ...self::identityRowFields($data, $indexes),
                'email' => $email,
                'gmail_address' => $gmailAddress,
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
        return User::mapAffiliationCodeToCompany($code);
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
     * @return list<string>
     */
    private static function normalizeHeader(array $header): array
    {
        return array_map(
            fn ($cell) => ltrim((string) $cell, "\xEF\xBB\xBF"),
            $header,
        );
    }

    /**
     * @param  list<string|null>  $row
     */
    private static function cellValue(array $row, ?int $index): string
    {
        if ($index === null) {
            return '';
        }

        return (string) ($row[$index] ?? '');
    }

    /**
     * @param  list<string>  $header
     * @param  list<string>  $candidates
     */
    private static function headerIndex(array $header, array $candidates, bool $required = true): ?int
    {
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
    }

    /**
     * @param  list<string>  $header
     * @return array{
     *     name: int,
     *     english_name: int|null,
     *     abbreviated_name: int|null,
     *     email: int
     * }
     */
    private static function identityColumnIndexes(array $header): array
    {
        return [
            'name' => self::headerIndex($header, ['名前', '氏名']),
            'english_name' => self::headerIndex($header, ['Name', 'name'], false),
            'abbreviated_name' => self::headerIndex($header, ['短縮表示'], false),
            'email' => self::headerIndex($header, ['社用アドレス', 'email', 'メールアドレス']),
        ];
    }

    public static function truncateAbbreviatedName(string $value): string
    {
        return mb_substr(trim($value), 0, 10);
    }

    /**
     * @param  list<string|null>  $data
     * @param  array{name: int, english_name: int|null, abbreviated_name: int|null, email: int}  $indexes
     * @return array{name: string, english_name: string, abbreviated_name: string}
     */
    private static function identityRowFields(array $data, array $indexes): array
    {
        return [
            'name' => trim(self::cellValue($data, $indexes['name'])),
            'english_name' => trim(self::cellValue($data, $indexes['english_name'])),
            'abbreviated_name' => self::truncateAbbreviatedName(self::cellValue($data, $indexes['abbreviated_name'])),
        ];
    }

    /**
     * @param  list<string|null>  $data
     * @param  array{
     *     name_kana: int|null,
     *     employee_id: int|null,
     *     gender: int|null,
     *     nationality: int|null,
     *     remarks: int|null,
     *     jurisdiction: int|null,
     *     birth_date: int|null
     * }  $indexes
     * @return array{
     *     name_kana: string,
     *     employee_id: string,
     *     gender: string,
     *     nationality: string,
     *     remarks: string,
     *     jurisdiction: string,
     *     birth_date: string|null
     * }|null
     */
    private static function registryIdentityFields(array $data, array $indexes): ?array
    {
        $nameKana = self::normalizeTextField(self::cellValue($data, $indexes['name_kana']));
        $employeeId = preg_replace('/\D/', '', self::cellValue($data, $indexes['employee_id'])) ?? '';
        $gender = self::normalizeTextField(self::cellValue($data, $indexes['gender']));
        $nationalityRaw = self::normalizeTextField(self::cellValue($data, $indexes['nationality']));
        $nationality = NationalityOptions::toDisplayName($nationalityRaw) ?? '';
        $remarks = trim(self::cellValue($data, $indexes['remarks']));
        $jurisdiction = self::normalizeTextField(self::cellValue($data, $indexes['jurisdiction']));
        $birthDate = self::parseDate(self::cellValue($data, $indexes['birth_date']));

        if (
            $nameKana === ''
            && $employeeId === ''
            && $gender === ''
            && $nationality === ''
            && $remarks === ''
            && $jurisdiction === ''
            && $birthDate === null
        ) {
            return null;
        }

        return [
            'name_kana' => $nameKana,
            'employee_id' => $employeeId,
            'gender' => $gender,
            'nationality' => $nationality,
            'remarks' => $remarks,
            'jurisdiction' => $jurisdiction,
            'birth_date' => $birthDate,
        ];
    }

    public static function normalizeEmployeeId(string $value): string
    {
        return preg_replace('/\D/', '', $value) ?? '';
    }

    /**
     * @param  list<string>  $header
     * @return array{
     *     name: int,
     *     english_name: int|null,
     *     abbreviated_name: int|null,
     *     email: int,
     *     joined_at: int|null,
     *     planned_joined_at: int|null
     * }
     */
    private static function columnIndexes(array $header): array
    {
        return [
            ...self::identityColumnIndexes($header),
            'joined_at' => self::headerIndex($header, ['入社日'], false),
            'planned_joined_at' => self::headerIndex($header, ['入社予定日'], false),
        ];
    }

    /**
     * @param  list<string>  $header
     * @return array{
     *     name: int,
     *     english_name: int|null,
     *     abbreviated_name: int|null,
     *     email: int,
     *     affiliation_code: int|null
     * }
     */
    private static function affiliationColumnIndexes(array $header): array
    {
        return [
            ...self::identityColumnIndexes($header),
            'affiliation_code' => self::headerIndex($header, ['所属'], false),
        ];
    }

    /**
     * @param  list<string>  $header
     * @return array{
     *     name: int,
     *     english_name: int|null,
     *     abbreviated_name: int|null,
     *     email: int,
     *     employment_status: int|null,
     *     employment_type: int|null
     * }
     */
    private static function hrDetailColumnIndexes(array $header): array
    {
        return [
            ...self::identityColumnIndexes($header),
            'employment_status' => self::headerIndex($header, ['状況'], false),
            'employment_type' => self::headerIndex($header, ['雇用形態', '雇用区分', '社員種別'], false),
        ];
    }

    /**
     * @param  list<string>  $header
     * @return array{
     *     name: int,
     *     english_name: int|null,
     *     abbreviated_name: int|null,
     *     email: int,
     *     company_phone: int|null
     * }
     */
    private static function companyPhoneColumnIndexes(array $header): array
    {
        return [
            ...self::identityColumnIndexes($header),
            'company_phone' => self::headerIndex($header, ['電話番号', '社用電話番号', '社用の電話番号'], false),
        ];
    }

    /**
     * @param  list<string>  $header
     * @return array{
     *     name: int,
     *     english_name: int|null,
     *     abbreviated_name: int|null,
     *     email: int,
     *     gmail_address: int|null
     * }
     */
    private static function gmailAddressColumnIndexes(array $header): array
    {
        return [
            ...self::identityColumnIndexes($header),
            'gmail_address' => self::headerIndex($header, ['Googleアドレス', 'Gmailアドレス', 'gmail_address'], false),
        ];
    }

    /**
     * @param  list<string>  $header
     * @return array{
     *     name: int,
     *     english_name: int|null,
     *     abbreviated_name: int|null,
     *     email: int,
     *     affiliation_code: int|null,
     *     department_primary: int|null,
     *     section_primary: int|null,
     *     position_primary: int|null
     * }
     */
    private static function hrDetailOrgPrimaryColumnIndexes(array $header): array
    {
        return [
            ...self::identityColumnIndexes($header),
            'affiliation_code' => self::headerIndex($header, ['所属'], false),
            'department_primary' => self::headerIndex($header, ['部署*', '部署'], false),
            'section_primary' => self::headerIndex($header, ['課/チーム*', '課/チーム', '課'], false),
            'position_primary' => self::headerIndex($header, ['役職【選択】', '役職【表示】', '役職'], false),
        ];
    }

    /**
     * @param  list<string>  $header
     * @return array{
     *     name: int,
     *     english_name: int|null,
     *     abbreviated_name: int|null,
     *     email: int,
     *     name_kana: int|null,
     *     employee_id: int|null,
     *     gender: int|null,
     *     nationality: int|null,
     *     remarks: int|null,
     *     jurisdiction: int|null,
     *     birth_date: int|null
     * }
     */
    private static function registryIdentityColumnIndexes(array $header): array
    {
        return [
            ...self::identityColumnIndexes($header),
            'name_kana' => self::headerIndex($header, ['ナマエ', '氏名カナ'], false),
            'employee_id' => self::headerIndex($header, ['社員番号', 'ID', '社員ID', '社員コード'], false),
            'gender' => self::headerIndex($header, ['性別'], false),
            'nationality' => self::headerIndex($header, ['国籍'], false),
            'remarks' => self::headerIndex($header, ['備考'], false),
            'jurisdiction' => self::headerIndex($header, ['管轄', '拠点'], false),
            'birth_date' => self::headerIndex($header, ['生年月日'], false),
        ];
    }

    /**
     * @param  list<string>  $header
     * @return array{
     *     name: int,
     *     english_name: int|null,
     *     abbreviated_name: int|null,
     *     email: int,
     *     department: int|null,
     *     section: int|null,
     *     location: int|null,
     *     employment_type: int|null
     * }
     */
    private static function affiliationOrgColumnIndexes(array $header): array
    {
        return [
            ...self::identityColumnIndexes($header),
            'department' => self::headerIndex($header, ['部署*', '部署'], false),
            'section' => self::headerIndex($header, ['課/チーム*', '課/チーム', '課'], false),
            'location' => self::headerIndex($header, ['管轄', '拠点'], false),
            'position' => self::headerIndex($header, ['役職【選択】', '役職【表示】', '役職'], false),
            'employment_type' => self::headerIndex($header, ['雇用形態', '雇用区分', '社員種別'], false),
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

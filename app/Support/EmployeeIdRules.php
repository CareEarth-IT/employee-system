<?php

namespace App\Support;

use Illuminate\Validation\Rule;

final class EmployeeIdRules
{
    public const MIN_LENGTH = 1;

    /** 最大桁数（名簿・登録で使う上限） */
    public const LENGTH = 5;

    public const FORMAT_MESSAGE = '社員IDは1〜5桁の数字で入力してください。';

    /**
     * @return list<string|Rule>
     */
    public static function rules(bool $required = true, ?int $uniqueIgnoreUserId = null, bool $sometimes = false): array
    {
        $rules = [];

        if ($sometimes) {
            $rules[] = 'sometimes';
        }

        $rules[] = $required ? 'required' : 'nullable';
        $rules[] = 'string';
        $rules[] = 'regex:/^\d{'.self::MIN_LENGTH.','.self::LENGTH.'}$/';

        $unique = Rule::unique('users', 'employee_id');
        if ($uniqueIgnoreUserId !== null) {
            $unique = $unique->ignore($uniqueIgnoreUserId);
        }
        $rules[] = $unique;

        return $rules;
    }

    public static function isValid(?string $employeeId): bool
    {
        if ($employeeId === null || $employeeId === '') {
            return false;
        }

        return (bool) preg_match('/^\d{'.self::MIN_LENGTH.','.self::LENGTH.'}$/', $employeeId);
    }

    /**
     * SQLite の GLOB で 1〜5 桁の数字のみにマッチする条件。
     */
    public static function sqliteDigitGlobSql(): string
    {
        $patterns = [];

        for ($length = self::MIN_LENGTH; $length <= self::LENGTH; $length++) {
            $patterns[] = "employee_id GLOB '".str_repeat('[0-9]', $length)."'";
        }

        return '('.implode(' OR ', $patterns).')';
    }
}

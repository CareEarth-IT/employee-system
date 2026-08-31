<?php

namespace App\Support;

use Illuminate\Validation\Rule;

final class EmployeeIdRules
{
    public const LENGTH = 5;

    public const FORMAT_MESSAGE = '社員IDは5桁の数字で入力してください。';

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
        $rules[] = 'digits:'.self::LENGTH;

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

        return (bool) preg_match('/^\d{5}$/', $employeeId);
    }
}

<?php

namespace Tests\Unit;

use App\Support\EmployeeIdRules;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class EmployeeIdRulesTest extends TestCase
{
    #[DataProvider('validEmployeeIds')]
    public function test_accepts_five_digit_employee_ids(string $employeeId): void
    {
        $this->assertTrue(EmployeeIdRules::isValid($employeeId));
    }

    #[DataProvider('invalidEmployeeIds')]
    public function test_rejects_non_five_digit_employee_ids(?string $employeeId): void
    {
        $this->assertFalse(EmployeeIdRules::isValid($employeeId));
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function validEmployeeIds(): array
    {
        return [
            'leading zero' => ['00100'],
            'max value' => ['99999'],
            'sample' => ['10255'],
        ];
    }

    /**
     * @return array<string, array{0: ?string}>
     */
    public static function invalidEmployeeIds(): array
    {
        return [
            'empty' => [''],
            'null' => [null],
            'four digits' => ['1234'],
            'six digits' => ['123456'],
            'letters' => ['HR001'],
            'mixed' => ['12A45'],
        ];
    }
}

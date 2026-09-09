<?php

namespace Tests\Unit;

use App\Models\User;
use App\Support\EmployeeIndexFilters;
use Illuminate\Http\Request;
use Tests\TestCase;

class EmployeeIndexFiltersTest extends TestCase
{
    public function test_parses_filter_array_from_request(): void
    {
        $request = Request::create('/employees', 'GET', [
            'filters' => [
                ['field' => 'company', 'op' => 'eq', 'value' => 'CareEarth'],
                ['field' => 'employee_id', 'op' => 'eq', 'value' => '10255'],
            ],
        ]);

        $filters = EmployeeIndexFilters::parseFromRequest($request);

        $this->assertSame([
            ['field' => 'company', 'op' => 'eq', 'value' => 'CareEarth'],
            ['field' => 'employee_id', 'op' => 'eq', 'value' => '10255'],
        ], $filters);
    }

    public function test_parses_legacy_query_parameters(): void
    {
        $request = Request::create('/employees', 'GET', [
            'company' => 'GROWTEC',
            'employee_id' => '10255',
            'employment_type' => '正社員',
        ]);

        $filters = EmployeeIndexFilters::parseFromRequest($request);

        $this->assertSame([
            ['field' => 'company', 'op' => 'eq', 'value' => 'GROWTEC'],
            ['field' => 'employee_id', 'op' => 'eq', 'value' => '10255'],
            ['field' => 'employment_type', 'op' => 'eq', 'value' => '正社員'],
        ], $filters);
    }

    public function test_ignores_invalid_filter_values(): void
    {
        $request = Request::create('/employees', 'GET', [
            'filters' => [
                ['field' => 'company', 'op' => 'eq', 'value' => 'Unknown Co'],
                ['field' => 'employee_id', 'op' => 'contains', 'value' => ''],
            ],
        ]);

        $filters = EmployeeIndexFilters::parseFromRequest($request);

        $this->assertSame([], $filters);
    }

    public function test_to_query_params_round_trips_filters(): void
    {
        $filters = [
            ['field' => 'employment_type', 'op' => 'eq', 'value' => 'アルバイト'],
        ];

        $this->assertSame([
            'filters' => [
                ['field' => 'employment_type', 'op' => 'eq', 'value' => 'アルバイト'],
            ],
        ], EmployeeIndexFilters::toQueryParams($filters));
    }

    public function test_field_config_includes_hr_detail_sections(): void
    {
        $config = EmployeeIndexFilters::fieldConfig();

        $this->assertSame(
            ['社員一覧', '基本情報', '部署・役職', '個人情報'],
            array_column($config['groups'], 'label'),
        );
        $this->assertContains('employee_id', EmployeeIndexFilters::fieldKeysAfter('company'));
        $this->assertNotContains('company', EmployeeIndexFilters::fieldKeysAfter('company'));
        $this->assertContains('name', EmployeeIndexFilters::fieldKeysAfter('employee_id'));
        $this->assertSame([], EmployeeIndexFilters::fieldKeysAfter('company_phone'));
        $this->assertArrayHasKey('gmail_address', $config['fields']);
        $this->assertArrayHasKey('department_primary', $config['fields']);
        $this->assertArrayHasKey('gender', $config['fields']);
        $this->assertArrayNotHasKey('remarks', $config['fields']);
        $this->assertArrayHasKey('section_primary', $config['fields']);
        $this->assertArrayHasKey('section_secondary', $config['fields']);
        $this->assertSame('select', $config['fields']['section_primary']['type']);
        $this->assertSame('select', $config['fields']['nationality']['type']);
        $this->assertSame('date', $config['fields']['joined_at']['type']);
        $this->assertSame('employee_id', $config['fields']['employee_id']['input']);
        $this->assertSame('ascii', $config['fields']['email']['input']);
        $this->assertSame('次を含む', $config['operatorLabels']['contains']);
        $this->assertSame(['eq', 'not_eq', 'empty', 'not_empty'], $config['fields']['company']['operators']);
        $this->assertSame(
            ['contains', 'not_contains', 'eq', 'not_eq', 'empty', 'not_empty'],
            $config['fields']['name']['operators'],
        );
    }

    public function test_accepts_presence_operators_without_value(): void
    {
        $request = Request::create('/employees', 'GET', [
            'filters' => [
                ['field' => 'email', 'op' => 'empty', 'value' => ''],
                ['field' => 'company', 'op' => 'not_empty', 'value' => ''],
            ],
        ]);

        $this->assertSame([
            ['field' => 'email', 'op' => 'empty', 'value' => ''],
            ['field' => 'company', 'op' => 'not_empty', 'value' => ''],
        ], EmployeeIndexFilters::parseFromRequest($request));
    }

    public function test_accepts_partial_employee_id_for_contains_operator(): void
    {
        $request = Request::create('/employees', 'GET', [
            'filters' => [
                ['field' => 'employee_id', 'op' => 'contains', 'value' => '255'],
            ],
        ]);

        $this->assertSame([
            ['field' => 'employee_id', 'op' => 'contains', 'value' => '255'],
        ], EmployeeIndexFilters::parseFromRequest($request));
    }

    public function test_operator_labels_are_exposed(): void
    {
        $this->assertSame('空', EmployeeIndexFilters::operatorLabel('empty'));
        $this->assertSame('次を含まない', EmployeeIndexFilters::operatorLabel('not_contains'));
    }

    public function test_rejects_invalid_employee_id_filter_value(): void
    {
        $request = Request::create('/employees', 'GET', [
            'filters' => [
                ['field' => 'employee_id', 'op' => 'eq', 'value' => '255'],
            ],
        ]);

        $this->assertSame([], EmployeeIndexFilters::parseFromRequest($request));
    }

    public function test_accepts_valid_date_filter_value(): void
    {
        $request = Request::create('/employees', 'GET', [
            'filters' => [
                ['field' => 'joined_at', 'op' => 'eq', 'value' => '2020-04-01'],
            ],
        ]);

        $this->assertSame([
            ['field' => 'joined_at', 'op' => 'eq', 'value' => '2020-04-01'],
        ], EmployeeIndexFilters::parseFromRequest($request));
    }

    public function test_field_label_uses_hr_detail_labels(): void
    {
        $this->assertSame('部署①', EmployeeIndexFilters::fieldLabel('department_primary'));
        $this->assertSame('社用電話番号', EmployeeIndexFilters::fieldLabel('company_phone'));
    }
}

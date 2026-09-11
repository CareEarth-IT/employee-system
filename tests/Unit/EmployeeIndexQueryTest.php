<?php

namespace Tests\Unit;

use App\Models\AffiliationHistory;
use App\Models\EmployeeHrDetail;
use App\Models\User;
use App\Support\EmployeeIndexQuery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class EmployeeIndexQueryTest extends TestCase
{
    use RefreshDatabase;
    public function test_has_search_criteria_is_false_without_keyword_or_filters(): void
    {
        $request = Request::create('/employees', 'GET', ['status' => '在籍']);

        $this->assertFalse(EmployeeIndexQuery::hasSearchCriteria($request));
    }

    public function test_has_search_criteria_is_true_with_keyword(): void
    {
        $request = Request::create('/employees', 'GET', ['keyword' => '山田']);

        $this->assertTrue(EmployeeIndexQuery::hasSearchCriteria($request));
    }

    public function test_has_search_criteria_is_true_with_filters(): void
    {
        $request = Request::create('/employees', 'GET', [
            'filters' => [
                ['field' => 'company', 'op' => 'eq', 'value' => 'CareEarth'],
            ],
        ]);

        $this->assertTrue(EmployeeIndexQuery::hasSearchCriteria($request));
    }

    public function test_resolve_status_defaults_to_active_tab(): void
    {
        $request = Request::create('/employees', 'GET');

        $this->assertSame('在籍', EmployeeIndexQuery::resolveStatus($request));
    }

    public function test_resolve_status_uses_valid_status_tab(): void
    {
        $request = Request::create('/employees', 'GET', ['status' => '退職']);

        $this->assertSame('退職', EmployeeIndexQuery::resolveStatus($request));
    }

    public function test_for_export_applies_filters_keyword_and_status_together(): void
    {
        $active = User::factory()->create([
            'last_name' => 'Export',
            'first_name' => '在籍',
            'employee_id' => '99001',
        ]);
        $resigned = User::factory()->create([
            'last_name' => 'Export',
            'first_name' => '退職',
            'employee_id' => '99002',
        ]);

        EmployeeHrDetail::create([
            'user_id' => $active->id,
            'employment_status' => '在籍',
        ]);
        EmployeeHrDetail::create([
            'user_id' => $resigned->id,
            'employment_status' => '退職',
        ]);

        AffiliationHistory::create([
            'user_id' => $active->id,
            'start_date' => '2024-01-01',
            'enrollment_status' => AffiliationHistory::STATUS_ENROLLED,
            'company' => 'CareEarth',
        ]);
        AffiliationHistory::create([
            'user_id' => $resigned->id,
            'start_date' => '2024-01-01',
            'enrollment_status' => AffiliationHistory::STATUS_ENROLLED,
            'company' => 'CareEarth',
        ]);

        $request = Request::create('/employees', 'GET', [
            'status' => '在籍',
            'filters' => [
                ['field' => 'company', 'op' => 'eq', 'value' => 'CareEarth'],
            ],
        ]);

        $ids = EmployeeIndexQuery::forExport($request)->pluck('employee_id')->all();

        $this->assertSame(['99001'], $ids);
    }
}

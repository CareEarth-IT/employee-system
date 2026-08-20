<?php

namespace Tests\Feature;

use App\Models\AffiliationHistory;
use App\Models\User;
use App\Support\DepartmentPortal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmployeeDirectoryApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['app.key' => 'base64:'.base64_encode(random_bytes(32))]);
    }

    public function test_requires_proxy_secret_header(): void
    {
        config(['services.employee_portal.proxy_secret' => 'test-secret']);

        $this->get(route('internal.employee-directory'))
            ->assertForbidden();
    }

    public function test_returns_directory_entries_for_valid_secret(): void
    {
        config(['services.employee_portal.proxy_secret' => 'test-secret']);

        $employee = User::factory()->create([
            'employee_id' => '1001',
            'email' => 'realestate_member@careearth.info',
            'last_name' => '不動産',
            'first_name' => '太郎',
        ]);

        AffiliationHistory::create([
            'user_id' => $employee->id,
            'start_date' => '2024-01-01',
            'enrollment_status' => AffiliationHistory::STATUS_ENROLLED,
            'company' => 'CareEarth',
            'department' => '不動産部',
            'section' => '賃貸課',
            'position' => '正社員',
        ]);

        $response = $this->withHeader(
            DepartmentPortal::EMPLOYEE_PORTAL_PROXY_SECRET_HEADER,
            'test-secret',
        )->get(route('internal.employee-directory', [
            'department' => '不動産',
        ]));

        $response
            ->assertOk()
            ->assertJsonPath('meta.department', '不動産')
            ->assertJsonPath('employees.0.email', 'realestate_member@careearth.info')
            ->assertJsonPath('employees.0.name', '不動産 太郎')
            ->assertJsonPath('employees.0.employee_id', '1001')
            ->assertJsonPath('employees.0.department', '不動産部');
    }

    public function test_keyword_filters_results(): void
    {
        config(['services.employee_portal.proxy_secret' => 'test-secret']);

        $match = User::factory()->create([
            'email' => 'match@careearth.info',
            'last_name' => '検索',
            'first_name' => 'ヒット',
        ]);
        AffiliationHistory::create([
            'user_id' => $match->id,
            'start_date' => '2024-01-01',
            'enrollment_status' => AffiliationHistory::STATUS_ENROLLED,
            'company' => 'CareEarth',
            'department' => '不動産部',
        ]);

        $other = User::factory()->create([
            'email' => 'other@careearth.info',
            'last_name' => '別',
            'first_name' => 'ユーザー',
        ]);
        AffiliationHistory::create([
            'user_id' => $other->id,
            'start_date' => '2024-01-01',
            'enrollment_status' => AffiliationHistory::STATUS_ENROLLED,
            'company' => 'CareEarth',
            'department' => '通信部',
        ]);

        $response = $this->withHeader(
            DepartmentPortal::EMPLOYEE_PORTAL_PROXY_SECRET_HEADER,
            'test-secret',
        )->get(route('internal.employee-directory', [
            'keyword' => 'ヒット',
        ]));

        $response
            ->assertOk()
            ->assertJsonCount(1, 'employees')
            ->assertJsonPath('employees.0.email', 'match@careearth.info');
    }

    public function test_exact_email_lookup_skips_department_filter_for_sso_handoff(): void
    {
        config(['services.employee_portal.proxy_secret' => 'test-secret']);

        $accountingUser = User::factory()->create([
            'employee_id' => '2001',
            'email' => 'accounting@careearth.info',
            'last_name' => '経理',
            'first_name' => '花子',
        ]);

        AffiliationHistory::create([
            'user_id' => $accountingUser->id,
            'start_date' => '2024-01-01',
            'enrollment_status' => AffiliationHistory::STATUS_ENROLLED,
            'company' => 'CareEarth',
            'department' => '経理部',
            'section' => '経理課',
        ]);

        $response = $this->withHeader(
            DepartmentPortal::EMPLOYEE_PORTAL_PROXY_SECRET_HEADER,
            'test-secret',
        )->get(route('internal.employee-directory', [
            'keyword' => 'accounting@careearth.info',
            'department' => '不動産',
        ]));

        $response
            ->assertOk()
            ->assertJsonCount(1, 'employees')
            ->assertJsonPath('employees.0.email', 'accounting@careearth.info')
            ->assertJsonPath('employees.0.department', '経理部')
            ->assertJsonPath('employees.0.section', '経理課');
    }
}

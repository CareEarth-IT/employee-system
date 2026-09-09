<?php

namespace Tests\Unit;

use App\Models\AffiliationHistory;
use App\Models\EmployeeHrDetail;
use App\Models\User;
use App\Models\EmployeeProfile;
use App\Services\EmployeeHrDetailCsvExporter;
use App\Support\EmployeeHrDetailAccess;
use App\Support\EmployeeHrDetailFieldGroups;
use App\Support\EmployeeHrDetailFieldLabels;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmployeeHrDetailCsvExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_hr_department_viewer_gets_core_procedures_and_it_columns_for_other_employee(): void
    {
        $viewer = $this->userInAffiliation('人事部', '総務課');
        $target = User::factory()->create();

        $columns = EmployeeHrDetailAccess::exportColumnNames($viewer, [$target]);

        $this->assertEqualsCanonicalizing(
            $this->expectedFullExportColumns(),
            $columns,
        );
    }

    public function test_hr_department_viewer_includes_own_procedure_columns_in_bulk_export(): void
    {
        $viewer = $this->userInAffiliation('人事部', '総務課');
        $other = User::factory()->create();

        $columns = EmployeeHrDetailAccess::exportColumnNames($viewer, [$viewer, $other]);

        $this->assertContains('email', $columns);
        $this->assertEqualsCanonicalizing(
            $this->expectedFullExportColumns(),
            $columns,
        );
    }

    public function test_hr_section_viewer_gets_core_procedures_and_it_columns(): void
    {
        $viewer = $this->userInAffiliation('人事部', '人事課');
        $target = User::factory()->create();

        $columns = EmployeeHrDetailAccess::exportColumnNames($viewer, [$target]);

        $this->assertEqualsCanonicalizing(
            $this->expectedFullExportColumns(),
            $columns,
        );
    }

    public function test_regular_employee_exports_only_procedure_fields_for_self(): void
    {
        $viewer = $this->userInAffiliation('通信部', '営業課');

        $columns = EmployeeHrDetailAccess::exportColumnNames($viewer, [$viewer]);

        $this->assertEqualsCanonicalizing(
            EmployeeHrDetailFieldGroups::IT,
            $columns,
        );
        $this->assertTrue(EmployeeHrDetailAccess::canExportCsvForTarget($viewer, $viewer));
        $this->assertFalse(EmployeeHrDetailAccess::canExportCsv($viewer));
    }

    public function test_exporter_masks_fields_viewer_cannot_see_for_target(): void
    {
        $viewer = $this->userInAffiliation('人事部', '総務課');

        $user = User::factory()->create([
            'employee_id' => 'E100',
            'email' => 'hanako@example.com',
        ]);

        EmployeeHrDetail::create([
            'user_id' => $user->id,
            'primary_id' => 'P000123',
            'gender' => '女',
            'address_as_of_jan1' => '大阪府大阪市',
            'has_pc' => true,
        ]);

        $user->load(['profile', 'hrDetail']);

        ob_start();
        app(EmployeeHrDetailCsvExporter::class)->stream(collect([$user]), $viewer);
        $csv = (string) ob_get_clean();

        $this->assertStringContainsString('女', $csv);
        $this->assertStringContainsString('はい', $csv);
        $this->assertStringContainsString('hanako@example.com', $csv);
        $this->assertStringNotContainsString('大阪府大阪市', $csv);
    }

    public function test_exporter_outputs_gr_department_as_jurisdiction_plus_global_business_department(): void
    {
        $viewer = $this->userInAffiliation('人事部', '人事課');

        $user = User::factory()->create([
            'employee_id' => '10175',
            'email' => 'gr@example.com',
        ]);

        EmployeeHrDetail::create([
            'user_id' => $user->id,
            'jurisdiction' => '東京',
            'department_primary' => \App\Support\RegistryGrAssignment::DEPARTMENT,
        ]);

        $user->load(['profile', 'hrDetail', 'affiliationHistories']);

        ob_start();
        app(EmployeeHrDetailCsvExporter::class)->stream(collect([$user]), $viewer);
        $csv = (string) ob_get_clean();

        $this->assertStringContainsString('東京グローバル事業部', $csv);
        $this->assertStringNotContainsString('GR部（グローバル部）', $csv);
    }

    public function test_exporter_outputs_viewable_values_for_hr_section(): void
    {
        $viewer = $this->userInAffiliation('人事部', '人事課');

        $user = User::factory()->create([
            'employee_id' => 'E100',
            'email' => 'hanako@example.com',
        ]);

        EmployeeProfile::create([
            'user_id' => $user->id,
            'english_name' => 'Hanako Sato',
            'abbreviated_name' => 'HS',
            'joined_at' => '2020-04-01',
        ]);

        EmployeeHrDetail::create([
            'user_id' => $user->id,
            'primary_id' => 'P000123',
            'gender' => '女',
            'has_pc' => true,
            'has_mobile' => false,
            'address_as_of_jan1' => '大阪府大阪市',
        ]);

        $user->load(['profile', 'hrDetail']);

        ob_start();
        app(EmployeeHrDetailCsvExporter::class)->stream(collect([$user]), $viewer);
        $csv = (string) ob_get_clean();

        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        $this->assertStringContainsString('Hanako Sato', $csv);
        $this->assertStringContainsString('E100', $csv);
        $this->assertStringContainsString('hanako@example.com', $csv);
        $this->assertStringContainsString('女', $csv);
        $this->assertStringNotContainsString('大阪府大阪市', $csv);
        $this->assertStringContainsString('はい', $csv);
        $this->assertStringContainsString('いいえ', $csv);
    }

    public function test_export_all_route_is_forbidden_for_regular_employee(): void
    {
        $viewer = $this->userInAffiliation('通信部', '営業課');

        $this->actingAs($viewer)
            ->get(route('hr-details.export'))
            ->assertForbidden();
    }

    public function test_regular_employee_can_export_own_hr_detail(): void
    {
        $viewer = $this->userInAffiliation('通信部', '営業課');

        EmployeeHrDetail::create([
            'user_id' => $viewer->id,
            'address_as_of_jan1' => '東京都千代田区',
        ]);

        $response = $this->actingAs($viewer)->get(route('profile.hr-detail.export'));

        $response->assertOk();
        $this->assertStringNotContainsString('1月1日時点の住所', $response->streamedContent());
        $this->assertStringNotContainsString('性別', $response->streamedContent());
    }

    public function test_export_all_route_returns_csv_for_hr_department(): void
    {
        $viewer = $this->userInAffiliation('人事部', '総務課');

        $response = $this->actingAs($viewer)->get(route('hr-details.export'));

        $response->assertOk();
        $response->assertHeader('content-disposition');
        $this->assertStringContainsString('text/csv', (string) $response->headers->get('content-type'));
    }

    public function test_export_all_route_returns_csv_for_general_affairs(): void
    {
        $viewer = $this->userInAffiliation('経理部', '総務課');

        $response = $this->actingAs($viewer)->get(route('hr-details.export'));

        $response->assertOk();
        $response->assertHeader('content-disposition');
        $this->assertStringContainsString('text/csv', (string) $response->headers->get('content-type'));
    }

    public function test_export_all_without_search_exports_all_statuses_when_all_tab_selected(): void
    {
        $viewer = $this->userInAffiliation('人事部', '総務課');

        $active = $this->createListedEmployee('CSV', '在籍', '12001', '在籍');
        $onLeave = $this->createListedEmployee('CSV', '休職', '12002', '休職');
        $resigned = $this->createListedEmployee('CSV', '退職', '12003', '退職');

        $response = $this->actingAs($viewer)->get(route('hr-details.export', ['status' => '全体']));

        $response->assertOk();
        $csv = $response->streamedContent();
        $this->assertStringContainsString($active->employee_id, $csv);
        $this->assertStringContainsString($onLeave->employee_id, $csv);
        $this->assertStringContainsString($resigned->employee_id, $csv);
    }

    public function test_export_all_without_search_respects_status_tab(): void
    {
        $viewer = $this->userInAffiliation('人事部', '総務課');

        $active = $this->createListedEmployee('CSV', '在籍', '12011', '在籍');
        $resigned = $this->createListedEmployee('CSV', '退職', '12012', '退職');

        $response = $this->actingAs($viewer)->get(route('hr-details.export', ['status' => '在籍']));

        $response->assertOk();
        $csv = $response->streamedContent();
        $this->assertStringContainsString($active->employee_id, $csv);
        $this->assertStringNotContainsString($resigned->employee_id, $csv);
    }

    public function test_export_all_without_search_defaults_to_active_status_tab(): void
    {
        $viewer = $this->userInAffiliation('人事部', '総務課');

        $active = $this->createListedEmployee('CSV', '在籍', '12013', '在籍');
        $resigned = $this->createListedEmployee('CSV', '退職', '12014', '退職');

        $response = $this->actingAs($viewer)->get(route('hr-details.export'));

        $response->assertOk();
        $csv = $response->streamedContent();
        $this->assertStringContainsString($active->employee_id, $csv);
        $this->assertStringNotContainsString($resigned->employee_id, $csv);
    }

    public function test_export_all_with_company_filter_exports_matching_employees_only(): void
    {
        $viewer = $this->userInAffiliation('人事部', '総務課');

        $careEarth = $this->createListedEmployee('CE', '太郎', '12021', '在籍', 'CareEarth');
        $growtec = $this->createListedEmployee('GT', '花子', '12022', '在籍', 'GROWTEC');

        $response = $this->actingAs($viewer)->get(route('hr-details.export', [
            'filters' => [
                ['field' => 'company', 'op' => 'eq', 'value' => 'CareEarth'],
            ],
        ]));

        $response->assertOk();
        $csv = $response->streamedContent();
        $this->assertStringContainsString($careEarth->employee_id, $csv);
        $this->assertStringNotContainsString($growtec->employee_id, $csv);
    }

    public function test_export_all_with_keyword_exports_matching_employees_only(): void
    {
        $viewer = $this->userInAffiliation('人事部', '総務課');

        $matched = User::factory()->create([
            'last_name' => 'キーワード',
            'first_name' => '一致',
            'employee_id' => '12031',
            'email' => 'keyword-match@careearth.info',
        ]);
        $this->markEmploymentStatus($matched);

        $other = User::factory()->create([
            'last_name' => '別',
            'first_name' => '社員',
            'employee_id' => '12032',
            'email' => 'other@careearth.info',
        ]);
        $this->markEmploymentStatus($other);

        $response = $this->actingAs($viewer)->get(route('hr-details.export', [
            'keyword' => 'keyword-match',
        ]));

        $response->assertOk();
        $csv = $response->streamedContent();
        $this->assertStringContainsString($matched->employee_id, $csv);
        $this->assertStringNotContainsString($other->employee_id, $csv);
    }

    public function test_export_all_with_search_does_not_limit_to_active_status_tab(): void
    {
        $viewer = $this->userInAffiliation('人事部', '総務課');

        $active = $this->createListedEmployee('検索', '在籍', '12041', '在籍', 'CareEarth');
        $resigned = $this->createListedEmployee('検索', '退職', '12042', '退職', 'CareEarth');

        $response = $this->actingAs($viewer)->get(route('hr-details.export', [
            'filters' => [
                ['field' => 'company', 'op' => 'eq', 'value' => 'CareEarth'],
            ],
        ]));

        $response->assertOk();
        $csv = $response->streamedContent();
        $this->assertStringContainsString($active->employee_id, $csv);
        $this->assertStringContainsString($resigned->employee_id, $csv);
    }

    public function test_general_affairs_viewer_gets_core_columns_only_for_other_employee(): void
    {
        $viewer = $this->userInAffiliation('経理部', '総務課');
        $target = User::factory()->create();

        $columns = EmployeeHrDetailAccess::exportColumnNames($viewer, [$target]);

        $this->assertEqualsCanonicalizing(
            array_values(array_unique(array_merge(
                EmployeeHrDetailFieldLabels::META_CORE,
                EmployeeHrDetailFieldGroups::CORE,
            ))),
            $columns,
        );
    }

    /**
     * @return list<string>
     */
    private function expectedFullExportColumns(): array
    {
        return array_values(array_unique(array_merge(
            EmployeeHrDetailFieldLabels::META,
            EmployeeHrDetailFieldGroups::CORE,
            EmployeeHrDetailFieldGroups::PROCEDURES,
            EmployeeHrDetailFieldGroups::IT,
        )));
    }

    private function userInAffiliation(string $department, string $section): User
    {
        $user = User::factory()->create();

        AffiliationHistory::create([
            'user_id' => $user->id,
            'start_date' => '2024-01-01',
            'enrollment_status' => AffiliationHistory::STATUS_ENROLLED,
            'department' => $department,
            'section' => $section,
            'location' => '大阪',
        ]);

        return $user->fresh();
    }

    private function createListedEmployee(
        string $lastName,
        string $firstName,
        string $employeeId,
        string $employmentStatus,
        string $company = 'CareEarth',
    ): User {
        $user = User::factory()->create([
            'last_name' => $lastName,
            'first_name' => $firstName,
            'employee_id' => $employeeId,
        ]);

        AffiliationHistory::create([
            'user_id' => $user->id,
            'start_date' => '2024-01-01',
            'enrollment_status' => AffiliationHistory::STATUS_ENROLLED,
            'company' => $company,
            'location' => '大阪',
        ]);

        EmployeeHrDetail::create([
            'user_id' => $user->id,
            'employment_status' => $employmentStatus,
        ]);

        return $user->fresh();
    }

    private function markEmploymentStatus(User $user, string $status = '在籍'): void
    {
        EmployeeHrDetail::create([
            'user_id' => $user->id,
            'employment_status' => $status,
        ]);
    }
}

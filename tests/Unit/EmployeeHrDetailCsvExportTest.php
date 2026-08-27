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

    public function test_hr_department_viewer_gets_core_and_it_columns_for_other_employee(): void
    {
        $viewer = $this->userInAffiliation('人事部', '総務課');
        $target = User::factory()->create();

        $columns = EmployeeHrDetailAccess::exportColumnNames($viewer, [$target]);

        $this->assertEqualsCanonicalizing(
            array_merge(
                EmployeeHrDetailFieldLabels::META_CORE,
                EmployeeHrDetailFieldGroups::CORE,
                EmployeeHrDetailFieldGroups::IT,
            ),
            $columns,
        );
        $this->assertNotContains('email', $columns);
    }

    public function test_hr_department_viewer_includes_own_procedure_columns_in_bulk_export(): void
    {
        $viewer = $this->userInAffiliation('人事部', '総務課');
        $other = User::factory()->create();

        $columns = EmployeeHrDetailAccess::exportColumnNames($viewer, [$viewer, $other]);

        $this->assertContains('email', $columns);
        $this->assertEqualsCanonicalizing(
            array_merge(
                EmployeeHrDetailFieldLabels::META,
                EmployeeHrDetailFieldGroups::CORE,
                EmployeeHrDetailFieldGroups::PROCEDURES,
                EmployeeHrDetailFieldGroups::IT,
            ),
            $columns,
        );
    }

    public function test_hr_section_viewer_gets_core_procedures_and_it_columns(): void
    {
        $viewer = $this->userInAffiliation('人事部', '人事課');
        $target = User::factory()->create();

        $columns = EmployeeHrDetailAccess::exportColumnNames($viewer, [$target]);

        $this->assertEqualsCanonicalizing(
            array_merge(
                EmployeeHrDetailFieldLabels::META,
                EmployeeHrDetailFieldGroups::CORE,
                EmployeeHrDetailFieldGroups::PROCEDURES,
                EmployeeHrDetailFieldGroups::IT,
            ),
            $columns,
        );
    }

    public function test_regular_employee_exports_only_procedure_fields_for_self(): void
    {
        $viewer = $this->userInAffiliation('通信部', '営業課');

        $columns = EmployeeHrDetailAccess::exportColumnNames($viewer, [$viewer]);

        $this->assertEqualsCanonicalizing(
            array_merge(
                EmployeeHrDetailFieldLabels::META_PROCEDURES,
                EmployeeHrDetailFieldGroups::PROCEDURES,
                EmployeeHrDetailFieldGroups::IT,
            ),
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
        $this->assertStringNotContainsString('hanako@example.com', $csv);
        $this->assertStringNotContainsString('大阪府大阪市', $csv);
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
            'my_number_verified' => true,
            'has_pc' => false,
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
        $this->assertStringContainsString('大阪府大阪市', $csv);
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
        $this->assertStringContainsString('東京都千代田区', $response->streamedContent());
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
}

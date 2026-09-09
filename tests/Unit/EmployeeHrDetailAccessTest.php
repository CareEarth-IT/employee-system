<?php

namespace Tests\Unit;

use App\Models\AffiliationHistory;
use App\Models\User;
use App\Support\EmployeeHrDetailAccess;
use App\Support\EmployeeHrDetailFieldGroups;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmployeeHrDetailAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_information_systems_can_edit_hr_detail_for_others(): void
    {
        $viewer = $this->userInAffiliation('情報システム部', '事業IT推進課');
        $target = User::factory()->create();

        $this->assertTrue(EmployeeHrDetailAccess::canEditCore($viewer, $target));
        $this->assertTrue(EmployeeHrDetailAccess::canEditProcedures($viewer, $target));
        $this->assertTrue(EmployeeHrDetailAccess::canViewProcedures($viewer, $target));
        $this->assertTrue(EmployeeHrDetailAccess::canEditIt($viewer, $target));
        $this->assertTrue(EmployeeHrDetailAccess::canUpdateAny($viewer, $target));
    }

    public function test_hr_section_can_edit_procedures_for_others(): void
    {
        $viewer = $this->userInAffiliation('人事部', '人事課');
        $target = User::factory()->create();

        $this->assertTrue(EmployeeHrDetailAccess::canEditProcedures($viewer, $target));
        $this->assertTrue(EmployeeHrDetailAccess::canEditCore($viewer, $target));
    }

    public function test_hr_department_can_edit_procedures_for_others(): void
    {
        $viewer = $this->userInAffiliation('人事部', '総務課');
        $target = User::factory()->create();

        $this->assertTrue(EmployeeHrDetailAccess::canEditProcedures($viewer, $target));
        $this->assertTrue(EmployeeHrDetailAccess::canViewProcedures($viewer, $target));
        $this->assertTrue(EmployeeHrDetailAccess::canEditCore($viewer, $target));
    }

    public function test_general_affairs_can_view_and_edit_core_but_not_procedures(): void
    {
        $viewer = $this->userInAffiliation('経理部', '総務課');
        $target = User::factory()->create();

        $this->assertTrue(EmployeeHrDetailAccess::canViewCore($viewer, $target));
        $this->assertTrue(EmployeeHrDetailAccess::canViewPage($viewer, $target));
        $this->assertTrue(EmployeeHrDetailAccess::canExportCsv($viewer));
        $this->assertTrue(EmployeeHrDetailAccess::canEditCore($viewer, $target));
        $this->assertTrue(EmployeeHrDetailAccess::canUpdateAny($viewer, $target));
        $this->assertTrue($viewer->canManageAffiliation($target));
        $this->assertTrue($viewer->canEditProfile($target));
        $this->assertFalse(EmployeeHrDetailAccess::canEditProcedures($viewer, $target));
        $this->assertFalse(EmployeeHrDetailAccess::canViewProcedures($viewer, $target));
    }

    public function test_employee_can_view_but_not_edit_own_procedures(): void
    {
        $viewer = $this->userInAffiliation('通信部', '営業課');
        $other = User::factory()->create();

        $this->assertTrue(EmployeeHrDetailAccess::canViewProcedures($viewer, $viewer));
        $this->assertFalse(EmployeeHrDetailAccess::canEditProcedures($viewer, $viewer));
        $this->assertFalse(EmployeeHrDetailAccess::canViewProcedures($viewer, $other));
        $this->assertFalse(EmployeeHrDetailAccess::canEditProcedures($viewer, $other));
    }

    public function test_employee_can_edit_own_device_fields_only(): void
    {
        $viewer = $this->userInAffiliation('通信部', '営業課');
        $other = User::factory()->create();

        $this->assertTrue(EmployeeHrDetailAccess::canViewIt($viewer, $viewer));
        $this->assertTrue(EmployeeHrDetailAccess::canEditItSelfDevice($viewer, $viewer));
        $this->assertFalse(EmployeeHrDetailAccess::canEditIt($viewer, $viewer));
        $this->assertTrue(EmployeeHrDetailAccess::canUpdateAny($viewer, $viewer));

        $this->assertEqualsCanonicalizing(
            EmployeeHrDetailFieldGroups::IT_SELF_EDITABLE,
            EmployeeHrDetailAccess::editableFieldNames($viewer, $viewer),
        );

        $this->assertFalse(EmployeeHrDetailAccess::canViewIt($viewer, $other));
        $this->assertFalse(EmployeeHrDetailAccess::canEditItSelfDevice($viewer, $other));
        $this->assertFalse(EmployeeHrDetailAccess::canUpdateAny($viewer, $other));
        $this->assertSame([], EmployeeHrDetailAccess::editableFieldNames($viewer, $other));
    }

    public function test_export_column_names_for_bulk_matches_union_of_targets(): void
    {
        $viewer = $this->userInAffiliation('人事部', '総務課');
        $other = User::factory()->create();

        $this->assertEqualsCanonicalizing(
            EmployeeHrDetailAccess::exportColumnNames($viewer, [$viewer, $other]),
            EmployeeHrDetailAccess::exportColumnNamesForBulk($viewer),
        );
    }

    private function userInAffiliation(string $department, ?string $section = null): User
    {
        $user = User::factory()->create();

        AffiliationHistory::create([
            'user_id' => $user->id,
            'start_date' => '2024-01-01',
            'enrollment_status' => AffiliationHistory::STATUS_ENROLLED,
            'company' => 'CareEarth',
            'department' => $department,
            'section' => $section,
            'position' => '一般',
            'location' => '大阪',
        ]);

        return $user->fresh();
    }
}

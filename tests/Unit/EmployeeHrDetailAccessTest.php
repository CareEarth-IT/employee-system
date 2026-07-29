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

    public function test_information_systems_cannot_edit_or_view_procedures_for_others(): void
    {
        $viewer = $this->userInAffiliation('情報システム部', '事業IT推進課');
        $target = User::factory()->create();

        $this->assertFalse(EmployeeHrDetailAccess::canEditProcedures($viewer, $target));
        $this->assertFalse(EmployeeHrDetailAccess::canViewProcedures($viewer, $target));
        $this->assertFalse(EmployeeHrDetailAccess::canEditCore($viewer, $target));
        $this->assertTrue(EmployeeHrDetailAccess::canEditIt($viewer, $target));
    }

    public function test_hr_section_can_edit_procedures_for_others(): void
    {
        $viewer = $this->userInAffiliation('人事部', '人事課');
        $target = User::factory()->create();

        $this->assertTrue(EmployeeHrDetailAccess::canEditProcedures($viewer, $target));
        $this->assertTrue(EmployeeHrDetailAccess::canEditCore($viewer, $target));
    }

    public function test_hr_department_without_hr_section_cannot_edit_procedures_for_others(): void
    {
        $viewer = $this->userInAffiliation('人事部', '総務課');
        $target = User::factory()->create();

        $this->assertFalse(EmployeeHrDetailAccess::canEditProcedures($viewer, $target));
        $this->assertTrue(EmployeeHrDetailAccess::canEditCore($viewer, $target));
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

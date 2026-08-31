<?php

namespace Tests\Unit;

use App\Models\AffiliationHistory;
use App\Models\User;
use App\Support\EmployeeHrDetailAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExecutivePermissionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_executive_by_department_can_edit_other_profiles(): void
    {
        $executive = $this->userInAffiliation('役員', '役員', '代表');
        $other = User::factory()->create();

        $this->assertTrue($executive->isExecutive());
        $this->assertTrue($executive->canEditProfile($other));
    }

    public function test_executive_by_position_can_edit_other_profiles(): void
    {
        $executive = $this->userInAffiliation('大阪グローバル事業部', '役員', '執行役員');
        $other = User::factory()->create();

        $this->assertTrue($executive->isExecutive());
        $this->assertTrue($executive->canEditProfile($other));
    }

    public function test_non_executive_cannot_edit_other_profiles(): void
    {
        $employee = $this->userInAffiliation('通信部', '営業課', '主任');
        $other = User::factory()->create();

        $this->assertFalse($employee->isExecutive());
        $this->assertFalse($employee->canEditProfile($other));
    }

    public function test_only_information_systems_can_edit_employee_identity(): void
    {
        $is = $this->userInAffiliation('情報システム部', 'システム課', '一般');
        $hr = $this->userInAffiliation('人事部', '人事課', '一般');
        $other = User::factory()->create();

        $this->assertTrue($is->canEditEmployeeIdentity($other));
        $this->assertFalse($hr->canEditEmployeeIdentity($other));
        $this->assertFalse($other->canEditEmployeeIdentity($other));
    }

    public function test_registry_access_for_information_systems_and_hr_section(): void
    {
        $is = $this->userInAffiliation('情報システム部', 'システム課', '一般');
        $hr = $this->userInAffiliation('人事部', '人事課', '一般');
        $hrGeneral = $this->userInAffiliation('人事部', '総務課', '一般');
        $other = User::factory()->create();

        $this->assertTrue($is->canManageEmployeeRegistry());
        $this->assertTrue($hr->canManageEmployeeRegistry());
        $this->assertFalse($hrGeneral->canManageEmployeeRegistry());
        $this->assertFalse($other->canManageEmployeeRegistry());

        $this->assertTrue($is->shouldForceProfileEditMode($other));
        $this->assertTrue($hr->shouldForceProfileEditMode($other));
        $this->assertFalse($hrGeneral->shouldForceProfileEditMode($other));
        $this->assertFalse($other->shouldForceProfileEditMode($other));
    }

    public function test_past_executive_affiliation_does_not_grant_permissions(): void
    {
        $user = User::factory()->create();

        AffiliationHistory::create([
            'user_id' => $user->id,
            'start_date' => '2024-01-01',
            'end_date' => '2025-03-31',
            'enrollment_status' => AffiliationHistory::STATUS_MOVED,
            'department' => '役員',
            'section' => '役員',
            'position' => '代表',
        ]);

        AffiliationHistory::create([
            'user_id' => $user->id,
            'start_date' => '2025-04-01',
            'enrollment_status' => AffiliationHistory::STATUS_ENROLLED,
            'department' => '通信部',
            'section' => '営業課',
        ]);

        $other = User::factory()->create();

        $this->assertFalse($user->fresh()->isExecutive());
        $this->assertFalse($user->fresh()->canEditProfile($other));
    }

    public function test_executive_can_edit_all_hr_detail_sections(): void
    {
        $executive = $this->userInAffiliation('役員', '役員', '代表');
        $target = User::factory()->create();

        $this->assertTrue(EmployeeHrDetailAccess::canEditCore($executive, $target));
        $this->assertTrue(EmployeeHrDetailAccess::canEditProcedures($executive, $target));
        $this->assertTrue(EmployeeHrDetailAccess::canEditIt($executive, $target));
        $this->assertTrue(EmployeeHrDetailAccess::canViewPage($executive, $target));
        $this->assertTrue(EmployeeHrDetailAccess::canExportCsv($executive));
    }

    private function userInAffiliation(string $department, string $section, string $position): User
    {
        $user = User::factory()->create();

        AffiliationHistory::create([
            'user_id' => $user->id,
            'start_date' => '2024-01-01',
            'enrollment_status' => AffiliationHistory::STATUS_ENROLLED,
            'department' => $department,
            'section' => $section,
            'position' => $position,
            'location' => '大阪',
        ]);

        return $user->fresh();
    }
}

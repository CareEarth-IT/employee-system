<?php

namespace Tests\Unit;

use App\Models\AffiliationHistory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_self_with_enrolled_affiliation_cannot_manage_affiliation(): void
    {
        $user = $this->userInAffiliation('通信部', '営業課');

        $this->assertFalse($user->canManageAffiliation($user));
    }

    public function test_self_without_enrolled_affiliation_can_manage_affiliation(): void
    {
        $user = User::factory()->create();

        $this->assertTrue($user->canManageAffiliation($user));
    }

    public function test_self_cannot_edit_profile_fields(): void
    {
        $user = $this->userInAffiliation('通信部', '営業課');

        $this->assertFalse($user->canEditProfile($user));
        $this->assertFalse($user->canFullyEditProfile($user));
        $this->assertSame([], $user->editableProfileFieldNames($user));
        $this->assertFalse($user->canViewPersonalProfileSections($user));
        $this->assertFalse($user->canEditProfileField($user, 'languages'));
        $this->assertFalse($user->canEditProfileField($user, 'self_introduction'));
        $this->assertFalse($user->canEditProfileField($user, 'name_kana'));
    }

    public function test_hr_section_can_fully_edit_other_profile(): void
    {
        $viewer = $this->userInAffiliation('人事部', '人事課');
        $target = User::factory()->create();

        $this->assertTrue($viewer->canFullyEditProfile($target));
        $this->assertTrue($viewer->canEditProfile($target));
        $this->assertTrue($viewer->canManageAffiliation($target));
        $this->assertTrue($viewer->canEditCurrentAffiliationOrg());
        $this->assertSame(
            User::FULL_PROFILE_EDITABLE_FIELDS,
            $viewer->editableProfileFieldNames($target),
        );
        $this->assertFalse($viewer->canEditProfileField($target, 'languages'));
        $this->assertFalse($viewer->canEditProfileField($target, 'self_introduction'));
        $this->assertTrue($viewer->canEditProfileField($target, 'photo'));
    }

    public function test_hr_department_without_hr_section_cannot_manage_affiliation(): void
    {
        $viewer = $this->userInAffiliation('人事部', '採用課');
        $target = User::factory()->create();

        $this->assertTrue($viewer->isHrDepartment());
        $this->assertFalse($viewer->isHrSection());
        $this->assertTrue($viewer->canFullyEditProfile($target));
        $this->assertFalse($viewer->canManageAffiliation($target));
        $this->assertFalse($viewer->canEditCurrentAffiliationOrg());
    }

    public function test_executive_cannot_manage_affiliation(): void
    {
        $viewer = $this->userInAffiliation('役員', '役員', '代表');
        $target = User::factory()->create();

        $this->assertTrue($viewer->isExecutive());
        $this->assertTrue($viewer->canEditProfile($target));
        $this->assertFalse($viewer->canManageAffiliation($target));
        $this->assertFalse($viewer->canEditCurrentAffiliationOrg());
    }

    public function test_general_affairs_can_fully_edit_other_profile_and_own_affiliation(): void
    {
        $viewer = $this->userInAffiliation('経理部', '総務課');
        $target = User::factory()->create();

        $this->assertTrue($viewer->canFullyEditProfile($target));
        $this->assertTrue($viewer->canManageAffiliation($viewer));
    }

    private function userInAffiliation(string $department, string $section, string $position = '一般'): User
    {
        $user = User::factory()->create();

        AffiliationHistory::create([
            'user_id' => $user->id,
            'start_date' => '2024-01-01',
            'enrollment_status' => AffiliationHistory::STATUS_ENROLLED,
            'company' => 'CareEarth',
            'department' => $department,
            'section' => $section,
            'position' => $position,
            'location' => '大阪',
        ]);

        return $user->fresh(['affiliationHistories']);
    }
}

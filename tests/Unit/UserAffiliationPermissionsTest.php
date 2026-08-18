<?php

namespace Tests\Unit;

use App\Models\AffiliationHistory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserAffiliationPermissionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_past_hr_affiliation_does_not_grant_hr_permissions(): void
    {
        $user = User::factory()->create(['role' => User::ROLE_HR]);

        AffiliationHistory::create([
            'user_id' => $user->id,
            'start_date' => '2024-01-01',
            'end_date' => '2025-03-31',
            'enrollment_status' => AffiliationHistory::STATUS_MOVED,
            'department' => '人事部',
            'section' => '人事課',
        ]);

        AffiliationHistory::create([
            'user_id' => $user->id,
            'start_date' => '2025-04-01',
            'enrollment_status' => AffiliationHistory::STATUS_ENROLLED,
            'department' => '営業部',
            'section' => '営業課',
        ]);

        $user->syncRoleFromAffiliation();

        $this->assertFalse($user->fresh()->isHrDepartment());
        $this->assertFalse($user->fresh()->isHrSection());
        $this->assertSame(User::ROLE_EMPLOYEE, $user->fresh()->role);
    }

    public function test_past_general_affairs_affiliation_does_not_grant_equipment_permissions(): void
    {
        $user = User::factory()->create();

        AffiliationHistory::create([
            'user_id' => $user->id,
            'start_date' => '2024-01-01',
            'end_date' => '2025-03-31',
            'enrollment_status' => AffiliationHistory::STATUS_MOVED,
            'department' => '経理部',
            'section' => '総務課',
        ]);

        AffiliationHistory::create([
            'user_id' => $user->id,
            'start_date' => '2025-04-01',
            'enrollment_status' => AffiliationHistory::STATUS_ENROLLED,
            'department' => '営業部',
            'section' => '営業課',
        ]);

        $this->assertFalse($user->fresh()->isGeneralAffairs());
    }

    public function test_new_enrolled_affiliation_closes_previous_enrolled_records(): void
    {
        $user = User::factory()->create();

        $previous = AffiliationHistory::create([
            'user_id' => $user->id,
            'start_date' => '2024-01-01',
            'enrollment_status' => AffiliationHistory::STATUS_ENROLLED,
            'department' => '人事部',
            'section' => '人事課',
        ]);

        $current = AffiliationHistory::create([
            'user_id' => $user->id,
            'start_date' => '2025-04-01',
            'enrollment_status' => AffiliationHistory::STATUS_ENROLLED,
            'department' => '営業部',
            'section' => '営業課',
        ]);

        $user->closeOtherEnrolledAffiliations($current);

        $this->assertSame(AffiliationHistory::STATUS_MOVED, $previous->fresh()->enrollment_status);
        $this->assertNotNull($previous->fresh()->end_date);
        $this->assertTrue($user->fresh()->currentAffiliation()?->is($current));
        $this->assertFalse($user->fresh()->isHrDepartment());
    }
}

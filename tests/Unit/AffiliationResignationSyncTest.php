<?php

namespace Tests\Unit;

use App\Models\AffiliationHistory;
use App\Models\EmployeeHrDetail;
use App\Models\User;
use App\Support\AffiliationResignationSync;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AffiliationResignationSyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_resigned_at_closes_current_enrolled_affiliation(): void
    {
        $user = User::factory()->create();

        $affiliation = AffiliationHistory::create([
            'user_id' => $user->id,
            'start_date' => '2024-01-01',
            'enrollment_status' => AffiliationHistory::STATUS_ENROLLED,
            'company' => 'CareEarth',
            'location' => '大阪',
            'department' => '営業部',
            'section' => '営業1課',
        ]);

        $detail = EmployeeHrDetail::create([
            'user_id' => $user->id,
            'employment_status' => '在籍',
            'resigned_at' => '2025-03-31',
        ]);

        $this->assertTrue(AffiliationResignationSync::syncFromHrDetail($user->fresh(), $detail));

        $affiliation->refresh();
        $detail->refresh();

        $this->assertSame(AffiliationHistory::STATUS_RESIGNED, $affiliation->enrollment_status);
        $this->assertSame('2025-03-31', $affiliation->end_date?->toDateString());
        $this->assertNull($user->fresh()->currentAffiliation());
        $this->assertSame('退職', $detail->employment_status);
    }

    public function test_resigned_at_does_not_change_past_affiliations(): void
    {
        $user = User::factory()->create();

        $pastAffiliation = AffiliationHistory::create([
            'user_id' => $user->id,
            'start_date' => '2020-01-01',
            'end_date' => '2023-12-31',
            'enrollment_status' => AffiliationHistory::STATUS_MOVED,
            'company' => 'CareEarth',
            'department' => '旧部署',
        ]);

        $currentAffiliation = AffiliationHistory::create([
            'user_id' => $user->id,
            'start_date' => '2024-01-01',
            'enrollment_status' => AffiliationHistory::STATUS_ENROLLED,
            'company' => 'CareEarth',
            'department' => '営業部',
        ]);

        $detail = EmployeeHrDetail::create([
            'user_id' => $user->id,
            'resigned_at' => '2025-06-30',
        ]);

        AffiliationResignationSync::syncFromHrDetail($user->fresh(), $detail);

        $pastAffiliation->refresh();
        $currentAffiliation->refresh();

        $this->assertSame(AffiliationHistory::STATUS_MOVED, $pastAffiliation->enrollment_status);
        $this->assertSame('2023-12-31', $pastAffiliation->end_date?->toDateString());
        $this->assertSame(AffiliationHistory::STATUS_RESIGNED, $currentAffiliation->enrollment_status);
        $this->assertSame('2025-06-30', $currentAffiliation->end_date?->toDateString());
    }

    public function test_changing_resigned_at_updates_latest_resigned_affiliation_end_date(): void
    {
        $user = User::factory()->create();

        $affiliation = AffiliationHistory::create([
            'user_id' => $user->id,
            'start_date' => '2024-01-01',
            'end_date' => '2025-03-31',
            'enrollment_status' => AffiliationHistory::STATUS_RESIGNED,
            'company' => 'CareEarth',
            'department' => '営業部',
        ]);

        $detail = EmployeeHrDetail::create([
            'user_id' => $user->id,
            'employment_status' => '退職',
            'resigned_at' => '2025-04-15',
        ]);

        AffiliationResignationSync::syncFromHrDetail($user->fresh(), $detail);

        $affiliation->refresh();

        $this->assertSame('2025-04-15', $affiliation->end_date?->toDateString());
    }
}

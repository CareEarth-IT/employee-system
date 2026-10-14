<?php

namespace Tests\Feature;

use App\Models\AffiliationHistory;
use App\Models\ScheduledAffiliationChange;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScheduledAffiliationChangeTest extends TestCase
{
    use RefreshDatabase;

    public function test_hr_section_sees_schedule_block_on_profile(): void
    {
        $hr = $this->userInAffiliation('人事部', '人事課');
        $target = $this->userInAffiliation('グローバル事業部', 'GR-O部');

        $this->actingAs($hr)
            ->get(route('users.profile.edit', $target))
            ->assertOk()
            ->assertSee('異動・役職の事前登録', false)
            ->assertSee('事前登録する', false);
    }

    public function test_non_hr_does_not_see_schedule_block(): void
    {
        $viewer = $this->userInAffiliation('情報システム部', '事業IT推進課');
        $target = $this->userInAffiliation('グローバル事業部', 'GR-O部');

        $this->actingAs($viewer)
            ->get(route('users.profile.edit', $target))
            ->assertOk()
            ->assertDontSee('異動・役職の事前登録', false);
    }

    public function test_hr_can_create_scheduled_change_storing_only_changed_fields(): void
    {
        $hr = $this->userInAffiliation('人事部', '人事課');
        $target = $this->userInAffiliation('営業部', '営業1課');
        $before = $target->currentAffiliation();

        $this->travelTo(now()->startOfDay());

        $this->assertReplacesLocationTo(
            $this->actingAs($hr)->post(route('users.scheduled-affiliations.store', $target), [
                'effective_date' => now()->addDays(5)->toDateString(),
                'company' => 'CareEarth',
                'location' => '大阪',
                'department' => '営業部',
                'section' => '営業1課',
                'position' => '主任',
                'job_description' => '',
            ]),
            route('users.profile.edit', $target),
        );

        $target->refresh();
        $current = $target->currentAffiliation();

        $this->assertSame($before?->id, $current?->id);
        $this->assertSame('営業部', $current?->department);

        $pending = $target->pendingScheduledAffiliationChange();
        $this->assertNotNull($pending);
        $this->assertNull($pending->company);
        $this->assertNull($pending->location);
        $this->assertNull($pending->department);
        $this->assertNull($pending->section);
        $this->assertSame('主任', $pending->position);
        $this->assertSame(ScheduledAffiliationChange::STATUS_PENDING, $pending->status);
    }

    public function test_scheduled_change_applies_only_changed_fields(): void
    {
        $hr = $this->userInAffiliation('人事部', '人事課');
        $target = $this->userInAffiliation('営業部', '営業1課');
        $old = $target->currentAffiliation();

        $this->travelTo('2026-04-01 10:00:00');

        $schedule = ScheduledAffiliationChange::create([
            'user_id' => $target->id,
            'created_by' => $hr->id,
            'effective_date' => '2026-04-10',
            'company' => null,
            'location' => null,
            'department' => null,
            'section' => null,
            'position' => '係長',
            'job_description' => null,
            'status' => ScheduledAffiliationChange::STATUS_PENDING,
        ]);

        $this->travelTo('2026-04-10 09:00:00');

        $current = $target->fresh()->currentAffiliation();

        $this->assertNotNull($current);
        $this->assertNotSame($old?->id, $current->id);
        $this->assertSame('CareEarth', $current->company);
        $this->assertSame('大阪', $current->location);
        $this->assertSame('営業部', $current->department);
        $this->assertSame('営業1課', $current->section);
        $this->assertSame('係長', $current->position);
        $this->assertSame('2026-04-10', $current->start_date?->toDateString());

        $old?->refresh();
        $this->assertSame(AffiliationHistory::STATUS_MOVED, $old?->enrollment_status);
        $this->assertSame('2026-04-10', $old?->end_date?->toDateString());

        $schedule->refresh();
        $this->assertSame(ScheduledAffiliationChange::STATUS_APPLIED, $schedule->status);
        $this->assertSame($current->id, $schedule->applied_affiliation_id);
    }

    public function test_unchanged_submission_is_rejected(): void
    {
        $hr = $this->userInAffiliation('人事部', '人事課');
        $target = $this->userInAffiliation('営業部', '営業1課');

        $this->travelTo(now()->startOfDay());

        $this->actingAs($hr)
            ->from(route('users.scheduled-affiliations.create', $target))
            ->post(route('users.scheduled-affiliations.store', $target), [
                'effective_date' => now()->addDays(5)->toDateString(),
                'company' => 'CareEarth',
                'location' => '大阪',
                'department' => '営業部',
                'section' => '営業1課',
                'position' => '一般',
                'job_description' => '',
            ])
            ->assertRedirect(route('users.scheduled-affiliations.create', $target))
            ->assertSessionHasErrors('effective_date');

        $this->assertNull($target->fresh()->pendingScheduledAffiliationChange());
    }

    public function test_hr_can_cancel_pending_schedule(): void
    {
        $hr = $this->userInAffiliation('人事部', '人事課');
        $target = $this->userInAffiliation('営業部', '営業1課');

        $this->travelTo(now()->startOfDay());

        $schedule = ScheduledAffiliationChange::create([
            'user_id' => $target->id,
            'created_by' => $hr->id,
            'effective_date' => now()->addWeek()->toDateString(),
            'position' => '主任',
            'status' => ScheduledAffiliationChange::STATUS_PENDING,
        ]);

        $this->assertReplacesLocationTo(
            $this->actingAs($hr)->delete(route('scheduled-affiliations.destroy', $schedule)),
            route('users.profile.edit', $target),
        );

        $schedule->refresh();
        $this->assertSame(ScheduledAffiliationChange::STATUS_CANCELLED, $schedule->status);
        $this->assertNull($target->fresh()->pendingScheduledAffiliationChange());
    }

    public function test_non_hr_cannot_create_schedule(): void
    {
        $viewer = $this->userInAffiliation('情報システム部', '事業IT推進課');
        $target = $this->userInAffiliation('営業部', '営業1課');

        $this->travelTo(now()->startOfDay());

        $this->actingAs($viewer)
            ->post(route('users.scheduled-affiliations.store', $target), [
                'effective_date' => now()->addDays(3)->toDateString(),
                'position' => '主任',
            ])
            ->assertForbidden();
    }

    public function test_profile_shows_only_changed_fields_for_pending_schedule(): void
    {
        $hr = $this->userInAffiliation('人事部', '人事課');
        $target = $this->userInAffiliation('営業部', '営業1課');

        ScheduledAffiliationChange::create([
            'user_id' => $target->id,
            'created_by' => $hr->id,
            'effective_date' => now()->addWeek()->toDateString(),
            'position' => '主任',
            'status' => ScheduledAffiliationChange::STATUS_PENDING,
        ]);

        $this->actingAs($hr)
            ->get(route('users.profile.edit', $target))
            ->assertOk()
            ->assertSee('役職: 主任', false)
            ->assertSee('変更箇所のみ反映', false);
    }

    private function userInAffiliation(string $department, string $section): User
    {
        $user = User::factory()->create();

        AffiliationHistory::create([
            'user_id' => $user->id,
            'start_date' => '2024-01-01',
            'enrollment_status' => AffiliationHistory::STATUS_ENROLLED,
            'company' => 'CareEarth',
            'location' => '大阪',
            'department' => $department,
            'section' => $section,
            'position' => '一般',
        ]);

        return $user->fresh();
    }
}

<?php

namespace Tests\Feature;

use App\Models\AffiliationHistory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AffiliationConcurrentTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_keep_multiple_enrolled_affiliations_without_closing_others(): void
    {
        $hr = $this->hrUser();
        $target = User::factory()->create();

        $existing = AffiliationHistory::create([
            'user_id' => $target->id,
            'start_date' => '2024-01-01',
            'enrollment_status' => AffiliationHistory::STATUS_ENROLLED,
            'company' => 'CareEarth',
            'location' => '大阪',
            'department' => '人事部',
            'section' => '人事課',
            'position' => '一般',
        ]);

        $this->actingAs($hr)
            ->post(route('users.affiliations.store', $target), [
                'is_current' => '1',
                'close_others' => '0',
                'start_date' => '2026-06-24',
                'company' => 'CareEarth',
                'location' => '東京',
                'department' => '営業部',
                'section' => '営業1課',
                'position' => '一般',
                'action' => 'save',
            ])
            ->tap(fn ($response) => $this->assertReplacesLocationTo(
                $response,
                route('users.profile.edit', $target),
            ));

        $target->refresh();
        $active = $target->affiliationHistories()->currentlyActive()->get();

        $this->assertCount(2, $active);
        $this->assertTrue($existing->fresh()->isEnrolled());

        $this->actingAs($hr)
            ->get(route('users.profile.edit', $target))
            ->assertOk()
            ->assertSee('現在', false)
            ->assertDontSee('主務', false)
            ->assertDontSee('兼務', false);
    }

    public function test_close_others_still_ends_previous_enrolled_affiliations(): void
    {
        $hr = $this->hrUser();
        $target = User::factory()->create();

        $previous = AffiliationHistory::create([
            'user_id' => $target->id,
            'start_date' => '2024-01-01',
            'enrollment_status' => AffiliationHistory::STATUS_ENROLLED,
            'department' => '人事部',
            'section' => '人事課',
        ]);

        $this->actingAs($hr)
            ->post(route('users.affiliations.store', $target), [
                'is_current' => '1',
                'close_others' => '1',
                'start_date' => '2026-06-24',
                'company' => 'CareEarth',
                'location' => '大阪',
                'department' => '営業部',
                'section' => '営業1課',
                'position' => '一般',
                'action' => 'save',
            ])
            ->tap(fn ($response) => $this->assertReplacesLocationTo(
                $response,
                route('users.profile.edit', $target),
            ));

        $previous->refresh();
        $this->assertSame(AffiliationHistory::STATUS_MOVED, $previous->enrollment_status);
        $this->assertSame('営業部', $target->fresh()->currentAffiliation()?->department);
    }

    private function hrUser(): User
    {
        $user = User::factory()->create();
        AffiliationHistory::create([
            'user_id' => $user->id,
            'start_date' => '2024-01-01',
            'enrollment_status' => AffiliationHistory::STATUS_ENROLLED,
            'department' => '人事部',
            'section' => '人事課',
            'location' => '大阪',
        ]);

        return $user->fresh();
    }
}

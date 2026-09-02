<?php

namespace Tests\Feature;

use App\Models\AffiliationHistory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AffiliationCurrentOrgLockTest extends TestCase
{
    use RefreshDatabase;

    public function test_employee_cannot_change_current_department_section_or_period(): void
    {
        $user = User::factory()->create();
        $affiliation = AffiliationHistory::create([
            'user_id' => $user->id,
            'start_date' => '2026-01-13',
            'end_date' => null,
            'enrollment_status' => AffiliationHistory::STATUS_ENROLLED,
            'company' => 'Earth Management',
            'location' => '大阪',
            'department' => '通信部',
            'section' => null,
            'position' => '一般',
        ]);

        $response = $this->actingAs($user)->put(route('affiliations.update', $affiliation), [
            'is_current' => '0',
            'start_date' => '2020-01-01',
            'end_date' => '2025-12-31',
            'department' => '食品部',
            'section' => '営業課',
            'position' => '主任',
            'location' => '東京',
            'action' => 'save',
        ]);

        $response->assertRedirect();

        $affiliation->refresh();
        $this->assertSame('2026-01-13', $affiliation->start_date->toDateString());
        $this->assertNull($affiliation->end_date);
        $this->assertSame(AffiliationHistory::STATUS_ENROLLED, $affiliation->enrollment_status);
        $this->assertSame('通信部', $affiliation->department);
        $this->assertNull($affiliation->section);
        $this->assertSame('主任', $affiliation->position);
        $this->assertSame('東京', $affiliation->location);
    }

    public function test_hr_department_can_change_current_department_section_and_period(): void
    {
        $hr = User::factory()->create();
        AffiliationHistory::create([
            'user_id' => $hr->id,
            'start_date' => '2024-01-01',
            'enrollment_status' => AffiliationHistory::STATUS_ENROLLED,
            'department' => '人事部',
            'section' => '人事課',
            'position' => '一般',
        ]);

        $target = User::factory()->create();
        $affiliation = AffiliationHistory::create([
            'user_id' => $target->id,
            'start_date' => '2026-01-13',
            'end_date' => null,
            'enrollment_status' => AffiliationHistory::STATUS_ENROLLED,
            'department' => '情報システム部',
            'section' => null,
            'position' => '一般',
        ]);

        $response = $this->actingAs($hr->fresh())->put(route('affiliations.update', $affiliation), [
            'is_current' => '1',
            'start_date' => '2026-02-01',
            'department' => '通信部',
            'section' => '営業課',
            'position' => '一般',
            'action' => 'save',
        ]);

        $response->assertRedirect();

        $affiliation->refresh();
        $this->assertSame('2026-02-01', $affiliation->start_date->toDateString());
        $this->assertSame(AffiliationHistory::STATUS_ENROLLED, $affiliation->enrollment_status);
        $this->assertSame('通信部', $affiliation->department);
        $this->assertSame('営業課', $affiliation->section);

        $detail = $target->fresh()->hrDetail;
        $this->assertSame('通信部', $detail?->department_primary);
        $this->assertSame('営業課', $detail?->section_primary);
    }

    public function test_employee_edit_form_locks_current_org_fields(): void
    {
        $user = User::factory()->create();
        $affiliation = AffiliationHistory::create([
            'user_id' => $user->id,
            'start_date' => '2026-01-13',
            'end_date' => null,
            'enrollment_status' => AffiliationHistory::STATUS_ENROLLED,
            'department' => '通信部',
            'section' => null,
            'position' => '一般',
        ]);

        $response = $this->actingAs($user)->get(route('affiliations.edit', $affiliation));

        $response->assertOk();
        $response->assertSee('現在の所属のため、部・課・チーム・期間の変更は人事部・情報システム部のみ可能です', false);
        $response->assertDontSee('id="department"', false);
        $response->assertDontSee('id="start_date"', false);
    }

    public function test_information_systems_department_can_change_current_department_section_and_period(): void
    {
        $editor = User::factory()->create();
        AffiliationHistory::create([
            'user_id' => $editor->id,
            'start_date' => '2024-01-01',
            'enrollment_status' => AffiliationHistory::STATUS_ENROLLED,
            'department' => '情報システム部',
            'section' => null,
            'position' => '一般',
        ]);

        $target = User::factory()->create();
        $affiliation = AffiliationHistory::create([
            'user_id' => $target->id,
            'start_date' => '2026-01-13',
            'end_date' => null,
            'enrollment_status' => AffiliationHistory::STATUS_ENROLLED,
            'department' => '食品部',
            'section' => null,
            'position' => '一般',
        ]);

        $response = $this->actingAs($editor->fresh())->put(route('affiliations.update', $affiliation), [
            'is_current' => '1',
            'start_date' => '2026-03-01',
            'department' => '通信部',
            'section' => '営業課',
            'position' => '一般',
            'action' => 'save',
        ]);

        $response->assertRedirect();

        $affiliation->refresh();
        $this->assertSame('2026-03-01', $affiliation->start_date->toDateString());
        $this->assertSame(AffiliationHistory::STATUS_ENROLLED, $affiliation->enrollment_status);
        $this->assertSame('通信部', $affiliation->department);
        $this->assertSame('営業課', $affiliation->section);
    }
}

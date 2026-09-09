<?php

namespace Tests\Feature;

use App\Models\AffiliationHistory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AffiliationCurrentOrgLockTest extends TestCase
{
    use RefreshDatabase;

    public function test_employee_cannot_update_current_affiliation(): void
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

        $response->assertForbidden();
    }

    public function test_hr_section_can_change_current_department_section_and_period(): void
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

    public function test_employee_cannot_open_affiliation_edit_form_for_current_affiliation(): void
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

        $response->assertForbidden();
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

    public function test_general_affairs_can_change_current_department_section_and_period(): void
    {
        $editor = User::factory()->create();
        AffiliationHistory::create([
            'user_id' => $editor->id,
            'start_date' => '2024-01-01',
            'enrollment_status' => AffiliationHistory::STATUS_ENROLLED,
            'department' => '経理部',
            'section' => '総務課',
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

    public function test_hr_section_can_update_current_affiliation_with_gr_o_nested_team(): void
    {
        $hr = User::factory()->create();
        AffiliationHistory::create([
            'user_id' => $hr->id,
            'start_date' => '2024-01-01',
            'enrollment_status' => AffiliationHistory::STATUS_ENROLLED,
            'department' => '人事部',
            'section' => '人事課',
            'location' => '大阪',
        ]);

        $target = User::factory()->create();
        $affiliation = AffiliationHistory::create([
            'user_id' => $target->id,
            'start_date' => '2026-01-13',
            'end_date' => null,
            'enrollment_status' => AffiliationHistory::STATUS_ENROLLED,
            'company' => 'CareEarth',
            'location' => '大阪',
            'department' => 'GR部（グローバル部）',
            'section' => 'GR-C_大阪,GR-総務課_大阪',
            'position' => '一般',
        ]);

        $response = $this->actingAs($hr->fresh())->put(route('affiliations.update', $affiliation), [
            'is_current' => '1',
            'start_date' => '2026-02-01',
            'company' => 'CareEarth',
            'location' => '大阪',
            'department' => 'GR部（グローバル部）',
            'section' => 'GR-O部',
            'team' => '固定現場チーム',
            'position' => '一般',
            'action' => 'save',
        ]);

        $response->assertRedirect();

        $affiliation->refresh();
        $this->assertSame(
            'GR-O_大阪,GR-O CS課 固定現場チーム_大阪',
            $affiliation->section,
        );
    }
}

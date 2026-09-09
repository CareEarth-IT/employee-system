<?php

namespace Tests\Feature;

use App\Models\EmployeeHrDetail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmployeeHrDetailOrgFormTest extends TestCase
{
    use RefreshDatabase;

    public function test_edit_form_shows_affiliation_with_official_company_name(): void
    {
        $hr = $this->userInAffiliation('人事部', '人事課');
        $target = User::factory()->create();
        EmployeeHrDetail::create([
            'user_id' => $target->id,
            'affiliation_code' => 'CE',
        ]);

        $this->actingAs($hr)
            ->get(route('users.profile.hr-detail.edit', $target))
            ->assertOk()
            ->assertSee('CareEarth', false)
            ->assertDontSee('>CE<', false);
    }

    public function test_hr_user_can_update_affiliation_with_official_company_name(): void
    {
        $hr = $this->userInAffiliation('人事部', '人事課');
        $target = User::factory()->create();
        EmployeeHrDetail::create([
            'user_id' => $target->id,
            'affiliation_code' => 'CE',
        ]);

        $this->actingAs($hr)
            ->put(route('users.profile.hr-detail.update', $target), [
                'affiliation_code' => 'GROWTEC',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame('GT', $target->fresh()->hrDetail?->affiliation_code);
    }

    public function test_edit_form_shows_split_org_fields(): void
    {
        $hr = $this->userInAffiliation('人事部', '人事課');
        $target = User::factory()->create();
        EmployeeHrDetail::create([
            'user_id' => $target->id,
            'department_primary' => 'Food Sales部',
            'section_primary' => '法人チーム',
            'jurisdiction' => '大阪',
        ]);

        $this->actingAs($hr)
            ->get(route('users.profile.hr-detail.edit', $target))
            ->assertOk()
            ->assertSee('for="department_primary"', false)
            ->assertSee('for="section_primary"', false)
            ->assertSee('for="team_primary"', false)
            ->assertSee('課①', false)
            ->assertSee('チーム①', false);
    }

    public function test_hr_user_can_update_split_org_fields(): void
    {
        $hr = $this->userInAffiliation('人事部', '人事課');
        $target = User::factory()->create();
        EmployeeHrDetail::create([
            'user_id' => $target->id,
            'department_primary' => 'Food Sales部',
            'section_primary' => null,
            'jurisdiction' => '大阪',
        ]);

        $response = $this->actingAs($hr)
            ->put(route('users.profile.hr-detail.update', $target), [
                'department_primary' => 'Food Sales部',
                'section_primary' => '',
                'team_primary' => 'ECチーム',
                'jurisdiction' => '大阪',
            ]);

        $response->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame('ECチーム', $target->fresh()->hrDetail?->section_primary);
    }

    public function test_hr_user_can_update_administrative_affairs_with_empty_department(): void
    {
        $hr = $this->userInAffiliation('人事部', '人事課');
        $target = User::factory()->create();
        EmployeeHrDetail::create([
            'user_id' => $target->id,
            'department_primary' => '管理本部',
            'section_primary' => '庶務課',
            'jurisdiction' => '大阪',
        ]);

        $this->actingAs($hr)
            ->put(route('users.profile.hr-detail.update', $target), [
                'department_primary' => '',
                'section_primary' => '庶務課',
                'jurisdiction' => '大阪',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $detail = $target->fresh()->hrDetail;
        $this->assertSame('管理本部', $detail?->department_primary);
        $this->assertSame('庶務課', $detail?->section_primary);
    }

    public function test_hr_detail_update_syncs_current_affiliation_org_fields(): void
    {
        $hr = $this->userInAffiliation('人事部', '人事課');
        $target = User::factory()->create();

        $affiliation = \App\Models\AffiliationHistory::create([
            'user_id' => $target->id,
            'start_date' => '2024-01-01',
            'enrollment_status' => \App\Models\AffiliationHistory::STATUS_ENROLLED,
            'company' => 'GROWTEC',
            'location' => '東京',
            'department' => '旧部署',
            'section' => '旧課',
            'position' => '旧役職',
        ]);

        EmployeeHrDetail::create([
            'user_id' => $target->id,
            'affiliation_code' => 'GT',
            'jurisdiction' => '東京',
            'department_primary' => '旧部署',
            'section_primary' => '旧課',
            'position_primary' => '旧役職',
        ]);

        $this->actingAs($hr)
            ->put(route('users.profile.hr-detail.update', $target), [
                'affiliation_code' => 'CareEarth',
                'jurisdiction' => '大阪',
                'department_primary' => '情報システム部',
                'section_primary' => '情報システム課',
                'position_primary' => '課長',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $affiliation->refresh();
        $this->assertSame('CareEarth', $affiliation->company);
        $this->assertSame('大阪', $affiliation->location);
        $this->assertSame('情報システム部', $affiliation->department);
        $this->assertSame('情報システム課', $affiliation->section);
        $this->assertSame('課長', $affiliation->position);
    }

    public function test_hr_user_cannot_set_administrative_affairs_section_with_department(): void
    {
        $hr = $this->userInAffiliation('人事部', '人事課');
        $target = User::factory()->create();
        EmployeeHrDetail::create([
            'user_id' => $target->id,
            'jurisdiction' => '大阪',
        ]);

        $this->actingAs($hr)
            ->put(route('users.profile.hr-detail.update', $target), [
                'department_primary' => '人事部',
                'section_primary' => '庶務課',
                'jurisdiction' => '大阪',
            ])
            ->assertSessionHasErrors(['section_primary']);
    }

    public function test_hr_user_can_update_gr_o_nested_team(): void
    {
        $hr = $this->userInAffiliation('人事部', '人事課');
        $target = User::factory()->create();
        EmployeeHrDetail::create([
            'user_id' => $target->id,
            'department_primary' => 'GR部（グローバル部）',
            'section_primary' => 'GR-C_大阪,GR-総務課_大阪',
            'jurisdiction' => '大阪',
        ]);

        $this->actingAs($hr)
            ->put(route('users.profile.hr-detail.update', $target), [
                'jurisdiction' => '大阪',
                'department_primary' => 'GR部（グローバル部）',
                'section_primary' => 'GR-O部',
                'team_primary' => '固定現場チーム',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $detail = $target->fresh()->hrDetail;
        $this->assertSame(
            'GR-O_大阪,GR-O CS課 固定現場チーム_大阪',
            $detail?->section_primary,
        );
    }

    public function test_hr_detail_update_syncs_gr_o_team_to_current_affiliation(): void
    {
        $hr = $this->userInAffiliation('人事部', '人事課');
        $target = User::factory()->create();

        $affiliation = \App\Models\AffiliationHistory::create([
            'user_id' => $target->id,
            'start_date' => '2024-01-01',
            'enrollment_status' => \App\Models\AffiliationHistory::STATUS_ENROLLED,
            'company' => 'CareEarth',
            'location' => '大阪',
            'department' => 'GR部（グローバル部）',
            'section' => 'GR-C_大阪,GR-総務課_大阪',
            'position' => '一般',
        ]);

        EmployeeHrDetail::create([
            'user_id' => $target->id,
            'affiliation_code' => 'CE',
            'jurisdiction' => '大阪',
            'department_primary' => 'GR部（グローバル部）',
            'section_primary' => 'GR-C_大阪,GR-総務課_大阪',
            'position_primary' => '一般',
        ]);

        $this->actingAs($hr)
            ->put(route('users.profile.hr-detail.update', $target), [
                'jurisdiction' => '大阪',
                'department_primary' => 'GR部（グローバル部）',
                'section_primary' => 'GR-O部',
                'team_primary' => '固定現場チーム',
                'position_primary' => '一般',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $affiliation->refresh();
        $this->assertSame(
            'GR-O_大阪,GR-O CS課 固定現場チーム_大阪',
            $affiliation->section,
        );
    }

    public function test_hr_detail_resigned_at_closes_current_affiliation(): void
    {
        $hr = $this->userInAffiliation('人事部', '人事課');
        $target = User::factory()->create();

        $affiliation = \App\Models\AffiliationHistory::create([
            'user_id' => $target->id,
            'start_date' => '2024-01-01',
            'enrollment_status' => \App\Models\AffiliationHistory::STATUS_ENROLLED,
            'company' => 'CareEarth',
            'location' => '大阪',
            'department' => '営業部',
            'section' => '営業1課',
        ]);

        EmployeeHrDetail::create([
            'user_id' => $target->id,
            'employment_status' => '在籍',
        ]);

        $this->actingAs($hr)
            ->put(route('users.profile.hr-detail.update', $target), [
                'resigned_at' => '2025-12-31',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $affiliation->refresh();

        $this->assertSame(\App\Models\AffiliationHistory::STATUS_RESIGNED, $affiliation->enrollment_status);
        $this->assertSame('2025-12-31', $affiliation->end_date?->toDateString());
        $this->assertSame('退職', $target->fresh()->hrDetail?->employment_status);
        $this->assertNull($target->fresh()->currentAffiliation());
    }

    public function test_hr_detail_can_save_without_joined_at(): void
    {
        $hr = $this->userInAffiliation('人事部', '人事課');
        $target = User::factory()->create();

        EmployeeHrDetail::create([
            'user_id' => $target->id,
            'employment_status' => '在籍',
        ]);

        $this->actingAs($hr)
            ->put(route('users.profile.hr-detail.update', $target), [
                'employment_status' => '在籍',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertNull($target->fresh()->profile?->joined_at);
    }

    public function test_hr_detail_can_clear_joined_at(): void
    {
        $hr = $this->userInAffiliation('人事部', '人事課');
        $target = User::factory()->create();

        $target->profile()->create([
            'joined_at' => '2020-04-01',
        ]);

        EmployeeHrDetail::create([
            'user_id' => $target->id,
            'employment_status' => '在籍',
        ]);

        $this->actingAs($hr)
            ->put(route('users.profile.hr-detail.update', $target), [
                'joined_at' => '',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertNull($target->fresh()->profile?->joined_at);
    }

    private function userInAffiliation(string $department, string $section): User
    {
        $user = User::factory()->create();

        \App\Models\AffiliationHistory::create([
            'user_id' => $user->id,
            'start_date' => '2024-01-01',
            'enrollment_status' => \App\Models\AffiliationHistory::STATUS_ENROLLED,
            'department' => $department,
            'section' => $section,
            'location' => '大阪',
        ]);

        return $user->fresh();
    }
}

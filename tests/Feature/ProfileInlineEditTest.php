<?php

namespace Tests\Feature;

use App\Models\AffiliationHistory;
use App\Models\EmployeeHrDetail;
use App\Models\EmployeeProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileInlineEditTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_update_profile_field_via_json_request(): void
    {
        $user = User::factory()->create();
        EmployeeProfile::create([
            'user_id' => $user->id,
            'name_kana' => '山田太郎',
            'nationality' => '日本',
        ]);

        $response = $this->actingAs($user)->putJson(route('profile.update'), [
            'name_kana' => '山田 太郎',
        ]);

        $response->assertOk()
            ->assertJsonPath('fields.name_kana.value', '山田 太郎')
            ->assertJsonPath('fields.name_kana.display', '山田 太郎');

        $this->assertSame('山田 太郎', $user->fresh()->profile?->name_kana);
        $this->assertSame('山田 太郎', $user->fresh()->name);
    }

    public function test_other_user_cannot_update_profile_via_json_request(): void
    {
        $viewer = User::factory()->create();
        $target = User::factory()->create();
        EmployeeProfile::create(['user_id' => $target->id, 'name_kana' => '対象者']);

        $response = $this->actingAs($viewer)->putJson(route('users.profile.update', $target), [
            'name_kana' => '変更',
        ]);

        $response->assertForbidden();
    }

    public function test_executive_can_update_other_profile_via_json_request(): void
    {
        $executive = User::factory()->create();
        AffiliationHistory::create([
            'user_id' => $executive->id,
            'start_date' => '2024-01-01',
            'enrollment_status' => AffiliationHistory::STATUS_ENROLLED,
            'department' => '役員',
            'section' => '役員',
            'position' => '代表',
        ]);
        $target = User::factory()->create();
        EmployeeProfile::create(['user_id' => $target->id, 'name_kana' => '対象者']);

        $response = $this->actingAs($executive->fresh())->putJson(route('users.profile.update', $target), [
            'name_kana' => '変更後',
        ]);

        $response->assertOk()
            ->assertJsonPath('fields.name_kana.value', '変更後');

        $this->assertSame('変更後', $target->fresh()->profile?->name_kana);
    }

    public function test_executive_sees_inline_edit_on_other_profile(): void
    {
        $executive = User::factory()->create();
        AffiliationHistory::create([
            'user_id' => $executive->id,
            'start_date' => '2024-01-01',
            'enrollment_status' => AffiliationHistory::STATUS_ENROLLED,
            'department' => '役員',
            'section' => '役員',
            'position' => '代表',
        ]);
        $target = User::factory()->create();

        $this->actingAs($executive->fresh())
            ->get(route('users.profile.show', $target))
            ->assertOk()
            ->assertSee('data-profile-inline-edit', false);
    }

    public function test_show_page_includes_inline_edit_markers_for_self(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('profile.show'));

        $response->assertOk()
            ->assertSee('data-profile-inline-edit', false)
            ->assertSee('ダブルクリックで編集', false);
    }

    public function test_show_page_hides_inline_edit_markers_for_other_viewer(): void
    {
        $viewer = User::factory()->create();
        $target = User::factory()->create();

        $response = $this->actingAs($viewer)->get(route('users.profile.show', $target));

        $response->assertOk()
            ->assertDontSee('data-profile-inline-edit', false);
    }

    public function test_show_page_displays_employment_status_and_type(): void
    {
        $user = User::factory()->create();
        EmployeeHrDetail::create([
            'user_id' => $user->id,
            'employment_status' => '在籍',
            'employment_type' => '正社員',
        ]);

        $this->actingAs($user)
            ->get(route('profile.show'))
            ->assertOk()
            ->assertSee('状況', false)
            ->assertSee('雇用形態', false)
            ->assertSee('在籍', false)
            ->assertSee('正社員', false);
    }

    public function test_information_systems_can_update_other_employee_identity(): void
    {
        $viewer = $this->userInDepartment('情報システム部');
        $target = User::factory()->create([
            'email' => 'old@careearth.info',
            'employee_id' => '10001',
        ]);

        $response = $this->actingAs($viewer)->putJson(route('users.profile.update', $target), [
            'email' => 'new@careearth.info',
            'employee_id' => '20002',
        ]);

        $response->assertOk()
            ->assertJsonPath('fields.email.value', 'new@careearth.info')
            ->assertJsonPath('fields.employee_id.value', '20002');

        $target->refresh();
        $this->assertSame('new@careearth.info', $target->email);
        $this->assertSame('20002', $target->employee_id);
    }

    public function test_hr_cannot_update_other_employee_identity(): void
    {
        $viewer = $this->userInDepartment('人事部');
        $target = User::factory()->create([
            'email' => 'keep@careearth.info',
            'employee_id' => '10001',
        ]);

        $this->actingAs($viewer)->putJson(route('users.profile.update', $target), [
            'email' => 'hacked@careearth.info',
            'employee_id' => '99999',
        ])->assertForbidden();

        $target->refresh();
        $this->assertSame('keep@careearth.info', $target->email);
        $this->assertSame('10001', $target->employee_id);
    }

    public function test_self_cannot_update_own_identity_unless_information_systems(): void
    {
        $user = $this->userInDepartment('通信部');
        $user->forceFill([
            'email' => 'self@careearth.info',
            'employee_id' => '30001',
        ])->save();

        $this->actingAs($user)->putJson(route('profile.update'), [
            'email' => 'changed@careearth.info',
        ])->assertForbidden();

        $this->assertSame('self@careearth.info', $user->fresh()->email);
    }

    private function userInDepartment(string $department, string $section = '一般'): User
    {
        $user = User::factory()->create();

        AffiliationHistory::create([
            'user_id' => $user->id,
            'department' => $department,
            'section' => $section,
            'position' => '一般',
            'enrollment_status' => AffiliationHistory::STATUS_ENROLLED,
            'start_date' => now()->subYear()->toDateString(),
            'end_date' => null,
            'location' => '大阪',
            'company' => 'CareEarth',
        ]);

        return $user->fresh(['affiliationHistories']);
    }
}

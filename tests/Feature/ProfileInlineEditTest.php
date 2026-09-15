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

    public function test_user_cannot_update_languages_via_json_request(): void
    {
        $user = User::factory()->create();
        EmployeeProfile::create([
            'user_id' => $user->id,
            'languages' => '日本語',
        ]);

        $this->actingAs($user)->putJson(route('profile.update'), [
            'languages' => '日本語,英語',
        ])->assertForbidden();

        $this->assertSame('日本語', $user->fresh()->profile?->languages);
    }

    public function test_user_cannot_update_restricted_profile_field_via_json_request(): void
    {
        $user = User::factory()->create();
        EmployeeProfile::create([
            'user_id' => $user->id,
            'name_kana' => '山田太郎',
        ]);

        $this->actingAs($user)->putJson(route('profile.update'), [
            'name_kana' => '山田 太郎',
        ])->assertForbidden();

        $this->assertSame('山田太郎', $user->fresh()->profile?->name_kana);
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

    public function test_registry_user_redirects_to_profile_edit_from_show(): void
    {
        $viewer = User::factory()->create();
        AffiliationHistory::create([
            'user_id' => $viewer->id,
            'start_date' => '2024-01-01',
            'enrollment_status' => AffiliationHistory::STATUS_ENROLLED,
            'department' => '情報システム部',
            'section' => '事業IT推進課',
        ]);
        $target = User::factory()->create();

        $this->actingAs($viewer->fresh())
            ->get(route('users.profile.show', $target))
            ->assertRedirect(route('users.profile.edit', $target));
    }

    public function test_hr_section_redirects_to_profile_edit_from_show(): void
    {
        $viewer = User::factory()->create();
        AffiliationHistory::create([
            'user_id' => $viewer->id,
            'start_date' => '2024-01-01',
            'enrollment_status' => AffiliationHistory::STATUS_ENROLLED,
            'department' => '人事部',
            'section' => '人事課',
        ]);
        $target = User::factory()->create();

        $this->actingAs($viewer->fresh())
            ->get(route('users.profile.show', $target))
            ->assertRedirect(route('users.profile.edit', $target));
    }

    public function test_hr_section_edit_page_hides_languages_and_self_introduction(): void
    {
        $viewer = User::factory()->create();
        AffiliationHistory::create([
            'user_id' => $viewer->id,
            'start_date' => '2024-01-01',
            'enrollment_status' => AffiliationHistory::STATUS_ENROLLED,
            'department' => '人事部',
            'section' => '人事課',
        ]);
        $target = User::factory()->create();
        EmployeeProfile::create([
            'user_id' => $target->id,
            'languages' => '日本語',
            'self_introduction' => '自己紹介',
        ]);

        $this->actingAs($viewer->fresh())
            ->get(route('users.profile.edit', $target))
            ->assertOk()
            ->assertDontSee('話せる言語', false)
            ->assertDontSee('自己紹介文', false)
            ->assertDontSee('日本語', false)
            ->assertDontSee('自己紹介', false);
    }

    public function test_information_systems_edit_page_hides_languages_and_self_introduction(): void
    {
        $viewer = $this->userInDepartment('情報システム部');
        $target = User::factory()->create();
        EmployeeProfile::create([
            'user_id' => $target->id,
            'languages' => '日本語',
            'self_introduction' => '自己紹介',
        ]);

        $this->actingAs($viewer)
            ->get(route('users.profile.edit', $target))
            ->assertOk()
            ->assertDontSee('話せる言語', false)
            ->assertDontSee('自己紹介文', false);
    }

    public function test_general_affairs_edit_page_hides_languages_and_self_introduction(): void
    {
        $viewer = $this->userInDepartment('経理部', '総務課');
        $target = User::factory()->create();
        EmployeeProfile::create([
            'user_id' => $target->id,
            'languages' => '日本語',
            'self_introduction' => '自己紹介',
        ]);

        $this->actingAs($viewer)
            ->get(route('users.profile.edit', $target))
            ->assertOk()
            ->assertDontSee('話せる言語', false)
            ->assertDontSee('自己紹介文', false);
    }

    public function test_hr_section_cannot_update_languages_via_json_request(): void
    {
        $viewer = $this->userInDepartment('人事部', '人事課');
        $target = User::factory()->create();
        EmployeeProfile::create([
            'user_id' => $target->id,
            'languages' => '日本語',
        ]);

        $this->actingAs($viewer)->putJson(route('users.profile.update', $target), [
            'languages' => '日本語,英語',
        ])->assertForbidden();

        $this->assertSame('日本語', $target->fresh()->profile?->languages);
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

    public function test_show_page_hides_inline_edit_markers_for_self(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('profile.show'))
            ->assertOk()
            ->assertDontSee('data-profile-inline-edit', false)
            ->assertDontSee('話せる言語', false)
            ->assertDontSee('自己紹介文', false)
            ->assertDontSee('写真登録', false);
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
            ->assertJsonPath('fields.employee_id.value', '20002')
            ->assertJsonPath('profile_urls.update', route('users.profile.update', '20002'))
            ->assertJsonPath('profile_urls.show', route('users.profile.show', '20002'))
            ->assertJsonPath('profile_urls.edit', route('users.profile.edit', '20002'));

        $target->refresh();
        $this->assertSame('new@careearth.info', $target->email);
        $this->assertSame('20002', $target->employee_id);
    }

    public function test_profile_form_save_works_after_employee_id_is_changed(): void
    {
        $viewer = $this->userInDepartment('情報システム部');
        $target = User::factory()->create([
            'employee_id' => '10001',
        ]);
        EmployeeProfile::create([
            'user_id' => $target->id,
            'name_kana' => '変更前',
        ]);

        $this->actingAs($viewer)->putJson(route('users.profile.update', '10001'), [
            'employee_id' => '20002',
        ])->assertOk();

        $this->actingAs($viewer)
            ->put(route('users.profile.update', '20002'), [
                'name_kana' => '変更後',
            ])
            ->assertRedirect(route('users.profile.edit', '20002'));

        $this->assertSame('変更後', $target->fresh()->profile?->name_kana);
    }

    public function test_profile_update_with_stale_employee_id_in_url_returns_not_found(): void
    {
        $viewer = $this->userInDepartment('情報システム部');
        $target = User::factory()->create([
            'employee_id' => '10001',
        ]);

        $this->actingAs($viewer)->putJson(route('users.profile.update', '10001'), [
            'employee_id' => '20002',
        ])->assertOk();

        $this->actingAs($viewer)
            ->put(route('users.profile.update', '10001'), [
                'name_kana' => '失敗',
            ])
            ->assertNotFound();
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

    public function test_information_systems_cannot_set_duplicate_employee_id(): void
    {
        $viewer = $this->userInDepartment('情報システム部');
        User::factory()->create(['employee_id' => '99999']);
        $target = User::factory()->create(['employee_id' => '10001']);

        $response = $this->actingAs($viewer)->putJson(route('users.profile.update', $target), [
            'employee_id' => '99999',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['employee_id']);

        $this->assertSame('10001', $target->fresh()->employee_id);
    }

    public function test_information_systems_cannot_set_non_five_digit_employee_id(): void
    {
        $viewer = $this->userInDepartment('情報システム部');
        $target = User::factory()->create(['employee_id' => '10001']);

        $response = $this->actingAs($viewer)->putJson(route('users.profile.update', $target), [
            'employee_id' => '255',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['employee_id']);

        $this->assertSame('10001', $target->fresh()->employee_id);
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

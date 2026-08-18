<?php

namespace Tests\Feature;

use App\Models\AffiliationHistory;
use App\Models\EmployeeHrDetail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmployeeItDeviceListTest extends TestCase
{
    use RefreshDatabase;

    public function test_information_systems_user_can_view_it_device_list(): void
    {
        $viewer = $this->informationSystemsUser();

        $employee = User::factory()->create([
            'employee_id' => '2001',
            'email' => 'device_user@careearth.info',
            'last_name' => 'デバイス',
            'first_name' => '太郎',
        ]);

        AffiliationHistory::create([
            'user_id' => $employee->id,
            'start_date' => '2024-01-01',
            'enrollment_status' => AffiliationHistory::STATUS_ENROLLED,
            'company' => 'CareEarth',
            'department' => '不動産部',
            'section' => '賃貸課',
            'position' => '正社員',
            'location' => '大阪',
        ]);

        EmployeeHrDetail::create([
            'user_id' => $employee->id,
            'employment_status' => '在籍',
            'employment_type' => '正社員',
            'phone' => '06-1234-5678',
            'has_pc' => true,
            'has_mobile' => false,
            'pc_manufacturer' => 'Dynabook',
            'pc_model' => 'R73',
        ]);

        $this->actingAs($viewer)
            ->get(route('it-devices.index'))
            ->assertOk()
            ->assertSee('情シスデバイス一覧', false)
            ->assertSee('2001', false)
            ->assertSee('デバイス 太郎', false)
            ->assertSee('device_user@careearth.info', false)
            ->assertSee('大阪', false)
            ->assertSee('不動産部', false)
            ->assertSee('06-1234-5678', false)
            ->assertSee('aria-label="PC"', false)
            ->assertSee('aria-label="モバイル"', false)
            ->assertSee('it-device-modal', false);

        $this->actingAs($viewer)
            ->get(route('it-devices.show', $employee))
            ->assertOk()
            ->assertSee('IT・デバイス', false)
            ->assertSee('Dynabook', false)
            ->assertSee('PCの型番', false)
            ->assertDontSee('基本情報・個人情報', false);
    }

    public function test_information_systems_user_can_update_it_device_fields(): void
    {
        $viewer = $this->informationSystemsUser();

        $employee = User::factory()->create([
            'email' => 'update-device@careearth.info',
        ]);

        AffiliationHistory::create([
            'user_id' => $employee->id,
            'start_date' => '2024-01-01',
            'enrollment_status' => AffiliationHistory::STATUS_ENROLLED,
            'company' => 'CareEarth',
            'department' => '通信部',
        ]);

        EmployeeHrDetail::create([
            'user_id' => $employee->id,
            'employment_status' => '在籍',
            'has_pc' => false,
        ]);

        $this->actingAs($viewer)
            ->put(route('it-devices.update', $employee), [
                'status' => '在籍',
                'has_pc' => '1',
                'pc_manufacturer' => 'HP',
                'pc_model' => 'ProBook',
                'mac_address' => 'AA:BB:CC:DD:EE:FF',
                'has_mobile' => '0',
                'setup_completed' => '0',
                'device_collected' => '0',
                'microsoft_account_removed' => '0',
                'gws_account_removed' => '0',
                'slack_account_removed' => '0',
            ])
            ->assertRedirect(route('it-devices.index', ['status' => '在籍']));

        $employee->refresh();
        $this->assertTrue($employee->hrDetail?->has_pc);
        $this->assertSame('HP', $employee->hrDetail?->pc_manufacturer);
        $this->assertSame('ProBook', $employee->hrDetail?->pc_model);
    }

    public function test_regular_employee_cannot_view_it_device_list(): void
    {
        $user = $this->userInDepartment('通信部');

        $this->actingAs($user)
            ->get(route('it-devices.index'))
            ->assertForbidden();
    }

    public function test_hr_department_cannot_view_it_device_list(): void
    {
        $user = $this->userInDepartment('人事部');

        $this->actingAs($user)
            ->get(route('it-devices.index'))
            ->assertForbidden();
    }

    public function test_keyword_search_filters_results(): void
    {
        $viewer = $this->informationSystemsUser();

        $match = User::factory()->create([
            'email' => 'match-device@careearth.info',
            'last_name' => '検索',
            'first_name' => 'ヒット',
        ]);
        AffiliationHistory::create([
            'user_id' => $match->id,
            'start_date' => '2024-01-01',
            'enrollment_status' => AffiliationHistory::STATUS_ENROLLED,
            'company' => 'CareEarth',
            'department' => '情報システム部',
            'location' => '東京',
        ]);
        EmployeeHrDetail::create([
            'user_id' => $match->id,
            'employment_status' => '在籍',
            'has_pc' => true,
        ]);

        $other = User::factory()->create([
            'email' => 'other-device@careearth.info',
            'last_name' => '別',
            'first_name' => 'ユーザー',
        ]);
        AffiliationHistory::create([
            'user_id' => $other->id,
            'start_date' => '2024-01-01',
            'enrollment_status' => AffiliationHistory::STATUS_ENROLLED,
            'company' => 'CareEarth',
            'department' => '通信部',
        ]);
        EmployeeHrDetail::create([
            'user_id' => $other->id,
            'employment_status' => '在籍',
        ]);

        $this->actingAs($viewer)
            ->get(route('it-devices.index', ['keyword' => 'ヒット']))
            ->assertOk()
            ->assertSee('検索 ヒット', false)
            ->assertDontSee('別 ユーザー', false);
    }

    public function test_dashboard_link_opens_in_new_tab(): void
    {
        $user = $this->informationSystemsUser();

        $this->actingAs($user)
            ->get(route('dashboard', ['tab' => 'common']))
            ->assertOk()
            ->assertSee('target="_blank"', false)
            ->assertSee('rel="noopener noreferrer"', false);
    }

    private function informationSystemsUser(): User
    {
        $user = User::factory()->create([
            'email' => 'is_viewer@careearth.info',
        ]);

        AffiliationHistory::create([
            'user_id' => $user->id,
            'start_date' => '2024-01-01',
            'enrollment_status' => AffiliationHistory::STATUS_ENROLLED,
            'company' => 'CareEarth',
            'department' => '情報システム部',
            'section' => '事業IT推進課',
            'position' => '正社員',
        ]);

        return $user;
    }

    private function userInDepartment(string $department): User
    {
        $user = User::factory()->create();

        AffiliationHistory::create([
            'user_id' => $user->id,
            'start_date' => '2024-01-01',
            'enrollment_status' => AffiliationHistory::STATUS_ENROLLED,
            'company' => 'CareEarth',
            'department' => $department,
            'position' => '正社員',
        ]);

        return $user;
    }
}

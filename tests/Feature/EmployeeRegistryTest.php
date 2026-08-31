<?php

namespace Tests\Feature;

use App\Models\AffiliationHistory;
use App\Models\EmployeeHrDetail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class EmployeeRegistryTest extends TestCase
{
    use RefreshDatabase;

    public function test_information_systems_user_sees_registry_links(): void
    {
        $user = $this->userInAffiliation('情報システム部', '事業IT推進課');

        $this->actingAs($user)
            ->get(route('employees.index'))
            ->assertOk()
            ->assertSee('新規登録', false)
            ->assertSee(route('employees.create'), false)
            ->assertSee('情報システム部・人事部人事課のみ', false);
    }

    public function test_hr_section_user_sees_registry_links(): void
    {
        $user = $this->userInAffiliation('人事部', '人事課');

        $this->actingAs($user)
            ->get(route('employees.index'))
            ->assertOk()
            ->assertSee('新規登録', false)
            ->assertDontSee('社員追加 CSV', false);
    }

    public function test_hr_department_without_hr_section_cannot_manage_registry(): void
    {
        $user = $this->userInAffiliation('人事部', '総務課');

        $this->actingAs($user)
            ->get(route('employees.create'))
            ->assertForbidden();
    }

    public function test_other_department_cannot_manage_registry(): void
    {
        $user = $this->userInAffiliation('通信部', '営業課');

        $this->actingAs($user)
            ->get(route('employees.index'))
            ->assertOk()
            ->assertDontSee('新規登録', false);

        $this->actingAs($user)
            ->get(route('employees.create'))
            ->assertForbidden();
    }

    public function test_registry_user_can_create_employee(): void
    {
        $admin = $this->userInAffiliation('情報システム部', '事業IT推進課');

        $response = $this->actingAs($admin)
            ->post(route('employees.store'), [
                'name' => '山田 太郎',
                'email' => 'taro_yamada@careearth.info',
                'password' => 'password123',
                'password_confirmation' => 'password123',
                'employee_id' => '10999',
                'department' => '通信事業部',
                'location' => '大阪',
                'employment_type' => '正社員',
            ]);

        $response->assertRedirect(route('employees.create'));
        $response->assertSessionHas('success', '社員を登録しました。');

        $created = User::query()->where('email', 'taro_yamada@careearth.info')->first();

        $this->assertNotNull($created);
        $this->assertSame('10999', $created->employee_id);
        $this->assertSame('山田', $created->last_name);
        $this->assertSame('太郎', $created->first_name);
        $this->assertTrue($created->must_change_password);

        $affiliation = $created->currentAffiliation();
        $this->assertSame('通信事業部', $affiliation?->department);
        $this->assertSame('大阪', $affiliation?->location);

        $this->assertSame('正社員', $created->hrDetail?->employment_type);
        $this->assertSame('在籍', $created->hrDetail?->employment_status);
    }

    public function test_registry_user_can_create_employee_with_section(): void
    {
        $admin = $this->userInAffiliation('情報システム部', '事業IT推進課');

        $this->actingAs($admin)
            ->post(route('employees.store'), [
                'name' => '佐藤 花子',
                'email' => 'hanako_sato@careearth.info',
                'password' => 'password123',
                'password_confirmation' => 'password123',
                'employee_id' => '10988',
                'department' => '人事部',
                'section' => '人事課',
                'location' => '東京',
                'employment_type' => '正社員',
            ])
            ->assertRedirect(route('employees.create'));

        $created = User::query()->where('email', 'hanako_sato@careearth.info')->firstOrFail();

        $this->assertSame('人事部', $created->currentAffiliation()?->department);
        $this->assertSame('人事課', $created->currentAffiliation()?->section);
        $this->assertSame('人事課', $created->hrDetail?->section_primary);
        $this->assertTrue($created->isHrSection());
    }

    public function test_store_rejects_invalid_section(): void
    {
        $admin = $this->userInAffiliation('情報システム部', '事業IT推進課');

        $this->actingAs($admin)
            ->post(route('employees.store'), [
                'name' => '山田 太郎',
                'email' => 'invalid_section@careearth.info',
                'password' => 'password123',
                'password_confirmation' => 'password123',
                'employee_id' => '10987',
                'department' => '通信事業部',
                'section' => 'ベトナム支店',
                'location' => '大阪',
                'employment_type' => '正社員',
            ])
            ->assertSessionHasErrors(['section']);

        $this->assertNull(User::query()->where('email', 'invalid_section@careearth.info')->first());
    }

    public function test_create_form_shows_section_select_without_departments(): void
    {
        $admin = $this->userInAffiliation('情報システム部', '事業IT推進課');

        $this->actingAs($admin)
            ->get(route('employees.create'))
            ->assertOk()
            ->assertSee('name="section"', false)
            ->assertSee('>人事課</option>', false);
    }

    public function test_registry_user_can_update_employee(): void
    {
        $admin = $this->userInAffiliation('人事部', '人事課');

        $employee = User::factory()->create([
            'email' => 'existing@careearth.info',
            'employee_id' => '10001',
            'last_name' => '旧',
            'first_name' => '名前',
            'name' => '旧 名前',
        ]);
        AffiliationHistory::create([
            'user_id' => $employee->id,
            'start_date' => '2024-01-01',
            'enrollment_status' => AffiliationHistory::STATUS_ENROLLED,
            'department' => '食品部',
            'location' => '東京',
        ]);
        EmployeeHrDetail::create([
            'user_id' => $employee->id,
            'employment_type' => 'アルバイト',
            'employment_status' => '在籍',
        ]);

        $response = $this->actingAs($admin)
            ->put(route('employees.update', $employee), [
                'name' => '新井 花子',
                'email' => 'hanako_arai@careearth.info',
                'employee_id' => '10002',
                'department' => '人事部',
                'location' => '大阪',
                'employment_type' => '正社員',
            ]);

        $employee->refresh();
        $response->assertRedirect(route('employees.edit', $employee));
        $this->assertSame('新井', $employee->last_name);
        $this->assertSame('花子', $employee->first_name);
        $this->assertSame('10002', $employee->employee_id);
        $this->assertSame('hanako_arai@careearth.info', $employee->email);
        $this->assertSame('人事部', $employee->currentAffiliation()?->department);
        $this->assertSame('大阪', $employee->currentAffiliation()?->location);
        $this->assertSame('正社員', $employee->hrDetail?->employment_type);
    }

    public function test_edit_page_shows_edit_form(): void
    {
        $admin = $this->userInAffiliation('情報システム部', '事業IT推進課');
        $employee = User::factory()->create([
            'email' => 'sample@careearth.info',
            'employee_id' => '10055',
        ]);

        $this->actingAs($admin)
            ->get(route('employees.edit', $employee))
            ->assertOk()
            ->assertSee('社員編集', false)
            ->assertSee('sample@careearth.info', false)
            ->assertSee('10055', false);
    }

    public function test_store_rejects_name_without_space(): void
    {
        $admin = $this->userInAffiliation('情報システム部', '事業IT推進課');

        $this->actingAs($admin)
            ->post(route('employees.store'), [
                'name' => '山田太郎',
                'email' => 'invalid_name@careearth.info',
                'password' => 'password123',
                'password_confirmation' => 'password123',
                'employee_id' => '10998',
                'department' => '通信事業部',
                'location' => '大阪',
                'employment_type' => '正社員',
            ])
            ->assertSessionHasErrors(['name']);

        $this->assertNull(User::query()->where('email', 'invalid_name@careearth.info')->first());
    }

    public function test_store_rejects_non_numeric_employee_id(): void
    {
        $admin = $this->userInAffiliation('情報システム部', '事業IT推進課');

        $this->actingAs($admin)
            ->post(route('employees.store'), [
                'name' => '山田 太郎',
                'email' => 'invalid_id@careearth.info',
                'password' => 'password123',
                'password_confirmation' => 'password123',
                'employee_id' => '10A99',
                'department' => '通信事業部',
                'location' => '大阪',
                'employment_type' => '正社員',
            ])
            ->assertSessionHasErrors(['employee_id']);

        $this->assertNull(User::query()->where('email', 'invalid_id@careearth.info')->first());
    }

    public function test_store_rejects_blank_department(): void
    {
        $admin = $this->userInAffiliation('情報システム部', '事業IT推進課');

        $this->actingAs($admin)
            ->post(route('employees.store'), [
                'name' => '山田 太郎',
                'email' => 'blank_dept@careearth.info',
                'password' => 'password123',
                'password_confirmation' => 'password123',
                'employee_id' => '10997',
                'department' => '',
                'location' => '大阪',
                'employment_type' => '正社員',
            ])
            ->assertSessionHasErrors(['department']);

        $this->assertNull(User::query()->where('email', 'blank_dept@careearth.info')->first());
    }

    public function test_store_rejects_invalid_department(): void
    {
        $admin = $this->userInAffiliation('情報システム部', '事業IT推進課');

        $this->actingAs($admin)
            ->post(route('employees.store'), [
                'name' => '山田 太郎',
                'email' => 'invalid_dept@careearth.info',
                'password' => 'password123',
                'password_confirmation' => 'password123',
                'employee_id' => '10994',
                'department' => '通信部',
                'location' => '大阪',
                'employment_type' => '正社員',
            ])
            ->assertSessionHasErrors(['department']);

        $this->assertNull(User::query()->where('email', 'invalid_dept@careearth.info')->first());
    }

    public function test_store_rejects_department_not_in_list(): void
    {
        $admin = $this->userInAffiliation('情報システム部', '事業IT推進課');

        $this->actingAs($admin)
            ->post(route('employees.store'), [
                'name' => '山田 太郎',
                'email' => 'section_only@careearth.info',
                'password' => 'password123',
                'password_confirmation' => 'password123',
                'employee_id' => '10993',
                'department' => '営業1課',
                'location' => '大阪',
                'employment_type' => '正社員',
            ])
            ->assertSessionHasErrors(['department']);

        $this->assertNull(User::query()->where('email', 'section_only@careearth.info')->first());
    }

    public function test_store_rejects_duplicate_employee_id(): void
    {
        $admin = $this->userInAffiliation('情報システム部', '事業IT推進課');
        User::factory()->create(['employee_id' => '10996']);

        $this->actingAs($admin)
            ->post(route('employees.store'), [
                'name' => '山田 太郎',
                'email' => 'duplicate_id@careearth.info',
                'password' => 'password123',
                'password_confirmation' => 'password123',
                'employee_id' => '10996',
                'department' => '通信事業部',
                'location' => '大阪',
                'employment_type' => '正社員',
            ])
            ->assertSessionHasErrors(['employee_id'])
            ->assertSessionHasErrors([
                'employee_id' => 'この社員IDは既に使用されています。',
            ]);

        $this->assertNull(User::query()->where('email', 'duplicate_id@careearth.info')->first());
    }

    public function test_update_rejects_duplicate_employee_id(): void
    {
        $admin = $this->userInAffiliation('情報システム部', '事業IT推進課');
        User::factory()->create(['employee_id' => '10991']);
        $employee = User::factory()->create([
            'email' => 'update_target@careearth.info',
            'employee_id' => '10990',
        ]);
        AffiliationHistory::create([
            'user_id' => $employee->id,
            'start_date' => '2024-01-01',
            'enrollment_status' => AffiliationHistory::STATUS_ENROLLED,
            'department' => '通信事業部',
            'location' => '大阪',
        ]);

        $this->actingAs($admin)
            ->put(route('employees.update', $employee), [
                'name' => '山田 太郎',
                'email' => 'update_target@careearth.info',
                'employee_id' => '10991',
                'department' => '通信事業部',
                'location' => '大阪',
                'employment_type' => '正社員',
            ])
            ->assertSessionHasErrors(['employee_id']);

        $this->assertSame('10990', $employee->fresh()->employee_id);
    }

    public function test_create_form_shows_department_select(): void
    {
        $admin = $this->userInAffiliation('情報システム部', '事業IT推進課');

        $this->actingAs($admin)
            ->get(route('employees.create'))
            ->assertOk()
            ->assertSee('name="department"', false)
            ->assertSee('通信事業部', false)
            ->assertSee('経営企画室', false)
            ->assertSee('ベトナム支店', false);
    }

    public function test_create_form_restricts_employee_id_input_to_digits(): void
    {
        $admin = $this->userInAffiliation('情報システム部', '事業IT推進課');

        $this->actingAs($admin)
            ->get(route('employees.create'))
            ->assertOk()
            ->assertSee('oninput="this.value = this.value.replace(/\\D/g, \'\').slice(0, 5)"', false);
    }

    public function test_edit_form_keeps_legacy_department_option(): void
    {
        $admin = $this->userInAffiliation('情報システム部', '事業IT推進課');
        $employee = User::factory()->create([
            'email' => 'legacy_dept@careearth.info',
            'employee_id' => '10077',
        ]);
        AffiliationHistory::create([
            'user_id' => $employee->id,
            'start_date' => '2024-01-01',
            'enrollment_status' => AffiliationHistory::STATUS_ENROLLED,
            'department' => '食品部',
            'location' => '大阪',
        ]);

        $this->actingAs($admin)
            ->get(route('employees.edit', $employee))
            ->assertOk()
            ->assertSee('value="食品部"', false);
    }

    public function test_hr_create_form_hides_password_fields(): void
    {
        $hrUser = $this->userInAffiliation('人事部', '人事課');

        $this->actingAs($hrUser)
            ->get(route('employees.create'))
            ->assertOk()
            ->assertDontSee('name="password"', false)
            ->assertDontSee('name="password_confirmation"', false);
    }

    public function test_information_systems_create_form_shows_password_fields(): void
    {
        $admin = $this->userInAffiliation('情報システム部', '事業IT推進課');

        $this->actingAs($admin)
            ->get(route('employees.create'))
            ->assertOk()
            ->assertSee('name="password"', false)
            ->assertSee('name="password_confirmation"', false);
    }

    public function test_hr_user_can_create_employee_with_default_password(): void
    {
        $hrUser = $this->userInAffiliation('人事部', '人事課');

        $this->actingAs($hrUser)
            ->post(route('employees.store'), [
                'name' => '佐藤 次郎',
                'email' => 'jiro_sato@careearth.info',
                'password' => 'custom-secret',
                'password_confirmation' => 'custom-secret',
                'employee_id' => '10995',
                'department' => '人事部',
                'location' => '東京',
                'employment_type' => '正社員',
            ])
            ->assertRedirect(route('employees.create'));

        $created = User::query()->where('email', 'jiro_sato@careearth.info')->first();

        $this->assertNotNull($created);
        $this->assertTrue(Hash::check(User::DEFAULT_REGISTRY_PASSWORD, (string) $created->password));
        $this->assertTrue($created->must_change_password);
    }

    public function test_create_form_shows_extended_fields(): void
    {
        $admin = $this->userInAffiliation('情報システム部', '事業IT推進課');

        $this->actingAs($admin)
            ->get(route('employees.create'))
            ->assertOk()
            ->assertSee('name="name_kana"', false)
            ->assertSee('name="english_name"', false)
            ->assertSee('name="abbreviated_name"', false)
            ->assertSee('name="gender"', false)
            ->assertSee('name="nationality"', false)
            ->assertSee('name="joined_at"', false)
            ->assertSee('name="remarks"', false);
    }

    public function test_registry_user_can_create_employee_with_extended_fields(): void
    {
        $admin = $this->userInAffiliation('情報システム部', '事業IT推進課');

        $this->actingAs($admin)
            ->post(route('employees.store'), [
                'name' => '田中 一郎',
                'email' => 'ichiro_tanaka@careearth.info',
                'password' => 'password123',
                'password_confirmation' => 'password123',
                'employee_id' => '10992',
                'department' => '通信事業部',
                'location' => '大阪',
                'employment_type' => '正社員',
                'name_kana' => 'タナカ イチロウ',
                'english_name' => 'Ichiro Tanaka',
                'abbreviated_name' => '田一',
                'gender' => '男',
                'nationality' => '日本',
                'joined_at' => '2026-04-01',
                'remarks' => '新卒入社予定',
            ])
            ->assertRedirect(route('employees.create'));

        $created = User::query()->where('email', 'ichiro_tanaka@careearth.info')->firstOrFail();

        $this->assertSame('タナカ イチロウ', $created->profile?->name_kana);
        $this->assertSame('Ichiro Tanaka', $created->profile?->english_name);
        $this->assertSame('田一', $created->profile?->abbreviated_name);
        $this->assertSame('日本', $created->profile?->nationality);
        $this->assertSame('2026-04-01', $created->profile?->joined_at?->toDateString());
        $this->assertSame('男', $created->hrDetail?->gender);
        $this->assertSame('タナカ イチロウ', $created->hrDetail?->name_kana_fullwidth);
        $this->assertSame('新卒入社予定', $created->hrDetail?->remarks);
        $this->assertSame('2026-04-01', $created->currentAffiliation()?->start_date?->toDateString());
    }

    /**
     * @return array{0: User}
     */
    private function userInAffiliation(string $department, string $section = '営業課'): User
    {
        $user = User::factory()->create();

        AffiliationHistory::create([
            'user_id' => $user->id,
            'start_date' => '2024-01-01',
            'enrollment_status' => AffiliationHistory::STATUS_ENROLLED,
            'department' => $department,
            'section' => $section,
            'location' => '大阪',
        ]);

        return $user->fresh();
    }
}

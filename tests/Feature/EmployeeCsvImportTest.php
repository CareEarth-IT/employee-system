<?php

namespace Tests\Feature;

use App\Models\AffiliationHistory;
use App\Models\EmployeeHrDetail;
use App\Models\EmployeeProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class EmployeeCsvImportTest extends TestCase
{
    use RefreshDatabase;

    private const JAPANESE_HEADER = '社員コード,社員名,社員略名,E-MAIL,所属1部門名,所属1役職名';

    private const HR_EXPORT_HEADER = '社員コード,社員名,社員略名,社員名カナ,E-MAIL,所属1部門コード,所属1部門名,所属1役職コード,所属1役職名,所属2部門コード,所属2部門名,所属2役職コード,所属2役職名,所属3部門コード,所属3部門名,所属3役職コード,所属3役職名,権限(コード),権限(名称),在職区分(名称)';

    public function test_information_systems_user_sees_import_link(): void
    {
        $user = $this->userInDepartment('情報システム部');

        $this->actingAs($user)
            ->get(route('employees.index'))
            ->assertOk()
            ->assertSee('社員追加 CSV', false)
            ->assertSee(route('employees.import.create'), false);
    }

    public function test_non_information_systems_user_does_not_see_import_link(): void
    {
        $user = $this->userInDepartment('通信部');

        $this->actingAs($user)
            ->get(route('employees.index'))
            ->assertOk()
            ->assertDontSee('社員追加 CSV', false);
    }

    public function test_non_information_systems_user_cannot_open_import_page(): void
    {
        $user = $this->userInDepartment('通信部');

        $this->actingAs($user)
            ->get(route('employees.import.create'))
            ->assertForbidden();
    }

    public function test_csv_import_creates_new_employee_only(): void
    {
        $importer = $this->userInDepartment('情報システム部');
        $existing = User::factory()->create([
            'email' => 'existing@careearth.info',
            'name' => '既存 太郎',
            'last_name' => '既存',
            'first_name' => '太郎',
            'employee_id' => '00100',
        ]);
        EmployeeProfile::create([
            'user_id' => $existing->id,
            'name_kana' => 'キゾンタロウ',
            'joined_at' => '2019-04-01',
            'nationality' => '日本',
        ]);
        AffiliationHistory::create([
            'user_id' => $existing->id,
            'start_date' => '2019-04-01',
            'enrollment_status' => AffiliationHistory::STATUS_ENROLLED,
            'department' => '人事部',
            'section' => '人事課',
            'location' => '大阪',
            'company' => 'CareEarth',
        ]);

        $csv = UploadedFile::fake()->createWithContent(
            'employees.csv',
            implode("\n", [
                self::JAPANESE_HEADER,
                'A001,変更 花子,変更,existing@careearth.info,営業部,一般',
                'A002,新規 次郎,新規,newhire@careearth.info,通信部 事業IT推進課,一般',
            ])."\n",
        );

        $this->actingAs($importer)
            ->post(route('employees.import.store'), ['csv' => $csv])
            ->assertRedirect(route('employees.import.create'))
            ->assertSessionHas('success');

        $existing->refresh();
        $this->assertSame('既存 太郎', $existing->name);
        $this->assertSame('人事部', $existing->currentAffiliation()?->department);

        $created = User::query()->where('email', 'newhire@careearth.info')->first();
        $this->assertNotNull($created);
        $this->assertSame('新規 次郎', $created->name);
        $this->assertSame('A002', $created->employee_id);
        $this->assertSame('通信部', $created->currentAffiliation()?->department);
        $this->assertSame('事業IT推進課', $created->currentAffiliation()?->section);
        $this->assertSame('一般', $created->currentAffiliation()?->position);
        $this->assertSame('在籍', $created->displayEmploymentStatus());
        $this->assertSame('新規', $created->profile?->abbreviated_name);
        $this->assertTrue($created->must_change_password);
        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('password', $created->password));

        $detail = EmployeeHrDetail::query()->where('user_id', $created->id)->first();
        $this->assertNotNull($detail);
        $this->assertSame('在籍', $detail->employment_status);
    }

    public function test_csv_import_ignores_unneeded_columns_from_hr_export(): void
    {
        $importer = $this->userInDepartment('情報システム部');

        $csv = UploadedFile::fake()->createWithContent(
            'employees.csv',
            implode("\n", [
                '社員コード,社員名,社員略称名,E-MAIL,所属1部門コード,所属1部門名称,所属1役職コード,所属役職',
                'B001,人事 花子,ジンジ,hr@careearth.info,HR01,人事部,POS01,部長',
            ])."\n",
        );

        $this->actingAs($importer)
            ->post(route('employees.import.store'), ['csv' => $csv])
            ->assertRedirect(route('employees.import.create'))
            ->assertSessionHas('success');

        $created = User::query()->where('email', 'hr@careearth.info')->first();
        $this->assertNotNull($created);
        $this->assertSame('B001', $created->employee_id);
        $this->assertSame('人事部', $created->currentAffiliation()?->department);
        $this->assertSame('部長', $created->currentAffiliation()?->position);
    }

    public function test_csv_import_accepts_full_hr_export_format(): void
    {
        $importer = $this->userInDepartment('情報システム部');

        $csv = UploadedFile::fake()->createWithContent(
            'employees.csv',
            implode("\n", [
                self::HR_EXPORT_HEADER,
                'C001,営業 太郎,エイギョウ,エイギョウタロウ,sales@careearth.info,S01,営業部,P01,一般,,,,,,,,,,,在籍',
            ])."\n",
        );

        $this->actingAs($importer)
            ->post(route('employees.import.store'), ['csv' => $csv])
            ->assertRedirect(route('employees.import.create'))
            ->assertSessionHas('success');

        $created = User::query()->where('email', 'sales@careearth.info')->first();
        $this->assertNotNull($created);
        $this->assertSame('C001', $created->employee_id);
        $this->assertSame('営業 太郎', $created->name);
        $this->assertSame('エイギョウ', $created->profile?->abbreviated_name);
        $this->assertSame('営業部', $created->currentAffiliation()?->department);
        $this->assertSame('一般', $created->currentAffiliation()?->position);
    }

    public function test_csv_import_rejects_invalid_employee_code(): void
    {
        $importer = $this->userInDepartment('情報システム部');

        $csv = UploadedFile::fake()->createWithContent(
            'employees.csv',
            implode("\n", [
                self::JAPANESE_HEADER,
                'bad code!,不正 番号,不正,invalid-id@careearth.info,通信部,一般',
            ])."\n",
        );

        $this->actingAs($importer)
            ->from(route('employees.import.create'))
            ->post(route('employees.import.store'), ['csv' => $csv])
            ->assertRedirect(route('employees.import.create'))
            ->assertSessionHasErrors('csv');

        $this->assertNull(User::query()->where('email', 'invalid-id@careearth.info')->first());
    }

    public function test_new_hire_must_change_password_on_first_login(): void
    {
        $user = User::factory()->create([
            'email' => 'newhire-login@careearth.info',
            'password' => 'password',
            'must_change_password' => true,
        ]);

        $this->post(route('login'), [
            'email' => $user->email,
            'password' => 'password',
        ])->assertRedirect(route('password.change'));

        $this->get(route('dashboard'))
            ->assertRedirect(route('password.change'));

        $this->post(route('password.change.update'), [
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertSessionHasErrors('password');

        $this->post(route('password.change.update'), [
            'password' => 'new-secure-pass',
            'password_confirmation' => 'new-secure-pass',
        ])->assertRedirect(route('dashboard'));

        $user->refresh();
        $this->assertFalse($user->must_change_password);
        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('new-secure-pass', $user->password));
    }

    private function userInDepartment(string $department): User
    {
        $user = User::factory()->create();

        AffiliationHistory::create([
            'user_id' => $user->id,
            'start_date' => '2020-01-01',
            'enrollment_status' => AffiliationHistory::STATUS_ENROLLED,
            'department' => $department,
            'section' => '事業IT推進課',
            'location' => '大阪',
            'company' => 'CareEarth',
        ]);

        return $user->fresh();
    }
}

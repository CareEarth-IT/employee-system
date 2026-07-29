<?php

namespace Tests\Feature;

use App\Models\AffiliationHistory;
use App\Models\EmployeeProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class EmployeeCsvImportTest extends TestCase
{
    use RefreshDatabase;

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
            'employee_id' => '100',
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
                'email,姓,名,部,課,役職,会社,拠点,社員番号',
                'existing@careearth.info,変更,花子,営業部,営業課,一般,CareEarth,東京,100',
                'newhire@careearth.info,新規,次郎,通信部,営業課,一般,CareEarth,大阪,200',
            ])."\n",
        );

        $this->actingAs($importer)
            ->post(route('employees.import.store'), ['csv' => $csv])
            ->assertRedirect(route('employees.import.create'))
            ->assertSessionHas('success');

        $existing->refresh();
        $this->assertSame('既存 太郎', $existing->name);
        $this->assertSame('人事部', $existing->currentAffiliation()?->department);
        $this->assertSame('2019-04-01', $existing->profile?->joined_at?->toDateString());

        $created = User::query()->where('email', 'newhire@careearth.info')->first();
        $this->assertNotNull($created);
        $this->assertSame('新規 次郎', $created->name);
        $this->assertSame('200', $created->employee_id);
        $this->assertSame('通信部', $created->currentAffiliation()?->department);
        $this->assertTrue($created->must_change_password);
        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('password', $created->password));
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

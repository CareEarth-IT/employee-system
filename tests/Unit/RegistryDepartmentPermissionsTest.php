<?php

namespace Tests\Unit;

use App\Models\AffiliationHistory;
use App\Models\User;
use App\Support\DashboardTab;
use App\Support\RegistryDepartmentOptions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class RegistryDepartmentPermissionsTest extends TestCase
{
    use RefreshDatabase;

    #[DataProvider('dashboardTabProvider')]
    public function test_registry_department_grants_expected_dashboard_tabs(
        string $registryDepartment,
        array $expectedTabs,
        array $forbiddenTabs,
    ): void {
        $user = $this->userInRegistryDepartment($registryDepartment);

        foreach ($expectedTabs as $tabKey) {
            $this->assertTrue(
                DashboardTab::canViewTab($user, $tabKey),
                "{$registryDepartment} should view {$tabKey}",
            );
        }

        foreach ($forbiddenTabs as $tabKey) {
            $this->assertFalse(
                DashboardTab::canViewTab($user, $tabKey),
                "{$registryDepartment} should not view {$tabKey}",
            );
        }
    }

    public function test_information_systems_department_gets_is_permissions(): void
    {
        $user = $this->userInRegistryDepartment('情報システム部');

        $this->assertTrue($user->isInformationSystems());
        $this->assertTrue($user->canEditDevelopmentRequest());
    }

    public function test_hr_department_gets_hr_permissions(): void
    {
        $user = $this->userInRegistryDepartment('人事部');

        $this->assertTrue($user->isHrDepartment());
    }

    public function test_accounting_department_gets_accounting_permissions(): void
    {
        $user = $this->userInRegistryDepartment('経理部');

        $this->assertTrue($user->isAccountingDepartment());
    }

    public function test_sales_department_gets_attendance_access(): void
    {
        $user = $this->userInRegistryDepartment('大阪営業部');

        $this->assertTrue($user->canViewAttendanceSection());
    }

    public function test_registry_create_persists_resolved_affiliation(): void
    {
        $admin = User::factory()->create();
        AffiliationHistory::create([
            'user_id' => $admin->id,
            'start_date' => '2024-01-01',
            'enrollment_status' => AffiliationHistory::STATUS_ENROLLED,
            'department' => '情報システム部',
            'section' => '事業IT推進課',
            'location' => '大阪',
        ]);

        $this->actingAs($admin->fresh())
            ->post(route('employees.store'), [
                'name' => '権限 太郎',
                'email' => 'permission_taro@careearth.info',
                'password' => 'password123',
                'password_confirmation' => 'password123',
                'employee_id' => '10992',
                'department' => '通信事業部',
                'location' => '大阪',
                'employment_type' => '正社員',
            ])
            ->assertRedirect(route('employees.create'));

        $created = User::query()->where('email', 'permission_taro@careearth.info')->firstOrFail();

        $this->assertSame('通信事業部', $created->currentAffiliation()?->department);
        $this->assertTrue(DashboardTab::canViewTab($created, 'telecom'));
        $this->assertSame('通信事業部', $created->hrDetail?->department_primary);
    }

    public static function dashboardTabProvider(): array
    {
        $businessTabs = ['dispatch', 'specified-skills', 'real-estate', 'food', 'telecom', 'beauty'];

        $cases = [
            '派遣事業部' => ['dispatch'],
            '不動産事業部' => ['real-estate'],
            '通信事業部' => ['telecom'],
            '特定技能事業部' => ['specified-skills'],
            '食品事業部' => ['food'],
            '美容事業部' => ['beauty'],
            '経理部' => ['specified-skills', 'real-estate'],
        ];

        $provider = [];

        foreach (RegistryDepartmentOptions::OPTIONS as $department) {
            $expected = $cases[$department] ?? [];
            $forbidden = array_values(array_diff($businessTabs, $expected));

            $provider[$department] = [$department, $expected, $forbidden];
        }

        return $provider;
    }

    private function userInRegistryDepartment(string $registryDepartment): User
    {
        $user = User::factory()->create();

        AffiliationHistory::create([
            'user_id' => $user->id,
            'start_date' => '2024-01-01',
            'enrollment_status' => AffiliationHistory::STATUS_ENROLLED,
            'department' => RegistryDepartmentOptions::resolveAffiliation($registryDepartment)['department'],
            'section' => RegistryDepartmentOptions::resolveAffiliation($registryDepartment)['section'],
            'location' => '大阪',
        ]);

        return $user->fresh();
    }
}

<?php

namespace Tests\Unit;

use App\Models\AffiliationHistory;
use App\Models\User;
use App\Services\MonthlyAffiliationSnapshotService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MonthlyAffiliationSnapshotTest extends TestCase
{
    use RefreshDatabase;

    public function test_accounting_department_can_view_monthly_snapshots(): void
    {
        $user = $this->userInDepartment('経理部');

        $this->assertTrue($user->canViewMonthlyAffiliationSnapshots());
    }

    public function test_information_systems_department_can_view_monthly_snapshots(): void
    {
        $user = $this->userInDepartment('情報システム部');

        $this->assertTrue($user->canViewMonthlyAffiliationSnapshots());
    }

    public function test_other_department_cannot_view_monthly_snapshots(): void
    {
        $user = $this->userInDepartment('通信部');

        $this->assertFalse($user->canViewMonthlyAffiliationSnapshots());
    }

    public function test_capture_stores_current_affiliation_for_all_users(): void
    {
        $user = User::factory()->create([
            'employee_id' => 'E001',
            'last_name' => '山田',
            'first_name' => '太郎',
            'name' => '山田 太郎',
        ]);

        AffiliationHistory::create([
            'user_id' => $user->id,
            'start_date' => '2024-01-01',
            'enrollment_status' => AffiliationHistory::STATUS_ENROLLED,
            'location' => '大阪',
            'department' => '通信部',
            'section' => '営業課',
        ]);

        $service = app(MonthlyAffiliationSnapshotService::class);
        $count = $service->capture('2026-06');

        $this->assertSame(1, $count);
        $this->assertDatabaseHas('monthly_affiliation_records', [
            'year_month' => '2026-06',
            'user_id' => $user->id,
            'employee_id' => 'E001',
            'location' => '大阪',
            'department' => '通信部',
            'section' => '営業課',
        ]);
    }

    private function userInDepartment(string $department): User
    {
        $user = User::factory()->create();

        AffiliationHistory::create([
            'user_id' => $user->id,
            'start_date' => '2024-01-01',
            'enrollment_status' => AffiliationHistory::STATUS_ENROLLED,
            'department' => $department,
            'location' => '大阪',
        ]);

        return $user->fresh();
    }
}

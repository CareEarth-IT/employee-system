<?php

namespace Tests\Unit;

use App\Models\AffiliationHistory;
use App\Models\EmployeeHrDetail;
use App\Models\User;
use App\Services\DriveStaffSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DriveStaffSyncServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.drive_app.url' => 'https://drive.example.test',
            'services.drive_app.sync_secret' => 'test-secret',
        ]);
    }

    public function test_build_payload_includes_staff_fields(): void
    {
        $user = $this->userInDepartment('通信部');
        EmployeeHrDetail::create([
            'user_id' => $user->id,
            'employment_type' => '正社員',
        ]);

        $this->assertSame([
            'email' => $user->email,
            'name' => $user->displayName(),
            'departmentName' => '通信部',
            'employmentType' => 'employee',
        ], app(DriveStaffSyncService::class)->buildPayload($user->fresh()));
    }

    public function test_sync_user_posts_to_drive_api(): void
    {
        Http::fake([
            'https://drive.example.test/api/integrations/employee-site/staff-profiles' => Http::response([
                'ok' => true,
                'synced' => 1,
                'failed' => 0,
                'results' => [['email' => 'sync@careearth.info', 'ok' => true]],
            ], 200),
        ]);

        $user = $this->userInDepartment('通信部');
        $this->assertTrue(app(DriveStaffSyncService::class)->syncUser($user->fresh()));

        Http::assertSent(fn ($request) => $request->method() === 'POST'
            && $request->url() === 'https://drive.example.test/api/integrations/employee-site/staff-profiles'
            && $request->hasHeader('X-Employee-Site-Secret', 'test-secret'));
    }

    private function userInDepartment(string $department): User
    {
        $user = User::factory()->create([
            'email' => 'sync@careearth.info',
            'last_name' => '山田',
            'first_name' => '太郎',
        ]);

        AffiliationHistory::create([
            'user_id' => $user->id,
            'start_date' => '2024-01-01',
            'enrollment_status' => AffiliationHistory::STATUS_ENROLLED,
            'department' => $department,
            'section' => '営業課',
            'location' => '大阪',
        ]);

        return $user->fresh();
    }
}

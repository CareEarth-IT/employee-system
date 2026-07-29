<?php

namespace Tests\Feature;

use App\Models\AffiliationHistory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DriveAppSyncTest extends TestCase
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

    public function test_company_car_tab_shows_drive_sync_links(): void
    {
        $user = $this->userInDepartment('通信部');

        $this->actingAs($user)
            ->get(route('dashboard', ['tab' => 'company-car']))
            ->assertOk()
            ->assertSee('社用車の初めて使用する方はこちら', false)
            ->assertSee('部署が変更された方はこちら', false);
    }

    public function test_dashboard_sync_button_posts_staff_profile(): void
    {
        $user = $this->userInDepartment('不動産部');

        Http::fake([
            'https://drive.example.test/api/integrations/employee-site/staff-profiles' => Http::response([
                'ok' => true,
                'synced' => 1,
                'failed' => 0,
                'results' => [['email' => $user->email, 'ok' => true]],
            ], 200),
        ]);

        $this->actingAs($user)
            ->from(route('dashboard', ['tab' => 'company-car']))
            ->post(route('drive-app.sync'))
            ->assertRedirect(route('dashboard', ['tab' => 'company-car']))
            ->assertSessionHas('success');
    }

    private function userInDepartment(string $department): User
    {
        $user = User::factory()->create(['email' => 'drive-user@careearth.info']);

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

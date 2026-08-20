<?php

namespace Tests\Unit;

use App\Models\AffiliationHistory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class RealEstatePortalProxyPathTest extends TestCase
{
    use RefreshDatabase;

    public function test_proxy_root_redirects_to_entry_path(): void
    {
        $user = $this->userInDepartment('不動産部');

        $this->actingAs($user)
            ->get('/realestate-portal')
            ->assertRedirect('/realestate-portal/home');
    }

    public function test_proxy_strips_duplicate_proxy_path_before_upstream_request(): void
    {
        config([
            'department_portals.real-estate.internal_url' => 'https://real-estate.example.test',
            'department_portals.real-estate.proxy_secret' => 'portal-shared-secret',
            'department_portals.real-estate.use_identity_token' => false,
        ]);

        Http::fake([
            'https://real-estate.example.test/*' => Http::response('<html>ok</html>', 200, ['Content-Type' => 'text/html']),
        ]);

        $user = $this->userInDepartment('不動産部');

        $this->actingAs($user)
            ->withHeader('Cookie', 'real_estate_portal_session=test-session')
            ->get('/realestate-portal/realestate-portal')
            ->assertOk();

        Http::assertSent(function ($request) {
            return $request->url() === 'https://real-estate.example.test/';
        });
    }

    private function userInDepartment(string $department): User
    {
        $user = User::factory()->create();

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

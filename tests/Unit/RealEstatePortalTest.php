<?php

namespace Tests\Unit;

use App\Models\AffiliationHistory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class RealEstatePortalTest extends TestCase
{
    use RefreshDatabase;

    public function test_real_estate_department_can_view_portal_link(): void
    {
        $user = $this->userInDepartment('不動産部');

        $this->assertTrue($user->isRealEstateDepartment());
        $this->assertTrue($user->canViewRealEstatePortalLink());
    }

    public function test_other_department_cannot_view_portal_link(): void
    {
        $user = $this->userInDepartment('通信部');

        $this->assertFalse($user->isRealEstateDepartment());
        $this->assertFalse($user->canViewRealEstatePortalLink());
    }

    public function test_dashboard_shows_real_estate_link_only_for_real_estate_department(): void
    {
        $realEstateUser = $this->userInDepartment('不動産部');
        $otherUser = $this->userInDepartment('通信部');

        $this->actingAs($realEstateUser)
            ->get(route('dashboard', ['tab' => 'real-estate']))
            ->assertOk()
            ->assertSee('不動産社内サイト', false)
            ->assertSee('/realestate-portal', false);

        $this->actingAs($otherUser)
            ->get(route('dashboard', ['tab' => 'common']))
            ->assertOk()
            ->assertDontSee('不動産社内サイト', false);
    }

    public function test_common_tab_shows_employee_list_link(): void
    {
        $user = $this->userInDepartment('通信部');

        $this->actingAs($user)
            ->get(route('dashboard', ['tab' => 'common']))
            ->assertOk()
            ->assertSee('社員一覧', false);
    }

    public function test_proxy_is_forbidden_for_non_real_estate_department(): void
    {
        Http::fake([
            '*' => Http::response('upstream', 418),
        ]);

        $otherUser = $this->userInDepartment('通信部');

        $this->assertFalse($otherUser->canViewRealEstatePortalLink());

        $this->actingAs($otherUser)
            ->get('/realestate-portal/applications/create')
            ->assertForbidden();

        Http::assertNothingSent();
    }

    public function test_proxy_forwards_shared_secret_header_when_configured(): void
    {
        config([
            'department_portals.real-estate.proxy_secret' => 'portal-shared-secret',
            'department_portals.real-estate.use_identity_token' => false,
        ]);

        Http::fake([
            '*' => Http::response('<html>ok</html>', 200, ['Content-Type' => 'text/html']),
        ]);

        $user = $this->userInDepartment('不動産部');

        $this->actingAs($user)
            ->get('/realestate-portal/applications/create')
            ->assertOk();

        Http::assertSent(function ($request) {
            return $request->hasHeader('X-Employee-Portal', '1')
                && $request->hasHeader('X-Employee-Portal-Proxy-Secret', 'portal-shared-secret')
                && ! $request->hasHeader('Authorization');
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

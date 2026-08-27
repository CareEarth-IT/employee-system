<?php

namespace Tests\Unit;

use App\Models\AffiliationHistory;
use App\Models\User;
use App\Support\DepartmentPortal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DepartmentPortalTest extends TestCase
{
    use RefreshDatabase;

    public function test_common_tab_has_no_portal_access(): void
    {
        $user = $this->userInDepartment('通信部');

        $this->assertFalse(DepartmentPortal::canAccess($user, 'common'));
        $this->assertNull(DepartmentPortal::entryUrl('common'));
    }

    public function test_department_member_can_access_configured_portal(): void
    {
        config(['department_portals.dispatch.internal_url' => 'https://dispatch.example.test']);

        $user = $this->userInDepartment('派遣事業部');

        $this->assertTrue($user->canViewDepartmentPortal('dispatch'));
        $this->assertSame(
            url('dispatch-portal'),
            $user->canViewDepartmentPortal('dispatch') ? DepartmentPortal::entryUrl('dispatch') : null,
        );
    }

    public function test_other_department_cannot_access_portal(): void
    {
        config(['department_portals.dispatch.internal_url' => 'https://dispatch.example.test']);

        $user = $this->userInDepartment('通信部');

        $this->assertFalse($user->canViewDepartmentPortal('dispatch'));
    }

    public function test_unconfigured_portal_is_not_accessible(): void
    {
        config(['department_portals.food.internal_url' => null]);

        $user = $this->userInDepartment('食品部');

        $this->assertFalse($user->canViewDepartmentPortal('food'));
    }

    public function test_dashboard_shows_department_portal_link_for_matching_tab(): void
    {
        config(['department_portals.dispatch.internal_url' => 'https://dispatch.example.test']);

        $user = $this->userInDepartment('派遣事業部');

        $this->actingAs($user)
            ->get(route('dashboard', ['tab' => 'dispatch']))
            ->assertOk()
            ->assertSee('派遣事業社内サイト', false);
    }

    public function test_dashboard_common_tab_shows_employee_list_not_portal_link(): void
    {
        $user = $this->userInDepartment('派遣事業部');

        $this->actingAs($user)
            ->get(route('dashboard', ['tab' => 'common']))
            ->assertOk()
            ->assertSee('社員一覧', false)
            ->assertDontSee('派遣事業社内サイト', false);
    }

    public function test_proxy_is_forbidden_for_other_department(): void
    {
        config(['department_portals.dispatch.internal_url' => 'https://dispatch.example.test']);

        $user = $this->userInDepartment('通信部');

        $this->actingAs($user)
            ->get('/dispatch-portal')
            ->assertForbidden();
    }

    public function test_real_estate_entry_url_uses_employee_proxy(): void
    {
        $this->assertSame(
            url('realestate-portal/home'),
            DepartmentPortal::entryUrl('real-estate'),
        );
    }

    public function test_real_estate_backward_compat_helper(): void
    {
        $user = $this->userInDepartment('不動産部');

        $this->assertTrue($user->canViewRealEstatePortalLink());
    }

    public function test_proxy_secret_disables_identity_token_when_not_configured(): void
    {
        config([
            'department_portals.real-estate.proxy_secret' => 'test-secret',
            'department_portals.real-estate.use_identity_token' => null,
        ]);

        $this->assertFalse(DepartmentPortal::shouldUseIdentityToken('real-estate'));
    }

    public function test_explicit_use_identity_token_overrides_proxy_secret(): void
    {
        config([
            'department_portals.real-estate.proxy_secret' => 'test-secret',
            'department_portals.real-estate.use_identity_token' => true,
        ]);

        $this->assertTrue(DepartmentPortal::shouldUseIdentityToken('real-estate'));
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

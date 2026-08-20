<?php

namespace Tests\Unit;

use App\Models\AffiliationHistory;
use App\Models\User;
use App\Support\DepartmentPortal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SpecifiedSkillsPortalTest extends TestCase
{
    use RefreshDatabase;

    public function test_specified_skills_department_can_access_portal_when_configured(): void
    {
        config([
            'department_portals.specified-skills.internal_url' => 'https://specified-skills.example.test',
        ]);

        $user = $this->userInDepartment('特定技能部');

        $this->assertTrue(DepartmentPortal::canAccess($user, 'specified-skills'));
        $this->assertSame(
            url('specified-skills-portal'),
            DepartmentPortal::entryUrl('specified-skills'),
        );
    }

    public function test_other_department_cannot_access_portal(): void
    {
        config([
            'department_portals.specified-skills.internal_url' => 'https://specified-skills.example.test',
        ]);

        $user = $this->userInDepartment('通信部');

        $this->assertFalse(DepartmentPortal::canAccess($user, 'specified-skills'));
    }

    public function test_accounting_section_can_access_portal(): void
    {
        config([
            'department_portals.specified-skills.internal_url' => 'https://specified-skills.example.test',
        ]);

        $user = $this->userInDepartment('経理部', '経理課');

        $this->assertTrue(DepartmentPortal::canAccess($user, 'specified-skills'));
    }

    public function test_dashboard_shows_portal_link_for_specified_skills_department(): void
    {
        config([
            'department_portals.specified-skills.internal_url' => 'https://specified-skills.example.test',
        ]);

        $user = $this->userInDepartment('特定技能部');

        $this->actingAs($user)
            ->get(route('dashboard', ['tab' => 'specified-skills']))
            ->assertOk()
            ->assertSee('特定技能社内サイト', false)
            ->assertSee('/specified-skills-portal', false);
    }

    public function test_proxy_forwards_to_upstream_with_secret(): void
    {
        config([
            'department_portals.specified-skills.internal_url' => 'https://specified-skills.example.test',
            'department_portals.specified-skills.proxy_secret' => 'portal-shared-secret',
            'department_portals.specified-skills.use_identity_token' => false,
        ]);

        Http::fake([
            'https://specified-skills.example.test/*' => Http::response('<html>ok</html>', 200, ['Content-Type' => 'text/html']),
        ]);

        $user = $this->userInDepartment('特定技能部');

        $this->actingAs($user)
            ->get('/specified-skills-portal/applicants.php')
            ->assertOk();

        Http::assertSent(function ($request) {
            return $request->url() === 'https://specified-skills.example.test/applicants.php'
                && $request->hasHeader('X-Employee-Portal', '1')
                && $request->hasHeader('X-Employee-Portal-Tab', 'specified-skills')
                && $request->hasHeader('X-Employee-Portal-Proxy-Secret', 'portal-shared-secret');
        });
    }

    private function userInDepartment(string $department, string $section = '業務課'): User
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

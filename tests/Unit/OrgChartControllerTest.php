<?php

namespace Tests\Unit;

use App\Models\AffiliationHistory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrgChartControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_view_org_chart(): void
    {
        $this->get(route('org-chart.show'))->assertRedirect(route('login'));
    }

    public function test_authenticated_user_can_view_org_chart(): void
    {
        $user = $this->userInDepartment('通信部');

        $this->actingAs($user)
            ->get(route('org-chart.show'))
            ->assertOk()
            ->assertHeader('Content-Type', 'text/html; charset=UTF-8');
    }

    public function test_authenticated_user_can_load_org_chart_stylesheet(): void
    {
        $user = $this->userInDepartment('通信部');

        $this->actingAs($user)
            ->get(route('org-chart.show', ['path' => 'resources/sheet.css']))
            ->assertOk()
            ->assertHeader('Content-Type', 'text/css; charset=UTF-8');
    }

    public function test_unknown_org_chart_path_returns_not_found(): void
    {
        $user = $this->userInDepartment('通信部');

        $this->actingAs($user)
            ->get(route('org-chart.show', ['path' => 'secret.html']))
            ->assertNotFound();
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

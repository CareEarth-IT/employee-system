<?php

namespace Tests\Feature;

use App\Models\EmployeeHrDetail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmployeeHrDetailOrgFormTest extends TestCase
{
    use RefreshDatabase;

    public function test_edit_form_shows_split_org_fields(): void
    {
        $hr = $this->userInAffiliation('人事部', '人事課');
        $target = User::factory()->create();
        EmployeeHrDetail::create([
            'user_id' => $target->id,
            'department_primary' => 'Food Sales部',
            'section_primary' => '法人チーム',
            'jurisdiction' => '大阪',
        ]);

        $this->actingAs($hr)
            ->get(route('users.profile.hr-detail.edit', $target))
            ->assertOk()
            ->assertSee('for="department_primary"', false)
            ->assertSee('for="section_primary"', false)
            ->assertSee('for="team_primary"', false)
            ->assertSee('課①', false)
            ->assertSee('チーム①', false);
    }

    public function test_hr_user_can_update_split_org_fields(): void
    {
        $hr = $this->userInAffiliation('人事部', '人事課');
        $target = User::factory()->create();
        EmployeeHrDetail::create([
            'user_id' => $target->id,
            'department_primary' => 'Food Sales部',
            'section_primary' => null,
            'jurisdiction' => '大阪',
        ]);

        $response = $this->actingAs($hr)
            ->put(route('users.profile.hr-detail.update', $target), [
                'department_primary' => 'Food Sales部',
                'section_primary' => '',
                'team_primary' => 'ECチーム',
                'jurisdiction' => '大阪',
            ]);

        $response->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame('ECチーム', $target->fresh()->hrDetail?->section_primary);
    }

    private function userInAffiliation(string $department, string $section): User
    {
        $user = User::factory()->create();

        \App\Models\AffiliationHistory::create([
            'user_id' => $user->id,
            'start_date' => '2024-01-01',
            'enrollment_status' => \App\Models\AffiliationHistory::STATUS_ENROLLED,
            'department' => $department,
            'section' => $section,
            'location' => '大阪',
        ]);

        return $user->fresh();
    }
}

<?php

namespace Tests\Feature;

use App\Models\AffiliationHistory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AffiliationOrgFieldsTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_form_shows_affiliation_org_fields(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('affiliations.create'))
            ->assertOk()
            ->assertSee('所属会社', false)
            ->assertSee('管轄', false)
            ->assertSee('for="department"', false)
            ->assertSee('for="section"', false)
            ->assertSee('課/チーム', false)
            ->assertDontSee('name="team"', false);
    }

    public function test_user_can_store_affiliation_with_combined_section(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('affiliations.store'), [
                'is_current' => '1',
                'start_date' => '2026-03-01',
                'company' => 'CareEarth',
                'location' => '大阪',
                'department' => 'Food Sales部',
                'section' => '法人チーム',
                'position' => '一般',
                'action' => 'save',
            ])
            ->assertRedirect(route('profile.edit'));

        $affiliation = $user->fresh()->currentAffiliation();

        $this->assertNotNull($affiliation);
        $this->assertSame('CareEarth', $affiliation->company);
        $this->assertSame('大阪', $affiliation->location);
        $this->assertSame('Food Sales部', $affiliation->department);
        $this->assertSame('法人チーム', $affiliation->section);
    }

    public function test_profile_table_shows_section_and_team_columns(): void
    {
        $user = User::factory()->create();
        AffiliationHistory::create([
            'user_id' => $user->id,
            'start_date' => '2026-01-01',
            'enrollment_status' => AffiliationHistory::STATUS_ENROLLED,
            'company' => 'CareEarth',
            'location' => '大阪',
            'department' => 'GR部（グローバル部）',
            'section' => 'GR-O_大阪,GR-O CS課 固定現場チーム_大阪',
            'position' => '一般',
        ]);

        $this->actingAs($user)
            ->get(route('profile.show'))
            ->assertOk()
            ->assertSee('>チーム</th>', false)
            ->assertSee('GR-O部', false)
            ->assertSee('固定現場チーム', false);
    }
}

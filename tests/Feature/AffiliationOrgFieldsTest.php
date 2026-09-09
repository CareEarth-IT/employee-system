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
            ->assertSee('name="team"', false)
            ->assertDontSee('課/チーム', false);
    }

    public function test_user_can_store_affiliation_with_legacy_food_sales_team(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('affiliations.store'), [
                'is_current' => '1',
                'start_date' => '2026-03-01',
                'company' => 'CareEarth',
                'location' => '大阪',
                'department' => 'Food Sales部',
                'team' => '法人チーム',
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

    public function test_hr_section_can_store_affiliation_with_gr_o_nested_team(): void
    {
        $hr = User::factory()->create();
        AffiliationHistory::create([
            'user_id' => $hr->id,
            'start_date' => '2024-01-01',
            'enrollment_status' => AffiliationHistory::STATUS_ENROLLED,
            'department' => '人事部',
            'section' => '人事課',
            'location' => '大阪',
        ]);

        $target = User::factory()->create();

        $this->actingAs($hr->fresh())
            ->post(route('users.affiliations.store', $target), [
                'is_current' => '1',
                'start_date' => '2026-03-01',
                'company' => 'CareEarth',
                'location' => '大阪',
                'department' => 'GR部（グローバル部）',
                'section' => 'GR-O部',
                'team' => '固定現場チーム',
                'position' => '一般',
                'action' => 'save',
            ])
            ->assertRedirect(route('users.profile.edit', $target));

        $affiliation = $target->fresh()->currentAffiliation();

        $this->assertSame(
            'GR-O_大阪,GR-O CS課 固定現場チーム_大阪',
            $affiliation?->section,
        );
    }

    public function test_user_can_store_affiliation_without_start_date(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('affiliations.store'), [
                'is_current' => '1',
                'company' => 'CareEarth',
                'location' => '大阪',
                'department' => '営業部',
                'section' => '営業1課',
                'position' => '一般',
                'action' => 'save',
            ])
            ->assertRedirect(route('profile.edit'));

        $affiliation = $user->fresh()->currentAffiliation();

        $this->assertNotNull($affiliation);
        $this->assertNull($affiliation->start_date);
        $this->assertSame('営業部', $affiliation->department);
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

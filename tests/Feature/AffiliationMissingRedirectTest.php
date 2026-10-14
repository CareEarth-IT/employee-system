<?php

namespace Tests\Feature;

use App\Models\AffiliationHistory;
use App\Models\User;
use App\Support\MissingAffiliationRedirect;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AffiliationMissingRedirectTest extends TestCase
{
    use RefreshDatabase;

    public function test_deleted_affiliation_edit_redirects_to_profile_instead_of_404(): void
    {
        $hr = User::factory()->create();
        AffiliationHistory::create([
            'user_id' => $hr->id,
            'start_date' => '2024-01-01',
            'enrollment_status' => AffiliationHistory::STATUS_ENROLLED,
            'department' => '人事部',
            'section' => '人事課',
        ]);

        $target = User::factory()->create();
        $affiliation = AffiliationHistory::create([
            'user_id' => $target->id,
            'start_date' => '2024-01-01',
            'enrollment_status' => AffiliationHistory::STATUS_ENROLLED,
            'department' => '営業部',
            'section' => '営業1課',
        ]);

        $affiliationId = $affiliation->id;

        $this->assertReplacesLocationTo(
            $this->actingAs($hr->fresh())->delete(route('affiliations.destroy', $affiliation)),
            route('users.profile.edit', $target),
        );

        $this->assertDatabaseMissing('affiliation_histories', ['id' => $affiliationId]);

        $this->actingAs($hr->fresh())
            ->withSession([MissingAffiliationRedirect::RETURN_USER_SESSION_KEY => $target->id])
            ->get(route('affiliations.edit', $affiliationId))
            ->assertRedirect(route('users.profile.edit', $target))
            ->assertSessionHas('error');
    }
}

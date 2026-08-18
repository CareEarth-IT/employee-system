<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserRouteKeyTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_profile_route_uses_employee_id(): void
    {
        $viewer = User::factory()->create(['employee_id' => '100']);
        $target = User::factory()->create(['employee_id' => '255']);

        $url = route('users.profile.show', $target);

        $this->assertStringContainsString('/users/255/profile', $url);
        $this->assertStringNotContainsString('/users/'.$target->id.'/profile', $url);

        $this->actingAs($viewer)
            ->get($url)
            ->assertOk();
    }

    public function test_legacy_primary_key_profile_url_still_resolves(): void
    {
        $viewer = User::factory()->create(['employee_id' => '100']);
        $target = User::factory()->create(['employee_id' => '255']);

        $this->actingAs($viewer)
            ->get('/users/'.$target->id.'/profile')
            ->assertOk();
    }
}

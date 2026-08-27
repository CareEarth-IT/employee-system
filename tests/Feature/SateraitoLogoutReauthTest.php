<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\SateraitoSsoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

/**
 * @group sateraito
 */
class SateraitoLogoutReauthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('sateraito_sso.enabled', true);
        Config::set('sateraito_sso.secret', 'test-shared-secret');
        Config::set('sateraito_sso.entry_url', 'https://sateraito-apps-sso.appspot.com/a/careearth.info/sso/general?ssoid=CEEMPLOYEE01');
    }

    public function test_logout_redirects_to_logged_out_page(): void
    {
        $user = User::factory()->create([
            'email' => 'logout-user@careearth.info',
        ]);

        $this->actingAs($user)
            ->post(route('logout'))
            ->assertRedirect(route('portal.logged-out'));

        $this->get(route('portal.logged-out'))
            ->assertOk()
            ->assertSee('ログアウトしました', false)
            ->assertDontSee('社員ポータルへサインイン（SSO）', false)
            ->assertDontSee('セキュリティーブラウザから下のボタン', false);

        $this->assertGuest();
    }

    public function test_entry_url_is_built_from_domain_and_id_when_not_explicit(): void
    {
        Config::set('sateraito_sso.entry_url', null);
        Config::set('sateraito_sso.sso_domain', 'careearth.info');
        Config::set('sateraito_sso.sso_id', 'CEEMPLOYEE01');

        $url = app(SateraitoSsoService::class)->entryUrl();

        $this->assertSame(
            'https://sateraito-apps-sso.appspot.com/a/careearth.info/sso/general?ssoid=CEEMPLOYEE01',
            $url,
        );
    }
}

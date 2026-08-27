<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\PortalEntryGate;
use App\Services\SateraitoSsoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

/**
 * @group sateraito
 */
class PortalEntryGateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('portal_entry.require_sateraito', true);
        Config::set('portal_entry.grant_ttl_seconds', 900);
        Config::set('sateraito_sso.enabled', true);
        Config::set('sateraito_sso.secret', 'test-shared-secret');
        Config::set('sateraito_sso.params.email', 'mail');
        Config::set('sateraito_sso.params.timestamp', 'time');
        Config::set('sateraito_sso.params.signature', 'sign');
        Config::set('sateraito_sso.signature.enabled', true);
        Config::set('sateraito_sso.signature.algorithm', 'md5');
        Config::set('sateraito_sso.signature.secret_position', 'prefix');
        Config::set('sateraito_sso.signature.fields', ['email', 'timestamp']);
        Config::set('sateraito_sso.allowed_email_domains', ['careearth.info']);
        Config::set('sateraito_sso.max_age_seconds', 120);
    }

    public function test_direct_login_is_blocked_without_sateraito_entry(): void
    {
        $response = $this->get(route('login'));

        $response->assertRedirect(route('portal.entry-required'));
    }

    public function test_dashboard_is_blocked_without_sateraito_entry(): void
    {
        $response = $this->get(route('dashboard'));

        $response->assertRedirect(route('portal.entry-required'));
    }

    public function test_sso_failure_allows_password_login_after_valid_signature(): void
    {
        $email = 'missing@careearth.info';
        $timestamp = (string) time();
        $sign = app(SateraitoSsoService::class)->buildSignature([
            'email' => $email,
            'timestamp' => $timestamp,
        ]);

        $this->get(route('sso.sateraito', [
            'mail' => $email,
            'time' => $timestamp,
            'sign' => $sign,
        ]))->assertRedirect(route('login'));

        $this->get(route('login'))->assertOk();
    }

    public function test_sso_success_logs_in_without_visiting_login_page(): void
    {
        $user = User::factory()->create([
            'email' => 'sso-user@careearth.info',
            'must_change_password' => false,
        ]);

        $timestamp = (string) time();
        $sign = app(SateraitoSsoService::class)->buildSignature([
            'email' => $user->email,
            'timestamp' => $timestamp,
        ]);

        $this->get(route('sso.sateraito', [
            'mail' => $user->email,
            'time' => $timestamp,
            'sign' => $sign,
        ]))->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);
    }

    public function test_invalid_sso_does_not_grant_password_login(): void
    {
        $user = User::factory()->create([
            'email' => 'sso-invalid@careearth.info',
        ]);

        $this->get(route('sso.sateraito', [
            'mail' => $user->email,
            'time' => (string) time(),
            'sign' => 'invalid',
        ]))->assertRedirect(route('portal.entry-required'));

        $this->get(route('login'))->assertRedirect(route('portal.entry-required'));
    }

    public function test_login_allowed_with_sateraito_referer(): void
    {
        Config::set('portal_entry.allowed_referer_hosts', ['sateraito-apps-sso.appspot.com']);

        $response = $this->withHeader(
            'Referer',
            'https://sateraito-apps-sso.appspot.com/a/careearth.info/sso/general?ssoid=1'
        )->get(route('login'));

        $response->assertOk();
        $this->assertNotNull(session(PortalEntryGate::SESSION_KEY));
    }
}

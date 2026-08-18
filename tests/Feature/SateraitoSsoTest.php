<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\SateraitoSsoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class SateraitoSsoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

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
        Config::set('sateraito_sso.default_redirect', '/dashboard');
    }

    public function test_sso_logs_in_user_with_valid_get_request(): void
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

        $response = $this->get(route('sso.sateraito', [
            'mail' => $user->email,
            'time' => $timestamp,
            'sign' => $sign,
        ]));

        $response->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_sso_accepts_post_request(): void
    {
        $user = User::factory()->create([
            'email' => 'sso-post@careearth.info',
            'must_change_password' => false,
        ]);

        $timestamp = (string) time();
        $sign = app(SateraitoSsoService::class)->buildSignature([
            'email' => $user->email,
            'timestamp' => $timestamp,
        ]);

        $response = $this->post(route('sso.sateraito'), [
            'mail' => $user->email,
            'time' => $timestamp,
            'sign' => $sign,
        ]);

        $response->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_sso_rejects_invalid_signature(): void
    {
        Config::set('portal_entry.require_sateraito', false);

        $user = User::factory()->create([
            'email' => 'sso-invalid@careearth.info',
        ]);

        $response = $this->get(route('sso.sateraito', [
            'mail' => $user->email,
            'time' => (string) time(),
            'sign' => 'invalid',
        ]));

        $response->assertRedirect(route('login'));
        $response->assertSessionHas('error');
        $this->assertGuest();
    }

    public function test_sso_rejects_unknown_user(): void
    {
        Config::set('portal_entry.require_sateraito', false);

        $email = 'missing@careearth.info';
        $timestamp = (string) time();
        $sign = app(SateraitoSsoService::class)->buildSignature([
            'email' => $email,
            'timestamp' => $timestamp,
        ]);

        $response = $this->get(route('sso.sateraito', [
            'mail' => $email,
            'time' => $timestamp,
            'sign' => $sign,
        ]));

        $response->assertRedirect(route('login'));
        $response->assertSessionHas('error');
        $this->assertGuest();
    }

    public function test_sso_returns_service_unavailable_when_disabled(): void
    {
        Config::set('sateraito_sso.enabled', false);

        $response = $this->get(route('sso.sateraito'));

        $response->assertRedirect(route('login'));
        $response->assertSessionHas('error');
    }

    public function test_sso_redirects_to_password_change_when_required(): void
    {
        $user = User::factory()->create([
            'email' => 'sso-password-change@careearth.info',
            'must_change_password' => true,
        ]);

        $timestamp = (string) time();
        $sign = app(SateraitoSsoService::class)->buildSignature([
            'email' => $user->email,
            'timestamp' => $timestamp,
        ]);

        $response = $this->get(route('sso.sateraito', [
            'mail' => $user->email,
            'time' => $timestamp,
            'sign' => $sign,
        ]));

        $response->assertRedirect(route('password.change'));
        $this->assertAuthenticatedAs($user);
    }
}

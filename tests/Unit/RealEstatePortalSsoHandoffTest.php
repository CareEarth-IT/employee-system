<?php

namespace Tests\Unit;

use App\Models\AffiliationHistory;
use App\Models\User;
use App\Services\RealEstatePortalSsoHandoff;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class RealEstatePortalSsoHandoffTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticate_and_fetch_returns_upstream_response_and_browser_cookies(): void
    {
        config([
            'department_portals.real-estate.internal_url' => 'https://real-estate.example.test',
            'department_portals.real-estate.proxy_secret' => 'portal-shared-secret',
            'department_portals.real-estate.use_identity_token' => false,
        ]);

        Http::fake([
            'https://real-estate.example.test/internal/portal/sso/handoff' => Http::response([
                'success' => true,
                'redirect_url' => 'https://real-estate.example.test/auth/portal/callback?code=abc',
            ], 200),
            'https://real-estate.example.test/auth/portal/callback?code=abc' => Http::response('', 302, [
                'Set-Cookie' => 'real_estate_portal_session=new-session; path=/realestate-portal; httponly; secure',
                'Location' => 'https://real-estate.example.test/home',
            ]),
            'https://real-estate.example.test/home' => Http::response('<html>home</html>', 200, ['Content-Type' => 'text/html']),
        ]);

        $user = $this->userInDepartment('不動産部');

        $result = app(RealEstatePortalSsoHandoff::class)->authenticateAndFetch(
            $user,
            'https://real-estate.example.test/home',
        );

        $this->assertSame(200, $result['upstream']->status());
        $this->assertTrue(
            collect($result['set_cookies'])->contains(fn (string $cookie): bool => str_contains($cookie, 'real_estate_portal_session=new-session')),
        );

        Http::assertSent(fn ($request) => str_contains($request->url(), '/auth/portal/callback?code=abc'));
        Http::assertSent(function ($request) {
            return $request->url() === 'https://real-estate.example.test/home'
                && str_contains((string) ($request->header('Cookie')[0] ?? ''), 'real_estate_portal_session=new-session');
        });
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

<?php

namespace Tests\Unit;

use App\Models\AffiliationHistory;
use App\Models\User;
use App\Services\DepartmentPortalProxy\RealEstatePortalProxyHandler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class RealEstatePortalTest extends TestCase
{
    use RefreshDatabase;

    public function test_real_estate_department_can_view_portal_link(): void
    {
        $user = $this->userInDepartment('不動産部');

        $this->assertTrue($user->isRealEstateDepartment());
        $this->assertTrue($user->canViewRealEstatePortalLink());
    }

    public function test_other_department_cannot_view_portal_link(): void
    {
        $user = $this->userInDepartment('通信部');

        $this->assertFalse($user->isRealEstateDepartment());
        $this->assertFalse($user->canViewRealEstatePortalLink());
    }

    public function test_accounting_section_can_view_real_estate_portal_link(): void
    {
        $user = $this->userInDepartment('経理部', '経理課');

        $this->assertFalse($user->isRealEstateDepartment());
        $this->assertTrue($user->canViewRealEstatePortalLink());
    }

    public function test_dashboard_shows_real_estate_link_only_for_real_estate_department(): void
    {
        $realEstateUser = $this->userInDepartment('不動産部');
        $otherUser = $this->userInDepartment('通信部');

        $this->actingAs($realEstateUser)
            ->get(route('dashboard', ['tab' => 'real-estate']))
            ->assertOk()
            ->assertSee('不動産社内サイト', false)
            ->assertSee('/realestate-portal', false);

        $this->actingAs($otherUser)
            ->get(route('dashboard', ['tab' => 'common']))
            ->assertOk()
            ->assertDontSee('不動産社内サイト', false);
    }

    public function test_common_tab_shows_employee_list_link(): void
    {
        $user = $this->userInDepartment('通信部');

        $this->actingAs($user)
            ->get(route('dashboard', ['tab' => 'common']))
            ->assertOk()
            ->assertSee('社員一覧', false);
    }

    public function test_proxy_is_forbidden_for_non_real_estate_department(): void
    {
        Http::fake([
            '*' => Http::response('upstream', 418),
        ]);

        $otherUser = $this->userInDepartment('通信部');

        $this->assertFalse($otherUser->canViewRealEstatePortalLink());

        $this->actingAs($otherUser)
            ->get('/realestate-portal/applications/create')
            ->assertForbidden();

        Http::assertNothingSent();
    }

    public function test_proxy_forwards_shared_secret_header_when_configured(): void
    {
        config([
            'department_portals.real-estate.proxy_secret' => 'portal-shared-secret',
            'department_portals.real-estate.use_identity_token' => false,
        ]);

        Http::fake([
            '*' => Http::response('<html>ok</html>', 200, ['Content-Type' => 'text/html']),
        ]);

        $user = $this->userInDepartment('不動産部');

        $this->actingAs($user)
            ->withSession($this->portalSession())
            ->get('/realestate-portal/applications/create')
            ->assertOk();

        Http::assertSent(function ($request) use ($user) {
            return $request->hasHeader('X-Employee-Portal', '1')
                && $request->hasHeader('X-Employee-Portal-Proxy-Secret', 'portal-shared-secret')
                && $request->hasHeader('X-Employee-Portal-User-Email', (string) $user->email)
                && ! $request->hasHeader('Authorization');
        });
    }

    public function test_proxy_forwards_portal_session_and_xsrf_cookies_to_upstream(): void
    {
        config([
            'department_portals.real-estate.proxy_secret' => 'portal-shared-secret',
            'department_portals.real-estate.use_identity_token' => false,
        ]);

        Http::fake([
            '*' => Http::response('<html>ok</html>', 200, ['Content-Type' => 'text/html']),
        ]);

        $user = $this->userInDepartment('不動産部');

        $this->actingAs($user)
            ->withSession($this->portalSession())
            ->get('/realestate-portal/home')
            ->assertOk();

        Http::assertSent(function ($request) {
            $cookie = $request->header('Cookie')[0] ?? '';

            return str_contains($cookie, 'real_estate_portal_session=abc123')
                && str_contains($cookie, 'XSRF-TOKEN=portal-xsrf')
                && ! str_contains($cookie, 'laravel-session');
        });
    }

    public function test_proxy_forwards_csrf_header_on_mutating_requests(): void
    {
        config([
            'department_portals.real-estate.internal_url' => 'https://real-estate.example.test',
            'department_portals.real-estate.proxy_secret' => 'portal-shared-secret',
            'department_portals.real-estate.use_identity_token' => false,
        ]);

        Http::fake([
            '*' => Http::response('saved', 200),
        ]);

        $user = $this->userInDepartment('不動産部');

        $this->actingAs($user)
            ->withSession($this->portalSession())
            ->withHeader('X-CSRF-TOKEN', 'csrf-token-value')
            ->put('/realestate-portal/users/1', [
                '_token' => 'csrf-token-value',
                'name' => '更新テスト',
            ])
            ->assertOk();

        Http::assertSent(function ($request) {
            $cookie = $request->header('Cookie')[0] ?? '';

            return $request->method() === 'PUT'
                && $request->hasHeader('X-CSRF-TOKEN', 'csrf-token-value')
                && str_contains($cookie, 'XSRF-TOKEN=portal-xsrf');
        });
    }

    public function test_proxy_rewrites_root_relative_form_action_in_html(): void
    {
        config([
            'department_portals.real-estate.internal_url' => 'https://real-estate.example.test',
            'department_portals.real-estate.proxy_secret' => 'portal-shared-secret',
            'department_portals.real-estate.use_identity_token' => false,
        ]);

        Http::fake([
            'https://real-estate.example.test/*' => Http::response(
                '<form method="POST" action="/admin/applications"><input name="_token" value="tok"></form>',
                200,
                ['Content-Type' => 'text/html'],
            ),
        ]);

        $user = $this->userInDepartment('不動産部');

        $this->actingAs($user)
            ->withSession($this->portalSession())
            ->get('/realestate-portal/admin/applications/create')
            ->assertOk()
            ->assertSee('action="/realestate-portal/admin/applications"', false);
    }

    public function test_proxy_post_to_prefixed_path_avoids_employee_csrf_rejection(): void
    {
        config([
            'department_portals.real-estate.internal_url' => 'https://real-estate.example.test',
            'department_portals.real-estate.proxy_secret' => 'portal-shared-secret',
            'department_portals.real-estate.use_identity_token' => false,
        ]);

        Http::fake([
            'https://real-estate.example.test/admin/applications' => Http::response('', 200),
        ]);

        $user = $this->userInDepartment('不動産部');

        $this->actingAs($user)
            ->withSession($this->portalSession())
            ->post('/realestate-portal/admin/applications', [
                '_token' => 'csrf-token-value',
                'contractor' => 'テスト契約者',
            ])
            ->assertOk();

        Http::assertSent(function ($request) {
            return $request->method() === 'POST'
                && $request->url() === 'https://real-estate.example.test/admin/applications'
                && ($request->data()['_token'] ?? null) === 'csrf-token-value';
        });
    }

    public function test_proxy_forwards_multipart_post_body_to_upstream(): void
    {
        config([
            'department_portals.real-estate.internal_url' => 'https://real-estate.example.test',
            'department_portals.real-estate.proxy_secret' => 'portal-shared-secret',
            'department_portals.real-estate.use_identity_token' => false,
        ]);

        Http::fake([
            'https://real-estate.example.test/properties' => Http::response('', 302),
        ]);

        $user = $this->userInDepartment('不動産部');
        $boundary = '----TestBoundary';
        $body = "--{$boundary}\r\n"
            ."Content-Disposition: form-data; name=\"_token\"\r\n\r\n"
            ."csrf-token-value\r\n"
            ."--{$boundary}\r\n"
            ."Content-Disposition: form-data; name=\"buyer_name\"\r\n\r\n"
            ."テスト購入者\r\n"
            ."--{$boundary}--\r\n";

        $this->actingAs($user)
            ->withSession($this->portalSession())
            ->call(
                'POST',
                '/realestate-portal/properties',
                [],
                [],
                [],
                [
                    'CONTENT_TYPE' => "multipart/form-data; boundary={$boundary}",
                    'HTTP_ACCEPT' => 'text/html,application/xhtml+xml',
                ],
                $body,
            )
            ->assertStatus(302);

        Http::assertSent(function ($request) use ($boundary) {
            $contentType = $request->header('Content-Type')[0] ?? '';

            return $request->method() === 'POST'
                && $request->url() === 'https://real-estate.example.test/properties'
                && str_contains($contentType, 'multipart/form-data')
                && str_contains($contentType, $boundary)
                && str_contains($request->body(), 'name="_token"')
                && str_contains($request->body(), 'csrf-token-value')
                && str_contains($request->body(), 'テスト購入者');
        });
    }

    public function test_proxy_logout_redirects_to_employee_dashboard(): void
    {
        config([
            'department_portals.real-estate.internal_url' => 'https://real-estate.example.test',
            'department_portals.real-estate.proxy_secret' => 'portal-shared-secret',
            'department_portals.real-estate.use_identity_token' => false,
        ]);

        Http::fake([
            'https://real-estate.example.test/logout' => Http::response('', 302, [
                'Location' => 'https://employee.careearth.net/login',
            ]),
        ]);

        $user = $this->userInDepartment('不動産部');

        $this->actingAs($user)
            ->withSession($this->portalSession())
            ->post('/realestate-portal/logout', ['_token' => 'csrf-token-value'])
            ->assertRedirect(route('dashboard', ['tab' => 'real-estate']));

        Http::assertSent(fn ($request) => $request->method() === 'POST'
            && $request->url() === 'https://real-estate.example.test/logout');
    }

    public function test_proxy_logout_get_redirects_to_dashboard_without_sso_retry(): void
    {
        config([
            'department_portals.real-estate.internal_url' => 'https://real-estate.example.test',
            'department_portals.real-estate.proxy_secret' => 'portal-shared-secret',
            'department_portals.real-estate.use_identity_token' => false,
        ]);

        Http::fake();

        $user = $this->userInDepartment('不動産部');

        $this->actingAs($user)
            ->get('/realestate-portal/logout')
            ->assertRedirect(route('dashboard', ['tab' => 'real-estate']));

        Http::assertNothingSent();
    }

    public function test_proxy_starts_inline_sso_when_portal_session_missing(): void
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

        $response = $this->actingAs($user)
            ->get('/realestate-portal/home');

        $response->assertOk();
        $response->assertSee('home', false);

        Http::assertSent(fn ($request) => str_contains($request->url(), '/auth/portal/callback?code=abc'));
    }

    public function test_proxy_reuses_employee_session_on_follow_up_request_without_sso(): void
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
            'https://real-estate.example.test/admin/applications' => Http::response('<html>list</html>', 200, ['Content-Type' => 'text/html']),
        ]);

        $user = $this->userInDepartment('不動産部');

        $this->actingAs($user)
            ->get('/realestate-portal/home')
            ->assertOk();

        $this->actingAs($user)
            ->get('/realestate-portal/admin/applications')
            ->assertOk()
            ->assertSee('list', false);

        Http::assertSent(fn ($request) => $request->url() === 'https://real-estate.example.test/admin/applications'
            && str_contains((string) ($request->header('Cookie')[0] ?? ''), 'real_estate_portal_session=new-session'));

        $callbackRequests = collect(Http::recorded())
            ->filter(fn (array $pair) => str_contains($pair[0]->url(), '/auth/portal/callback?code=abc'))
            ->count();

        $this->assertSame(1, $callbackRequests);
    }

    public function test_proxy_does_not_retry_sso_on_404_when_session_cookie_present(): void
    {
        config([
            'department_portals.real-estate.internal_url' => 'https://real-estate.example.test',
            'department_portals.real-estate.proxy_secret' => 'portal-shared-secret',
            'department_portals.real-estate.use_identity_token' => false,
        ]);

        Http::fake([
            'https://real-estate.example.test/home' => Http::response('not found', 404),
        ]);

        $user = $this->userInDepartment('不動産部');

        $this->actingAs($user)
            ->withSession($this->portalSession(['real_estate_portal_session' => 'stale-session']))
            ->get('/realestate-portal/home')
            ->assertStatus(404);

        Http::assertSent(fn ($request) => $request->url() === 'https://real-estate.example.test/home');
        Http::assertSentCount(1);
    }

    public function test_proxy_refreshes_stale_portal_session_on_redirect_loop(): void
    {
        config([
            'department_portals.real-estate.internal_url' => 'https://real-estate.example.test',
            'department_portals.real-estate.proxy_secret' => 'portal-shared-secret',
            'department_portals.real-estate.use_identity_token' => false,
        ]);

        Http::fake([
            'https://real-estate.example.test/home' => Http::sequence()
                ->push('', 302, ['Location' => '/home'])
                ->push('<html>home</html>', 200, ['Content-Type' => 'text/html']),
            'https://real-estate.example.test/internal/portal/sso/handoff' => Http::response([
                'success' => true,
                'redirect_url' => 'https://real-estate.example.test/auth/portal/callback?code=abc',
            ], 200),
            'https://real-estate.example.test/auth/portal/callback?code=abc' => Http::response('', 302, [
                'Set-Cookie' => 'real_estate_portal_session=fresh-session; path=/realestate-portal; httponly; secure',
                'Location' => 'https://real-estate.example.test/home',
            ]),
        ]);

        $user = $this->userInDepartment('不動産部');

        $response = $this->actingAs($user)
            ->withSession($this->portalSession(['real_estate_portal_session' => 'stale-session']))
            ->get('/realestate-portal/home');

        $response->assertOk();
        $response->assertSee('home', false);

        Http::assertSent(fn ($request) => str_contains($request->url(), '/auth/portal/callback?code=abc'));
    }

    public function test_proxy_refreshes_portal_session_on_401(): void
    {
        config([
            'department_portals.real-estate.internal_url' => 'https://real-estate.example.test',
            'department_portals.real-estate.proxy_secret' => 'portal-shared-secret',
            'department_portals.real-estate.use_identity_token' => false,
        ]);

        Http::fake([
            'https://real-estate.example.test/admin/applications' => Http::sequence()
                ->push('Portal session required.', 401, ['X-Portal-Session-Required' => '1'])
                ->push('<html>list</html>', 200, ['Content-Type' => 'text/html']),
            'https://real-estate.example.test/internal/portal/sso/handoff' => Http::response([
                'success' => true,
                'redirect_url' => 'https://real-estate.example.test/auth/portal/callback?code=abc',
            ], 200),
            'https://real-estate.example.test/auth/portal/callback?code=abc' => Http::response('', 302, [
                'Set-Cookie' => 'real_estate_portal_session=fresh-session; path=/realestate-portal; httponly; secure',
                'Location' => 'https://real-estate.example.test/home',
            ]),
        ]);

        $user = $this->userInDepartment('不動産部');

        $response = $this->actingAs($user)
            ->withSession($this->portalSession(['real_estate_portal_session' => 'stale-session']))
            ->get('/realestate-portal/admin/applications');

        $response->assertOk();
        $response->assertSee('list', false);

        Http::assertSent(fn ($request) => str_contains($request->url(), '/auth/portal/callback?code=abc'));
    }

    private function portalSession(array $overrides = []): array
    {
        return [
            RealEstatePortalProxyHandler::PORTAL_SESSION_COOKIE_KEY => array_merge([
                'real_estate_portal_session' => 'abc123',
                'XSRF-TOKEN' => 'portal-xsrf',
            ], $overrides),
        ];
    }

    private function userInDepartment(string $department, string $section = '営業課'): User
    {
        $user = User::factory()->create();

        AffiliationHistory::create([
            'user_id' => $user->id,
            'start_date' => '2024-01-01',
            'enrollment_status' => AffiliationHistory::STATUS_ENROLLED,
            'department' => $department,
            'section' => $section,
            'location' => '大阪',
        ]);

        return $user->fresh();
    }
}

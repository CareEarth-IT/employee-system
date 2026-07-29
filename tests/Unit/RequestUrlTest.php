<?php

namespace Tests\Unit;

use App\Support\RequestUrl;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class RequestUrlTest extends TestCase
{
    public function test_root_uses_request_host(): void
    {
        config(['app.url' => 'https://employee.careearth.net']);

        $request = Request::create('https://employee.careearth.net/employees', 'GET');
        $session = $this->app['session.store'];
        $session->start();
        $request->setLaravelSession($session);

        $this->assertSame('https://employee.careearth.net', RequestUrl::root($request));
    }

    public function test_referer_is_used_when_request_has_no_host(): void
    {
        config(['app.url' => 'https://employee.careearth.net']);

        $request = Request::create('/employees', 'GET', server: [
            'HTTP_REFERER' => 'https://employee.careearth.net/employees',
        ]);
        $request->setLaravelSession($this->app['session.store']);

        $this->assertSame('https://employee.careearth.net', RequestUrl::root($request));
    }

    public function test_remember_stores_root_in_session(): void
    {
        $request = Request::create('https://employee-abc.asia-northeast1.run.app/dashboard', 'GET');
        $request->setLaravelSession($this->app['session.store']);

        RequestUrl::remember($request);

        $this->assertSame(
            'https://employee-abc.asia-northeast1.run.app',
            $request->session()->get(RequestUrl::SESSION_KEY),
        );
    }

    public function test_apply_root_affects_route_generation(): void
    {
        config(['app.url' => 'https://employee.careearth.net']);

        $request = Request::create('https://employee.careearth.net/login', 'GET');
        $session = $this->app['session.store'];
        $session->start();
        $request->setLaravelSession($session);

        RequestUrl::applyRoot($request);

        $this->assertStringEndsWith('/login', URL::route('login'));
        $this->assertStringContainsString('employee.careearth.net', URL::route('login'));
    }

    public function test_with_root_generates_urls_for_captured_host(): void
    {
        $url = RequestUrl::withRoot(
            'https://employee-abc.asia-northeast1.run.app',
            fn () => route('login'),
        );

        $this->assertStringEndsWith('/login', $url);
        $this->assertStringContainsString('employee-abc.asia-northeast1.run.app', $url);
    }
}

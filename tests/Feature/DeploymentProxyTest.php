<?php

namespace Tests\Feature;

use App\Http\Middleware\TrustDeploymentProxies;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class DeploymentProxyTest extends TestCase
{
    protected function tearDown(): void
    {
        Request::setTrustedProxies([], 0);
        URL::forceRootUrl(null);
        URL::forceScheme(null);
        parent::tearDown();
    }

    public function test_forwarded_headers_are_ignored_from_an_untrusted_peer(): void
    {
        config(['deployment.trusted_proxies' => ['10.1.2.3']]);
        $request = Request::create('http://example.com/login', server: ['REMOTE_ADDR' => '203.0.113.3', 'HTTP_X_FORWARDED_FOR' => '198.51.100.5', 'HTTP_X_FORWARDED_PROTO' => 'https']);
        (new TrustDeploymentProxies)->handle($request, function ($request) {
            $this->assertSame('203.0.113.3', $request->ip());
            $this->assertFalse($request->isSecure());

            return response('ok');
        });
    }

    public function test_explicit_proxy_preserves_https_and_the_client_ip(): void
    {
        config(['deployment.trusted_proxies' => ['10.1.2.3']]);
        $request = Request::create('http://example.com/login', server: ['REMOTE_ADDR' => '10.1.2.3', 'HTTP_X_FORWARDED_FOR' => '198.51.100.5', 'HTTP_X_FORWARDED_PROTO' => 'https']);
        (new TrustDeploymentProxies)->handle($request, function ($request) {
            $this->assertSame('198.51.100.5', $request->ip());
            $this->assertTrue($request->isSecure());

            return response('ok');
        });
    }

    public function test_render_demo_uses_its_https_origin_without_trusting_forwarded_headers(): void
    {
        app()->instance('env', 'production');
        config(['deployment.demo' => true, 'deployment.trusted_proxies' => [], 'app.url' => 'https://demo.example.com']);
        $request = Request::create('http://demo.example.com/login', server: ['REMOTE_ADDR' => '203.0.113.3', 'HTTP_X_FORWARDED_FOR' => '198.51.100.5', 'HTTP_X_FORWARDED_HOST' => 'evil.example.com']);
        (new TrustDeploymentProxies)->handle($request, function ($request) {
            $this->assertTrue($request->isSecure());
            $this->assertSame('203.0.113.3', $request->ip());
            $this->assertSame('https://demo.example.com/login', route('login'));
            $this->assertTrue(URL::hasValidSignature(Request::create(URL::temporarySignedRoute('login', now()->addMinute()))));

            return response('ok');
        });
    }
}

<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class SecurityHeadersTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_and_authenticated_responses_receive_browser_security_headers(): void
    {
        foreach ([$this->get('/login'), $this->actingAs(User::factory()->create())->get('/dashboard')] as $response) {
            $response->assertHeader('X-Content-Type-Options', 'nosniff')
                ->assertHeader('Content-Security-Policy', "base-uri 'self'; object-src 'none'; frame-ancestors 'self'")
                ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
                ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
                ->assertHeader('Permissions-Policy', 'camera=(), geolocation=(), microphone=()');
        }
    }

    public function test_sensitive_endpoint_keeps_its_stricter_referrer_policy(): void
    {
        Route::middleware('web')->get('/test-strict-referrer', fn () => response('ok')->header('Referrer-Policy', 'no-referrer'));

        $this->get('/test-strict-referrer')->assertOk()->assertHeader('Referrer-Policy', 'no-referrer');
    }
}

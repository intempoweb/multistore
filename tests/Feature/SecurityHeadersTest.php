<?php

namespace Tests\Feature;

use Tests\TestCase;

class SecurityHeadersTest extends TestCase
{
    public function test_security_headers_are_added_to_responses(): void
    {
        config(['security.headers.enabled' => true]);

        $response = $this->get('/up');

        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
        $this->assertStringContainsString("frame-ancestors 'self'", $response->headers->get('Content-Security-Policy'));
        $this->assertStringContainsString('https://connect.facebook.net', $response->headers->get('Content-Security-Policy'));
        $this->assertStringContainsString('form-action \'self\' https://www.paypal.com https://www.sandbox.paypal.com https://www.facebook.com', $response->headers->get('Content-Security-Policy'));
        $this->assertStringContainsString('frame-src \'self\' https://js.stripe.com https://hooks.stripe.com https://www.paypal.com https://www.sandbox.paypal.com https://www.google.com https://www.facebook.com', $response->headers->get('Content-Security-Policy'));
    }
}

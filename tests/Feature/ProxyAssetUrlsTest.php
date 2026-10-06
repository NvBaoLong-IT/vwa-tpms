<?php

namespace Tests\Feature;

use Tests\TestCase;

class ProxyAssetUrlsTest extends TestCase
{
    public function test_local_proxy_https_header_generates_secure_assets_and_form_urls(): void
    {
        $this->withServerVariables([
            'REMOTE_ADDR' => '127.0.0.1',
            'HTTP_HOST' => 'demo.ngrok-free.dev',
            'SERVER_PORT' => 8000,
            'HTTP_X_FORWARDED_PROTO' => 'https',
            'HTTP_X_FORWARDED_PORT' => '443',
        ])->get('http://demo.ngrok-free.dev/login')
            ->assertOk()
            ->assertSee('https://demo.ngrok-free.dev/build/', false)
            ->assertSee('action="https://demo.ngrok-free.dev/login"', false)
            ->assertSee('https://demo.ngrok-free.dev/images/', false)
            ->assertDontSee('http://demo.ngrok-free.dev/', false);
    }

    public function test_direct_local_http_still_generates_http_assets(): void
    {
        $this->withServerVariables([
            'REMOTE_ADDR' => '127.0.0.1',
            'HTTP_HOST' => '127.0.0.1:8000',
            'SERVER_PORT' => 8000,
        ])->get('http://127.0.0.1:8000/login')
            ->assertOk()
            ->assertSee('http://127.0.0.1:8000/build/', false)
            ->assertDontSee('https://127.0.0.1:8000/', false);
    }

    public function test_untrusted_remote_address_cannot_override_http_scheme(): void
    {
        $this->withServerVariables([
            'REMOTE_ADDR' => '198.51.100.10',
            'HTTP_HOST' => 'example.test',
            'SERVER_PORT' => 80,
            'HTTP_X_FORWARDED_PROTO' => 'https',
        ])->get('http://example.test/login')
            ->assertOk()
            ->assertSee('http://example.test/build/', false)
            ->assertDontSee('https://example.test/build/', false);
    }
}

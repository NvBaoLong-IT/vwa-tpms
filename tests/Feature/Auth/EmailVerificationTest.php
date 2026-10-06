<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_email_verification_routes_are_closed(): void
    {
        $this->get('/verify-email')->assertNotFound();
        $this->get('/verify-email/1/hash')->assertNotFound();
        $this->post('/email/verification-notification')->assertNotFound();
    }
}

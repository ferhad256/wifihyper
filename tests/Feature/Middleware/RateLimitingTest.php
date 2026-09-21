<?php

namespace Tests\Feature\Middleware;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RateLimitingTest extends TestCase
{
    use RefreshDatabase;

    /**
     * The limiter previously matched GET as well as POST, so simply opening
     * the login page a few times exhausted the five-attempt budget and locked
     * the user out before they had typed anything.
     */
    public function test_viewing_the_login_page_does_not_consume_the_attempt_budget(): void
    {
        for ($i = 0; $i < 8; $i++) {
            $this->get('/login')->assertOk();
        }
    }

    public function test_repeated_failed_logins_are_throttled(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', [
                'email' => 'victim@example.com',
                'password' => 'Wr0ng!Passw0rd',
            ]);
        }

        $this->post('/login', [
            'email' => 'victim@example.com',
            'password' => 'Wr0ng!Passw0rd',
        ])->assertSessionHas('error', fn ($error) => str_contains($error, 'Too many attempts'));
    }

    /**
     * Keying on the submitted email rather than the user agent means one
     * account being attacked does not lock out a different account behind the
     * same NAT, which matters where shared public IPs are common.
     */
    public function test_throttling_one_account_does_not_lock_out_another(): void
    {
        for ($i = 0; $i < 6; $i++) {
            $this->post('/login', [
                'email' => 'victim@example.com',
                'password' => 'Wr0ng!Passw0rd',
            ]);
        }

        $response = $this->post('/login', [
            'email' => 'bystander@example.com',
            'password' => 'Wr0ng!Passw0rd',
        ]);

        $response->assertSessionHas('error', 'Invalid credentials.');
    }
}

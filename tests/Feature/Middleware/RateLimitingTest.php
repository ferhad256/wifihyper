<?php

namespace Tests\Feature\Middleware;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RateLimitingTest extends TestCase
{
    use RefreshDatabase;

    /**
     * The limiter previously matched GET as well as POST, so simply opening
     * the form a few times exhausted the five-attempt budget and locked the
     * user out before they had typed anything.
     *
     * Tenant sign-in itself is no longer routed through here - the panel's
     * login page throttles itself - so the limiter is exercised through a
     * route it still guards.
     */
    public function test_viewing_a_guarded_form_does_not_consume_the_attempt_budget(): void
    {
        for ($i = 0; $i < 8; $i++) {
            $this->get('/forgot-password')->assertOk();
        }
    }

    public function test_repeated_posts_to_a_guarded_route_are_throttled(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->post('/forgot-password', ['email' => 'victim@example.com']);
        }

        $this->post('/forgot-password', ['email' => 'victim@example.com'])
            ->assertSessionHas('error', fn ($error) => str_contains($error, 'Too many attempts'));
    }

    /**
     * Keying on the submitted email rather than the user agent means one
     * account being attacked does not lock out a different account behind the
     * same NAT, which matters where shared public IPs are common.
     */
    public function test_throttling_one_account_does_not_lock_out_another(): void
    {
        for ($i = 0; $i < 6; $i++) {
            $this->post('/forgot-password', ['email' => 'victim@example.com']);
        }

        $this->post('/forgot-password', ['email' => 'bystander@example.com'])
            ->assertSessionMissing('error');
    }
}

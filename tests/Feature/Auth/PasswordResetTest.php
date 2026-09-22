<?php

namespace Tests\Feature\Auth;

use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    private const GENERIC = 'If your email is registered, you will receive a password reset link shortly.';

    public function test_requesting_a_reset_for_an_unknown_address_returns_the_generic_message(): void
    {
        $this->post('/forgot-password', ['email' => 'nobody@example.com'])
            ->assertSessionHas('success', self::GENERIC);
    }

    public function test_requesting_a_reset_for_a_live_account_returns_the_generic_message(): void
    {
        $tenant = Tenant::factory()->create();

        $this->post('/forgot-password', ['email' => $tenant->email])
            ->assertSessionHas('success', self::GENERIC);
    }

    /**
     * A deactivated account used to answer "Account is deactivated. Please
     * contact support." while an unknown address got the generic message,
     * which confirmed to an anonymous caller that the address was registered.
     */
    public function test_a_deactivated_account_is_not_revealed(): void
    {
        $tenant = Tenant::factory()->inactive()->create();

        $response = $this->post('/forgot-password', ['email' => $tenant->email]);

        $response->assertSessionHas('success', self::GENERIC);
        $response->assertSessionMissing('error');
    }

    public function test_no_reset_token_is_issued_for_a_deactivated_account(): void
    {
        $tenant = Tenant::factory()->inactive()->create();

        $this->post('/forgot-password', ['email' => $tenant->email]);

        $this->assertDatabaseMissing('password_reset_tokens', ['email' => $tenant->email]);
    }

    public function test_a_reset_token_is_issued_for_a_live_account(): void
    {
        $tenant = Tenant::factory()->create();

        $this->post('/forgot-password', ['email' => $tenant->email]);

        $this->assertDatabaseHas('password_reset_tokens', ['email' => $tenant->email]);
    }

    /**
     * The token is stored hashed, so a database read alone cannot be used to
     * take over an account.
     */
    public function test_the_stored_reset_token_is_hashed(): void
    {
        $tenant = Tenant::factory()->create();

        $this->post('/forgot-password', ['email' => $tenant->email]);

        $row = DB::table('password_reset_tokens')->where('email', $tenant->email)->first();

        $this->assertNotNull($row);
        $this->assertStringStartsWith('$2y$', $row->token);
    }
}

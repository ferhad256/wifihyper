<?php

namespace Tests\Feature\Auth;

use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Characterises the tenant login flow as it behaves TODAY.
 *
 * Some of these assertions pin behaviour that is known to be wrong (see the
 * enumeration tests at the bottom). They are written to pass against the
 * current controller on purpose: when the auth refactor changes that
 * behaviour, these fail loudly and get updated deliberately rather than
 * the change slipping through unnoticed.
 *
 * Note: RateLimiting allows 5 attempts/minute keyed on IP+user-agent, so each
 * test keeps itself to a couple of POSTs. The array cache store is rebuilt per
 * test, so the limiter does not leak between them.
 */
class TenantLoginTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD = 'Str0ng!Passw0rd';

    private function activeTenant(array $overrides = []): Tenant
    {
        return Tenant::factory()->create(array_merge([
            'password' => Hash::make(self::PASSWORD),
            'email_verified_at' => now(),
            'is_active' => true,
        ], $overrides));
    }

    public function test_valid_credentials_log_the_tenant_in(): void
    {
        $tenant = $this->activeTenant();

        $response = $this->post('/login', [
            'email' => $tenant->email,
            'password' => self::PASSWORD,
        ]);

        $response->assertRedirect(route('dashboard'));
        $this->assertSame($tenant->id, session('tenant_id'));
    }

    public function test_wrong_password_is_rejected(): void
    {
        $tenant = $this->activeTenant();

        $response = $this->post('/login', [
            'email' => $tenant->email,
            'password' => 'Wr0ng!Passw0rd',
        ]);

        $response->assertSessionHas('error', 'Invalid credentials.');
        $this->assertNull(session('tenant_id'));
    }

    public function test_unknown_email_is_rejected_with_the_same_message_as_a_wrong_password(): void
    {
        $response = $this->post('/login', [
            'email' => 'nobody@example.com',
            'password' => self::PASSWORD,
        ]);

        $response->assertSessionHas('error', 'Invalid credentials.');
        $this->assertNull(session('tenant_id'));
    }

    public function test_a_failed_login_sends_no_mail(): void
    {
        $tenant = $this->activeTenant();
        $this->flushSentMails();

        $this->post('/login', [
            'email' => $tenant->email,
            'password' => 'Wr0ng!Passw0rd',
        ]);

        $this->assertCount(0, $this->sentMails());
    }

    public function test_a_deactivated_tenant_cannot_log_in(): void
    {
        $tenant = $this->activeTenant(['is_active' => false]);

        $response = $this->post('/login', [
            'email' => $tenant->email,
            'password' => self::PASSWORD,
        ]);

        $response->assertSessionHas('error', 'Account is deactivated. Please contact support.');
        $this->assertNull(session('tenant_id'));
    }

    public function test_an_unverified_tenant_cannot_log_in(): void
    {
        $tenant = $this->activeTenant(['email_verified_at' => null]);

        $response = $this->post('/login', [
            'email' => $tenant->email,
            'password' => self::PASSWORD,
        ]);

        $this->assertNull(session('tenant_id'));
        $response->assertRedirect(route('verification.show', ['email' => $tenant->email]));
    }

    /**
     * The login flow used to run the verification and is_active gates before
     * checking the password, so an anonymous caller could tell a registered
     * address from an unknown one, and learn its state, without credentials.
     * Credentials are now checked first.
     */
    public function test_an_unverified_account_is_indistinguishable_without_the_password(): void
    {
        $tenant = $this->activeTenant(['email_verified_at' => null]);

        $unverified = $this->post('/login', [
            'email' => $tenant->email,
            'password' => 'not-the-right-password',
        ]);

        $unknown = $this->post('/login', [
            'email' => 'nobody@example.com',
            'password' => 'not-the-right-password',
        ]);

        $this->assertSame(
            $unknown->headers->get('Location'),
            $unverified->headers->get('Location'),
            'A registered-but-unverified address is still distinguishable from an unknown one.'
        );
        $this->assertSame(
            session('error'),
            'Invalid credentials.',
            'The response revealed something other than a generic failure.'
        );
    }

    public function test_a_deactivated_account_is_indistinguishable_without_the_password(): void
    {
        $tenant = $this->activeTenant(['is_active' => false]);

        $deactivated = $this->post('/login', [
            'email' => $tenant->email,
            'password' => 'not-the-right-password',
        ]);

        $unknown = $this->post('/login', [
            'email' => 'nobody@example.com',
            'password' => 'not-the-right-password',
        ]);

        $this->assertSame(
            $unknown->headers->get('Location'),
            $deactivated->headers->get('Location')
        );
    }

    /**
     * The same pre-password branch let an anonymous caller send a
     * verification email to any registered address, with no credentials.
     */
    public function test_a_wrong_password_triggers_no_verification_email(): void
    {
        $tenant = $this->activeTenant(['email_verified_at' => null]);
        $this->flushSentMails();

        $this->post('/login', [
            'email' => $tenant->email,
            'password' => 'not-the-right-password',
        ]);

        $this->assertCount(
            0,
            $this->sentMails(),
            'A failed login still triggered a verification email.'
        );
    }

    /**
     * With the CORRECT password, an unverified tenant should still be helped
     * along to the verification screen - that is a real user, not a prober.
     */
    public function test_the_correct_password_still_sends_an_unverified_tenant_to_verification(): void
    {
        $tenant = $this->activeTenant(['email_verified_at' => null]);
        $this->flushSentMails();

        $this->post('/login', [
            'email' => $tenant->email,
            'password' => self::PASSWORD,
        ])->assertRedirect(route('verification.show', ['email' => $tenant->email]));

        $this->assertCount(1, $this->sentMails());
    }

    public function test_logout_clears_the_session(): void
    {
        $tenant = $this->activeTenant();

        $this->loginAsTenant($tenant)->post('/logout')->assertRedirect(route('landing'));

        $this->assertNull(session('tenant_id'));
    }
}

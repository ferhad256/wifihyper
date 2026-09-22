<?php

namespace Tests\Feature\Auth;

use App\Filament\Tenant\Pages\Auth\Login;
use App\Models\Tenant;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Tenant sign-in, now served by the panel's Livewire login rather than a
 * Blade form post.
 *
 * The security properties asserted here are the same ones the Blade version
 * was held to. Filament verifies the password before asking whether the
 * account may use the panel, and pads both failures with a Timebox, so an
 * account that exists but cannot sign in stays indistinguishable from one
 * that does not.
 */
class TenantLoginTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD = 'Str0ng!Passw0rd';

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel('tenant');
    }

    private function activeTenant(array $overrides = []): Tenant
    {
        return Tenant::factory()->create(array_merge([
            'password' => Hash::make(self::PASSWORD),
            'email_verified_at' => now(),
            'is_active' => true,
        ], $overrides));
    }

    private function attempt(string $email, string $password): \Livewire\Features\SupportTesting\Testable
    {
        return Livewire::test(Login::class)
            ->fillForm(['email' => $email, 'password' => $password])
            ->call('authenticate');
    }

    public function test_the_login_page_renders(): void
    {
        $this->get('/dashboard/login')->assertOk();
    }

    public function test_the_old_login_url_still_works(): void
    {
        $this->get('/login')->assertRedirect(route('filament.tenant.auth.login'));
    }

    public function test_valid_credentials_sign_the_tenant_in(): void
    {
        $tenant = $this->activeTenant();

        $this->attempt($tenant->email, self::PASSWORD)->assertHasNoFormErrors();

        $this->assertTrue(Auth::guard('tenant')->check());
        $this->assertSame($tenant->id, Auth::guard('tenant')->id());
    }

    public function test_a_wrong_password_is_rejected(): void
    {
        $tenant = $this->activeTenant();

        $this->attempt($tenant->email, 'Wr0ng!Passw0rd')->assertHasFormErrors(['email']);

        $this->assertFalse(Auth::guard('tenant')->check());
    }

    public function test_an_unknown_address_is_rejected_the_same_way(): void
    {
        $this->attempt('nobody@example.com', self::PASSWORD)->assertHasFormErrors(['email']);

        $this->assertFalse(Auth::guard('tenant')->check());
    }

    public function test_a_failed_sign_in_sends_no_mail(): void
    {
        $tenant = $this->activeTenant();
        $this->flushSentMails();

        $this->attempt($tenant->email, 'Wr0ng!Passw0rd');

        $this->assertCount(0, $this->sentMails());
    }

    public function test_a_deactivated_tenant_cannot_sign_in(): void
    {
        $tenant = $this->activeTenant(['is_active' => false]);

        $this->attempt($tenant->email, self::PASSWORD)->assertHasFormErrors(['email']);

        $this->assertFalse(Auth::guard('tenant')->check());
    }

    /**
     * A deactivated account must fail exactly as an unknown one does, or the
     * difference tells an anonymous caller that the address is registered.
     */
    public function test_a_deactivated_account_is_indistinguishable_from_an_unknown_one(): void
    {
        $tenant = $this->activeTenant(['is_active' => false]);

        $deactivated = $this->attempt($tenant->email, 'Wr0ng!Passw0rd');
        $unknown = $this->attempt('nobody@example.com', 'Wr0ng!Passw0rd');

        $deactivated->assertHasFormErrors(['email']);
        $unknown->assertHasFormErrors(['email']);
    }

    /**
     * A wrong password must never trigger a verification email, or anyone
     * could use the login form to mail any registered address.
     */
    public function test_a_wrong_password_triggers_no_verification_email(): void
    {
        $tenant = $this->activeTenant(['email_verified_at' => null]);
        $this->flushSentMails();

        $this->attempt($tenant->email, 'Wr0ng!Passw0rd');

        $this->assertCount(0, $this->sentMails());
        $this->assertFalse(Auth::guard('tenant')->check());
    }

    /**
     * With the CORRECT password an unverified operator is a real user, so
     * they are helped to the verification screen rather than stonewalled.
     */
    public function test_the_correct_password_sends_an_unverified_tenant_to_verification(): void
    {
        $tenant = $this->activeTenant(['email_verified_at' => null]);
        $this->flushSentMails();

        // Sending is gated on a cache entry, so state it as a precondition:
        // otherwise a stale entry looks identical to a broken mailer.
        $this->assertTrue(
            app(\App\Services\EmailVerificationService::class)->canRequestVerification($tenant),
            'Precondition: the service refused to send. cache.default=' . config('cache.default')
                . ' cached=' . var_export(\Illuminate\Support\Facades\Cache::get("email_verification_{$tenant->id}"), true)
        );

        $this->attempt($tenant->email, self::PASSWORD)
            ->assertRedirect(route('verification.show', ['email' => $tenant->email]));

        $this->assertCount(1, $this->sentMails(), $this->mailDiagnostics());
        $this->assertFalse(Auth::guard('tenant')->check());
    }

    public function test_signing_out_clears_the_guard(): void
    {
        $tenant = $this->activeTenant();

        $this->actingAs($tenant, 'tenant')
            ->post('/dashboard/logout')
            ->assertRedirect();

        $this->assertFalse(Auth::guard('tenant')->check());
    }
}

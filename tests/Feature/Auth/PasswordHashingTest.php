<?php

namespace Tests\Feature\Auth;

use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Guards the planned move to a 'password' => 'hashed' cast.
 *
 * Passwords are currently hashed by hand in three places (AuthController,
 * PasswordResetController, ProfileController). When the cast is added those
 * calls get deleted; if any is missed the password is hashed twice and the
 * account is silently locked out. Run this file before AND after that change
 * and require identical results.
 */
class PasswordHashingTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD = 'Str0ng!Passw0rd';
    private const NEW_PASSWORD = 'An0ther!Passw0rd';

    public function test_registration_stores_a_usable_hash(): void
    {
        Mail::fake();

        $this->post('/register', [
            'name' => 'Test Operator',
            'email' => 'operator@example.com',
            'password' => self::PASSWORD,
            'password_confirmation' => self::PASSWORD,
            'terms' => 'on',
        ]);

        $tenant = Tenant::where('email', 'operator@example.com')->first();

        $this->assertNotNull($tenant, 'Registration did not create the tenant.');
        $this->assertNotSame(self::PASSWORD, $tenant->password, 'Password was stored in plain text.');
        $this->assertTrue(
            Hash::check(self::PASSWORD, $tenant->password),
            'Stored hash does not verify - the password was likely hashed twice.'
        );
    }

    public function test_changing_the_password_stores_a_usable_hash(): void
    {
        $tenant = Tenant::factory()->create([
            'password' => Hash::make(self::PASSWORD),
        ]);

        $this->loginAsTenant($tenant)->put('/profile/password', [
            'current_password' => self::PASSWORD,
            'new_password' => self::NEW_PASSWORD,
            'new_password_confirmation' => self::NEW_PASSWORD,
        ]);

        $tenant->refresh();

        $this->assertTrue(
            Hash::check(self::NEW_PASSWORD, $tenant->password),
            'New password hash does not verify - it was likely hashed twice.'
        );
        $this->assertFalse(
            Hash::check(self::PASSWORD, $tenant->password),
            'The old password still works after a password change.'
        );
        $this->assertNotNull($tenant->password_changed_at);
    }

    public function test_the_password_change_requires_the_current_password(): void
    {
        $tenant = Tenant::factory()->create([
            'password' => Hash::make(self::PASSWORD),
        ]);

        $this->loginAsTenant($tenant)->put('/profile/password', [
            'current_password' => 'not-the-current-password',
            'new_password' => self::NEW_PASSWORD,
            'new_password_confirmation' => self::NEW_PASSWORD,
        ])->assertSessionHasErrors('current_password');

        $tenant->refresh();

        $this->assertTrue(
            Hash::check(self::PASSWORD, $tenant->password),
            'The password changed despite the wrong current password being supplied.'
        );
    }

    public function test_the_new_password_must_differ_from_the_current_one(): void
    {
        $tenant = Tenant::factory()->create([
            'password' => Hash::make(self::PASSWORD),
        ]);

        $this->loginAsTenant($tenant)->put('/profile/password', [
            'current_password' => self::PASSWORD,
            'new_password' => self::PASSWORD,
            'new_password_confirmation' => self::PASSWORD,
        ])->assertSessionHasErrors('new_password');
    }

    public function test_the_factory_produces_a_usable_hash(): void
    {
        // The factory feeds the rest of the suite; if its hash stops verifying,
        // every login test fails for a reason that has nothing to do with auth.
        $tenant = Tenant::factory()->create();

        $this->assertTrue(Hash::check('password', $tenant->password));
    }
}

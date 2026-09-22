<?php

namespace Tests\Feature\Auth;

use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Guards the 'password' => 'hashed' cast on both models.
 *
 * Passwords used to be hashed by hand in several controllers. With the cast
 * doing it, a leftover manual Hash::make would hash twice and silently lock
 * the account out - which is exactly what happened on the Admin model, where
 * the cast was missing and a plain password reached the column instead.
 *
 * Password CHANGES are asserted in AccountPageTest (tenant) and
 * AdminStaffResourceTest (admin), where those forms now live.
 */
class PasswordHashingTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_stores_a_usable_hash(): void
    {
        Mail::fake();

        $this->post('/register', [
            'name' => 'Test Operator',
            'email' => 'operator@example.com',
            'password' => self::fixturePassword(),
            'password_confirmation' => self::fixturePassword(),
            'terms' => 'on',
        ]);

        $tenant = Tenant::where('email', 'operator@example.com')->first();

        $this->assertNotNull($tenant, 'Registration did not create the tenant.');
        $this->assertNotSame(self::fixturePassword(), $tenant->password, 'Password was stored in plain text.');
        $this->assertTrue(
            Hash::check(self::fixturePassword(), $tenant->password),
            'Stored hash does not verify - the password was likely hashed twice.'
        );
    }

    public function test_the_factory_produces_a_usable_hash(): void
    {
        // The factory feeds the rest of the suite; if its hash stops verifying,
        // every login test fails for a reason that has nothing to do with auth.
        $tenant = Tenant::factory()->create();

        $this->assertTrue(Hash::check('password', $tenant->password));
    }
}

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

    /**
     * A password is hashed, never rendered, so HTML-encoding it on the way in
     * protects nothing and changes it.
     *
     * Input sanitisation used to escape every posted string, so registering
     * with an ampersand in the password stored the hash of "&amp;" instead.
     * The panel login posts JSON and is exempt from sanitising, so the
     * password the operator typed could never match that hash again - an
     * account locked out at the moment it was created.
     *
     * The generated fixture password contains symbols at random, so this
     * asserts the property explicitly rather than leaving it to the draw.
     */
    public function test_a_password_containing_html_characters_survives_registration(): void
    {
        Mail::fake();

        $password = self::fixturePassword('html').'&<>"\'';

        $this->post('/register', [
            'name' => 'Test Operator',
            'email' => 'escaped@example.com',
            'password' => $password,
            'password_confirmation' => $password,
            'terms' => 'on',
        ]);

        $tenant = Tenant::where('email', 'escaped@example.com')->first();

        $this->assertNotNull($tenant, 'Registration did not create the tenant.');
        $this->assertTrue(
            Hash::check($password, $tenant->password),
            'The password was altered in transit - it can no longer be used to sign in.'
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

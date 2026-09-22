<?php

namespace Tests\Feature\Filament;

use App\Filament\Tenant\Pages\Account;
use App\Models\Hotspot;
use App\Models\Tenant;
use App\Models\Voucher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class AccountPageTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create([
            'password' => Hash::make(self::fixturePassword()),
            'wallet_balance' => 0,
        ]);

        $this->actingAs($this->tenant, 'tenant');
    }

    public function test_the_page_loads(): void
    {
        $this->get('/dashboard/account')->assertOk();
    }

    public function test_profile_details_can_be_saved(): void
    {
        Livewire::test(Account::class)
            ->fillForm([
                'name' => 'Updated Name',
                'email' => $this->tenant->email,
                'phone' => '0700111222',
                'business_name' => 'Corner Cafe Ltd',
            ], 'profileForm')
            ->call('saveProfile')
            ->assertHasNoFormErrors();

        $this->assertSame('Updated Name', $this->tenant->fresh()->name);
        $this->assertSame('Corner Cafe Ltd', $this->tenant->fresh()->business_name);
    }

    /**
     * The one setting from the old page that is genuinely wired up:
     * EmailService gates three different sends on it.
     */
    public function test_the_email_notification_preference_is_stored(): void
    {
        Livewire::test(Account::class)
            ->fillForm([
                'name' => $this->tenant->name,
                'email' => $this->tenant->email,
                'email_notifications' => true,
            ], 'profileForm')
            ->call('saveProfile');

        $this->assertTrue($this->tenant->fresh()->settings['email_notifications']);
    }

    public function test_the_password_can_be_changed(): void
    {
        Livewire::test(Account::class)
            ->fillForm([
                'current_password' => self::fixturePassword(),
                'new_password' => self::fixturePassword('new'),
                'new_password_confirmation' => self::fixturePassword('new'),
            ], 'passwordForm')
            ->call('savePassword')
            ->assertHasNoFormErrors();

        $this->assertTrue(Hash::check(self::fixturePassword('new'), $this->tenant->fresh()->password));
    }

    public function test_the_current_password_must_be_correct(): void
    {
        Livewire::test(Account::class)
            ->fillForm([
                'current_password' => self::fixturePassword('wrong'),
                'new_password' => self::fixturePassword('new'),
                'new_password_confirmation' => self::fixturePassword('new'),
            ], 'passwordForm')
            ->call('savePassword')
            ->assertHasFormErrors(['current_password'], 'passwordForm');

        $this->assertTrue(Hash::check(self::fixturePassword(), $this->tenant->fresh()->password));
    }

    public function test_closing_the_account_requires_the_exact_phrase(): void
    {
        Livewire::test(Account::class)
            ->callAction('deleteAccount', [
                'password' => self::fixturePassword(),
                'confirmation' => 'delete my account',
            ])
            ->assertHasActionErrors(['confirmation']);

        $this->assertDatabaseHas('tenants', ['id' => $this->tenant->id]);
    }

    public function test_closing_the_account_requires_the_password(): void
    {
        Livewire::test(Account::class)
            ->callAction('deleteAccount', [
                'password' => self::fixturePassword('wrong'),
                'confirmation' => 'DELETE MY ACCOUNT',
            ])
            ->assertHasActionErrors(['password']);

        $this->assertDatabaseHas('tenants', ['id' => $this->tenant->id]);
    }

    /**
     * Money still in the wallet blocks closure, so a balance cannot be
     * stranded by deleting the account that owns it.
     */
    public function test_an_account_with_a_balance_cannot_be_closed(): void
    {
        $this->tenant->update(['wallet_balance' => 5000]);

        Livewire::test(Account::class)
            ->callAction('deleteAccount', [
                'password' => self::fixturePassword(),
                'confirmation' => 'DELETE MY ACCOUNT',
            ]);

        $this->assertDatabaseHas('tenants', ['id' => $this->tenant->id]);
    }

    public function test_closing_the_account_removes_the_tenant_and_their_data(): void
    {
        $hotspot = Hotspot::factory()->for($this->tenant, 'tenant')->create();
        Hotspot::factory()->for($this->tenant, 'tenant')->create();
        Voucher::factory()->for($this->tenant, 'tenant')->create();

        Livewire::test(Account::class)
            ->callAction('deleteAccount', [
                'password' => self::fixturePassword(),
                'confirmation' => 'DELETE MY ACCOUNT',
            ])
            ->assertHasNoActionErrors();

        $this->assertDatabaseMissing('tenants', ['id' => $this->tenant->id]);
        $this->assertDatabaseMissing('hotspots', ['tenant_id' => $this->tenant->id]);
        $this->assertDatabaseMissing('vouchers', ['tenant_id' => $this->tenant->id]);
    }
}

<?php

namespace Tests\Feature\Filament;

use App\Models\Admin;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Both panels are registered but nothing is cut over yet. These tests pin the
 * access rules now, while the surface is still small enough to reason about.
 *
 * The cross-panel cases matter most: Filament's Authenticate middleware only
 * consults canAccessPanel(), so without the panel-id check in those methods an
 * authenticated tenant would reach the admin console.
 */
class PanelAccessTest extends TestCase
{
    use RefreshDatabase;

    private const TENANT_PANEL = '/app';
    private const ADMIN_PANEL = '/console';

    public function test_guests_are_sent_to_the_tenant_panel_login(): void
    {
        $this->get(self::TENANT_PANEL)->assertRedirect('/app/login');
    }

    public function test_guests_are_sent_to_the_admin_panel_login(): void
    {
        $this->get(self::ADMIN_PANEL)->assertRedirect('/console/login');
    }

    public function test_both_panel_login_pages_render(): void
    {
        $this->get('/app/login')->assertOk();
        $this->get('/console/login')->assertOk();
    }

    public function test_a_tenant_can_reach_the_tenant_panel(): void
    {
        $this->actingAs(Tenant::factory()->create(), 'tenant')
            ->get(self::TENANT_PANEL)
            ->assertOk();
    }

    public function test_an_admin_can_reach_the_admin_panel(): void
    {
        $this->actingAs(Admin::factory()->create(), 'admin')
            ->get(self::ADMIN_PANEL)
            ->assertOk();
    }

    public function test_a_tenant_cannot_reach_the_admin_panel(): void
    {
        $this->actingAs(Tenant::factory()->create(), 'tenant')
            ->get(self::ADMIN_PANEL)
            ->assertRedirect('/console/login');
    }

    public function test_an_admin_cannot_reach_the_tenant_panel(): void
    {
        $this->actingAs(Admin::factory()->create(), 'admin')
            ->get(self::TENANT_PANEL)
            ->assertRedirect('/app/login');
    }

    public function test_a_deactivated_tenant_is_refused_the_tenant_panel(): void
    {
        $this->actingAs(Tenant::factory()->inactive()->create(), 'tenant')
            ->get(self::TENANT_PANEL)
            ->assertForbidden();
    }

    public function test_an_unverified_tenant_is_refused_the_tenant_panel(): void
    {
        $this->actingAs(Tenant::factory()->unverified()->create(), 'tenant')
            ->get(self::TENANT_PANEL)
            ->assertForbidden();
    }

    public function test_a_deactivated_admin_is_refused_the_admin_panel(): void
    {
        $this->actingAs(Admin::factory()->inactive()->create(), 'admin')
            ->get(self::ADMIN_PANEL)
            ->assertForbidden();
    }

    /**
     * The panels must not have taken over the URLs the Blade UI still serves.
     */
    public function test_the_existing_blade_routes_are_untouched(): void
    {
        $this->get('/admin/login')->assertOk();
        $this->get('/login')->assertOk();
        $this->get('/dashboard')->assertRedirect(route('login'));
    }

    /**
     * A 200 alone would not catch a panel that renders as an empty shell, so
     * assert the navigation is really there.
     */
    public function test_the_tenant_panel_renders_its_navigation(): void
    {
        $response = $this->actingAs(Tenant::factory()->create(), 'tenant')->get(self::TENANT_PANEL);

        foreach (['Hotspots', 'Vouchers', 'Sales', 'Withdrawals', 'Account'] as $navItem) {
            $response->assertSee($navItem);
        }
    }

    public function test_the_admin_panel_renders_its_navigation(): void
    {
        $response = $this->actingAs(Admin::factory()->superAdmin()->create(), 'admin')->get(self::ADMIN_PANEL);

        foreach (['Tenants', 'Transactions', 'Withdrawals', 'Staff'] as $navItem) {
            $response->assertSee($navItem);
        }
    }

    /**
     * Widgets are lazy-loaded, so their content is not in the page's first
     * response - it arrives on a follow-up Livewire request. Testing them as
     * components is what actually exercises the queries behind them.
     */
    public function test_the_tenant_widgets_render(): void
    {
        $this->actingAs(Tenant::factory()->create(), 'tenant');

        Livewire::test(\App\Filament\Tenant\Widgets\WalletOverview::class)
            ->assertOk()
            ->assertSee('Wallet balance')
            ->assertSee('Voucher stock');

        Livewire::test(\App\Filament\Tenant\Widgets\SalesChart::class)->assertOk();
    }

    public function test_the_admin_widgets_render(): void
    {
        $this->actingAs(Admin::factory()->create(), 'admin');

        Livewire::test(\App\Filament\Admin\Widgets\PlatformOverview::class)
            ->assertOk()
            ->assertSee('Revenue')
            ->assertSee('Payouts waiting');

        Livewire::test(\App\Filament\Admin\Widgets\RevenueChart::class)->assertOk();
    }
}

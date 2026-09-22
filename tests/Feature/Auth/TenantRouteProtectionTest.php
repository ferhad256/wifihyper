<?php

namespace Tests\Feature\Auth;

use App\Models\Admin;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Nothing behind a panel is reachable without signing in.
 *
 * This began as the net under the tenant-guard refactor, enumerating routes
 * behind the auth.tenant middleware. Those Blade routes are gone and the
 * panels own their own authentication, so it now enumerates the panel routes
 * instead - same idea, still discovering them rather than listing them, so a
 * resource added later is covered without anyone remembering to add it here.
 */
class TenantRouteProtectionTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Parameterless GET routes belonging to a panel, excluding its own login
     * and logout pages, which are necessarily reachable.
     *
     * @return array<int, \Illuminate\Routing\Route>
     */
    private function panelRoutes(string $panel): array
    {
        return collect(Route::getRoutes())
            ->filter(fn ($route) => in_array('GET', $route->methods(), true))
            ->filter(fn ($route) => str_starts_with((string) $route->getName(), "filament.{$panel}."))
            ->filter(fn ($route) => ! str_contains((string) $route->getName(), '.auth.'))
            ->filter(fn ($route) => ! str_contains($route->uri(), '{'))
            ->values()
            ->all();
    }

    public function test_panel_routes_are_discoverable(): void
    {
        // Guards the assertions below from passing on an empty set.
        $this->assertNotEmpty($this->panelRoutes('tenant'));
        $this->assertNotEmpty($this->panelRoutes('admin'));
    }

    public function test_guests_are_sent_to_login_from_every_tenant_panel_route(): void
    {
        foreach ($this->panelRoutes('tenant') as $route) {
            $this->get('/' . ltrim($route->uri(), '/'))
                ->assertRedirect('/dashboard/login');
        }
    }

    public function test_guests_are_sent_to_login_from_every_admin_panel_route(): void
    {
        foreach ($this->panelRoutes('admin') as $route) {
            $this->get('/' . ltrim($route->uri(), '/'))
                ->assertRedirect('/admin/login');
        }
    }

    public function test_a_tenant_reaches_every_tenant_panel_route(): void
    {
        $tenant = Tenant::factory()->create();

        foreach ($this->panelRoutes('tenant') as $route) {
            $this->actingAs($tenant, 'tenant')
                ->get('/' . ltrim($route->uri(), '/'))
                ->assertOk();
        }
    }

    /**
     * A tenant session must not open any admin route, and vice versa. Staff
     * management is excluded from the admin sweep because it is additionally
     * gated to super admins.
     */
    public function test_a_tenant_cannot_reach_any_admin_panel_route(): void
    {
        $tenant = Tenant::factory()->create();

        foreach ($this->panelRoutes('admin') as $route) {
            $this->actingAs($tenant, 'tenant')
                ->get('/' . ltrim($route->uri(), '/'))
                ->assertRedirect('/admin/login');
        }
    }

    public function test_an_admin_cannot_reach_any_tenant_panel_route(): void
    {
        $admin = Admin::factory()->create();

        foreach ($this->panelRoutes('tenant') as $route) {
            $this->actingAs($admin, 'admin')
                ->get('/' . ltrim($route->uri(), '/'))
                ->assertRedirect('/dashboard/login');
        }
    }
}

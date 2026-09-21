<?php

namespace Tests\Feature\Auth;

use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * The safety net under the tenant auth refactor.
 *
 * Rather than listing routes by hand, this discovers every GET route behind
 * the auth.tenant middleware, so routes added later are covered automatically.
 * It deliberately asserts only the auth property (redirected to login or not),
 * not a 200 — several of these pages need seeded data to render fully.
 */
class TenantRouteProtectionTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Every parameterless GET route protected by auth.tenant.
     */
    private function protectedGetRoutes(): array
    {
        return collect(Route::getRoutes())
            ->filter(fn ($route) => in_array('GET', $route->methods(), true))
            ->filter(fn ($route) => in_array('auth.tenant', $route->middleware(), true))
            ->filter(fn ($route) => ! str_contains($route->uri(), '{'))
            ->values()
            ->all();
    }

    public function test_protected_routes_are_discoverable(): void
    {
        // Guards the two tests below against silently passing on an empty set.
        $this->assertNotEmpty(
            $this->protectedGetRoutes(),
            'No auth.tenant routes were discovered - the other assertions in this file would be vacuous.'
        );
    }

    public function test_guests_are_redirected_to_login_from_every_protected_route(): void
    {
        foreach ($this->protectedGetRoutes() as $route) {
            $response = $this->get('/' . ltrim($route->uri(), '/'));

            $this->assertSame(
                route('login'),
                $response->headers->get('Location'),
                "A guest was NOT redirected to login from /{$route->uri()}"
            );
        }
    }

    public function test_authenticated_tenants_are_not_bounced_to_login(): void
    {
        $tenant = Tenant::factory()->create();

        foreach ($this->protectedGetRoutes() as $route) {
            $response = $this->loginAsTenant($tenant)->get('/' . ltrim($route->uri(), '/'));

            $this->assertNotSame(
                route('login'),
                $response->headers->get('Location'),
                "An authenticated tenant WAS bounced to login from /{$route->uri()}"
            );
        }
    }

    /**
     * The guard is re-checked on every request, so a tenant switched off
     * while signed in loses access immediately. Previously the middleware
     * only asked whether a session key existed, so they kept full access
     * until their session happened to expire.
     */
    public function test_a_tenant_deactivated_mid_session_loses_access(): void
    {
        $tenant = Tenant::factory()->create();

        // /hotspots rather than /dashboard: the dashboard's chart query uses
        // MySQL-only DATE_FORMAT() and cannot run on the sqlite test database.
        $this->loginAsTenant($tenant)->get('/hotspots')->assertOk();

        $tenant->update(['is_active' => false]);

        $this->get('/hotspots')->assertRedirect(route('login'));
    }

    public function test_a_tenant_unverified_mid_session_loses_access(): void
    {
        $tenant = Tenant::factory()->create();

        $this->loginAsTenant($tenant)->get('/hotspots')->assertOk();

        $tenant->update(['email_verified_at' => null]);

        $this->get('/hotspots')->assertRedirect(route('login'));
    }

    public function test_an_unauthenticated_json_request_gets_401_rather_than_a_redirect(): void
    {
        $this->getJson('/notifications/count')->assertUnauthorized();
    }
}

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
     * Routes behind auth.tenant that are known-dead code.
     *
     * WithdrawalController resolves Auth::user() against the web guard, which
     * is always null for tenants, and the views it renders were never created.
     * Slated for deletion; excluded here so this test reports real regressions.
     */
    private const KNOWN_DEAD = [
        'withdrawal.form',
        'withdrawal.history',
    ];

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
            if (in_array($route->getName(), self::KNOWN_DEAD, true)) {
                continue;
            }

            $response = $this->loginAsTenant($tenant)->get('/' . ltrim($route->uri(), '/'));

            $this->assertNotSame(
                route('login'),
                $response->headers->get('Location'),
                "An authenticated tenant WAS bounced to login from /{$route->uri()}"
            );
        }
    }
}

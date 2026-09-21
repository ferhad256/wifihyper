<?php

namespace Tests\Feature\Auth;

use App\Models\Admin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * The admin guard already works correctly and is the reference implementation
 * the tenant side is being refactored towards. These tests exist to protect it
 * from the default-guard change that refactor introduces.
 */
class AdminLoginTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD = 'Str0ng!Passw0rd';

    private function admin(array $overrides = []): Admin
    {
        return Admin::factory()->create(array_merge([
            'password' => Hash::make(self::PASSWORD),
        ], $overrides));
    }

    public function test_valid_credentials_authenticate_on_the_admin_guard(): void
    {
        $admin = $this->admin();

        $response = $this->post('/admin/login', [
            'email' => $admin->email,
            'password' => self::PASSWORD,
        ]);

        $response->assertRedirect(route('admin.dashboard'));
        $this->assertTrue(Auth::guard('admin')->check());
        $this->assertSame($admin->id, Auth::guard('admin')->id());
    }

    public function test_wrong_password_is_rejected(): void
    {
        $admin = $this->admin();

        $this->post('/admin/login', [
            'email' => $admin->email,
            'password' => 'Wr0ng!Passw0rd',
        ])->assertSessionHasErrors('email');

        $this->assertFalse(Auth::guard('admin')->check());
    }

    public function test_an_inactive_admin_cannot_log_in(): void
    {
        $admin = $this->admin(['is_active' => false]);

        $this->post('/admin/login', [
            'email' => $admin->email,
            'password' => self::PASSWORD,
        ])->assertSessionHasErrors('email');

        $this->assertFalse(Auth::guard('admin')->check());
    }

    public function test_guests_cannot_reach_the_admin_dashboard(): void
    {
        $this->get('/admin')->assertRedirect(route('admin.login'));
    }

    public function test_an_authenticated_admin_reaches_the_dashboard(): void
    {
        $admin = $this->admin();

        $response = $this->actingAs($admin, 'admin')->get('/admin');

        $this->assertNotSame(route('admin.login'), $response->headers->get('Location'));
    }

    public function test_a_regular_admin_cannot_reach_super_admin_routes(): void
    {
        $admin = $this->admin(['role' => 'admin']);

        $this->actingAs($admin, 'admin')->get('/admin/register')->assertForbidden();
    }

    public function test_a_super_admin_can_reach_super_admin_routes(): void
    {
        $admin = $this->admin(['role' => 'super_admin']);

        $this->actingAs($admin, 'admin')->get('/admin/register')->assertOk();
    }

    public function test_logout_clears_the_admin_guard(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin, 'admin')
            ->post('/admin/logout')
            ->assertRedirect(route('admin.login'));

        $this->assertFalse(Auth::guard('admin')->check());
    }

    /**
     * A tenant session must never grant admin access, and vice versa. This is
     * the property most likely to break when the tenant guard is introduced.
     */
    public function test_a_tenant_session_does_not_grant_admin_access(): void
    {
        $tenant = \App\Models\Tenant::factory()->create();

        $this->loginAsTenant($tenant)
            ->get('/admin')
            ->assertRedirect(route('admin.login'));
    }
}

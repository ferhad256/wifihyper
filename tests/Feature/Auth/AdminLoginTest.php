<?php

namespace Tests\Feature\Auth;

use App\Models\Admin;
use App\Models\Tenant;
use Filament\Auth\Pages\Login;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Admin sign-in, now served by the admin panel.
 *
 * The properties that matter are unchanged: wrong credentials and an inactive
 * account both fail, and neither guard leaks into the other.
 */
class AdminLoginTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD = 'Str0ng!Passw0rd';

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel('admin');
    }

    private function admin(array $overrides = []): Admin
    {
        return Admin::factory()->create(array_merge([
            'password' => Hash::make(self::PASSWORD),
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
        $this->get('/admin/login')->assertOk();
    }

    public function test_valid_credentials_authenticate_on_the_admin_guard(): void
    {
        $admin = $this->admin();

        $this->attempt($admin->email, self::PASSWORD)->assertHasNoFormErrors();

        $this->assertTrue(Auth::guard('admin')->check());
        $this->assertSame($admin->id, Auth::guard('admin')->id());
    }

    public function test_a_wrong_password_is_rejected(): void
    {
        $admin = $this->admin();

        $this->attempt($admin->email, 'Wr0ng!Passw0rd')->assertHasFormErrors(['email']);

        $this->assertFalse(Auth::guard('admin')->check());
    }

    public function test_an_inactive_admin_cannot_sign_in(): void
    {
        $admin = $this->admin(['is_active' => false]);

        $this->attempt($admin->email, self::PASSWORD)->assertHasFormErrors(['email']);

        $this->assertFalse(Auth::guard('admin')->check());
    }

    public function test_guests_cannot_reach_the_console(): void
    {
        $this->get('/admin')->assertRedirect('/admin/login');
    }

    public function test_an_authenticated_admin_reaches_the_console(): void
    {
        $this->actingAs($this->admin(), 'admin')->get('/admin')->assertOk();
    }

    public function test_a_regular_admin_cannot_reach_staff_management(): void
    {
        $this->actingAs($this->admin(['role' => 'admin']), 'admin')
            ->get('/admin/admins')
            ->assertForbidden();
    }

    public function test_a_super_admin_can_reach_staff_management(): void
    {
        $this->actingAs($this->admin(['role' => 'super_admin']), 'admin')
            ->get('/admin/admins')
            ->assertOk();
    }

    public function test_signing_out_clears_the_admin_guard(): void
    {
        $this->actingAs($this->admin(), 'admin')
            ->post('/admin/logout')
            ->assertRedirect();

        $this->assertFalse(Auth::guard('admin')->check());
    }

    /**
     * The property most likely to break when two session guards coexist.
     */
    public function test_a_tenant_session_does_not_grant_console_access(): void
    {
        $this->actingAs(Tenant::factory()->create(), 'tenant')
            ->get('/admin')
            ->assertRedirect('/admin/login');
    }
}

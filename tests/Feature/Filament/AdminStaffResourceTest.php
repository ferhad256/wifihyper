<?php

namespace Tests\Feature\Filament;

use App\Filament\Admin\Resources\Admins\AdminResource;
use App\Filament\Admin\Resources\Admins\Pages\CreateAdmin;
use App\Models\Admin;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class AdminStaffResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel('admin');
    }

    private function asSuperAdmin(): Admin
    {
        $admin = Admin::factory()->superAdmin()->create();
        $this->actingAs($admin, 'admin');

        return $admin;
    }

    public function test_a_super_admin_can_open_the_staff_list(): void
    {
        $this->asSuperAdmin();

        $this->get('/admin/admins')->assertOk();
    }

    /**
     * Replaces the SuperAdminMiddleware wrapper the Blade routes used.
     */
    public function test_a_regular_admin_cannot_reach_the_staff_list(): void
    {
        $this->actingAs(Admin::factory()->create(['role' => 'admin']), 'admin');

        $this->get('/admin/admins')->assertForbidden();
    }

    public function test_the_resource_is_hidden_from_a_regular_admin(): void
    {
        $this->actingAs(Admin::factory()->create(['role' => 'admin']), 'admin');
        $this->assertFalse(AdminResource::canAccess());

        $this->actingAs(Admin::factory()->superAdmin()->create(), 'admin');
        $this->assertTrue(AdminResource::canAccess());
    }

    public function test_a_super_admin_can_create_staff(): void
    {
        $this->asSuperAdmin();

        Livewire::test(CreateAdmin::class)
            ->fillForm([
                'name' => 'New Operator',
                'email' => 'newstaff@example.com',
                'password' => 'Str0ng!Passw0rd',
                'password_confirmation' => 'Str0ng!Passw0rd',
                'role' => 'admin',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('admins', ['email' => 'newstaff@example.com', 'role' => 'admin']);
    }

    /**
     * The model's hashed cast does the hashing; a plain-text password reaching
     * the column would mean nobody could ever sign in with it.
     */
    public function test_a_created_staff_password_is_usable(): void
    {
        $this->asSuperAdmin();

        Livewire::test(CreateAdmin::class)
            ->fillForm([
                'name' => 'New Operator',
                'email' => 'newstaff@example.com',
                'password' => 'Str0ng!Passw0rd',
                'password_confirmation' => 'Str0ng!Passw0rd',
                'role' => 'admin',
            ])
            ->call('create');

        $admin = Admin::where('email', 'newstaff@example.com')->first();

        $this->assertTrue(Hash::check('Str0ng!Passw0rd', $admin->password));
    }

    public function test_a_mismatched_password_confirmation_is_rejected(): void
    {
        $this->asSuperAdmin();

        Livewire::test(CreateAdmin::class)
            ->fillForm([
                'name' => 'New Operator',
                'email' => 'newstaff@example.com',
                'password' => 'Str0ng!Passw0rd',
                'password_confirmation' => 'something-else',
                'role' => 'admin',
            ])
            ->call('create')
            ->assertHasFormErrors(['password']);
    }

    public function test_staff_accounts_cannot_be_deleted(): void
    {
        $this->asSuperAdmin();
        $other = Admin::factory()->create();

        $this->assertFalse(AdminResource::canDelete($other));
    }
}

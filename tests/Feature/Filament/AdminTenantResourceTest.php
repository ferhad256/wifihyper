<?php

namespace Tests\Feature\Filament;

use App\Filament\Admin\Resources\Tenants\Pages\ListTenants;
use App\Filament\Admin\Resources\Tenants\TenantResource;
use App\Models\Admin;
use App\Models\Hotspot;
use App\Models\Package;
use App\Models\Tenant;
use App\Models\Transaction;
use App\Models\Voucher;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Livewire\Livewire;
use Tests\TestCase;

class AdminTenantResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // The tenant panel is the default one, so a Livewire component tested
        // in isolation would otherwise resolve its URLs against that panel and
        // fail looking for routes this resource does not have there.
        Filament::setCurrentPanel('admin');

        $this->actingAs(Admin::factory()->create(), 'admin');
    }

    public function test_the_list_page_loads(): void
    {
        $this->get('/admin/tenants')->assertOk();
    }

    /**
     * The admin console is deliberately unscoped - it sees every operator.
     */
    public function test_every_tenant_is_listed(): void
    {
        $a = Tenant::factory()->create();
        $b = Tenant::factory()->create();

        Livewire::test(ListTenants::class)->assertCanSeeTableRecords([$a, $b]);
    }

    public function test_tenants_cannot_be_created_or_edited_from_the_console(): void
    {
        $tenant = Tenant::factory()->create();

        $this->assertFalse(TenantResource::canCreate());
        $this->assertFalse(TenantResource::canEdit($tenant));
    }

    public function test_a_tenant_can_be_deactivated_and_reactivated(): void
    {
        $tenant = Tenant::factory()->create(['is_active' => true]);

        Livewire::test(ListTenants::class)
            ->callAction(TestAction::make('toggleStatus')->table($tenant));

        $this->assertFalse($tenant->fresh()->is_active);

        Livewire::test(ListTenants::class)
            ->callAction(TestAction::make('toggleStatus')->table($tenant));

        $this->assertTrue($tenant->fresh()->is_active);
    }

    public function test_deleting_a_tenant_requires_their_email_typed_out(): void
    {
        $tenant = Tenant::factory()->create();

        Livewire::test(ListTenants::class)
            ->callAction(TestAction::make('deleteWithData')->table($tenant), [
                'confirmation' => 'not-the-email',
            ])
            ->assertHasActionErrors(['confirmation']);

        $this->assertDatabaseHas('tenants', ['id' => $tenant->id]);
    }

    /**
     * The cascade must reach every table that references the tenant. The
     * self-service copy of this used a scalar subquery for packages, which
     * threw as soon as a tenant owned two hotspots.
     */
    public function test_deleting_a_tenant_removes_all_of_their_data(): void
    {
        $tenant = Tenant::factory()->create();

        $first = Hotspot::factory()->for($tenant, 'tenant')->create();
        $second = Hotspot::factory()->for($tenant, 'tenant')->create();
        Package::factory()->for($first)->create();
        Package::factory()->for($second)->create();
        Voucher::factory()->for($tenant, 'tenant')->create();
        Transaction::factory()->for($tenant, 'tenant')->create();

        Livewire::test(ListTenants::class)
            ->callAction(TestAction::make('deleteWithData')->table($tenant), [
                'confirmation' => $tenant->email,
            ])
            ->assertHasNoActionErrors();

        $this->assertDatabaseMissing('tenants', ['id' => $tenant->id]);
        $this->assertDatabaseMissing('hotspots', ['tenant_id' => $tenant->id]);
        $this->assertDatabaseMissing('vouchers', ['tenant_id' => $tenant->id]);
        $this->assertDatabaseMissing('transactions', ['tenant_id' => $tenant->id]);
        $this->assertSame(0, Package::whereIn('hotspot_id', [$first->id, $second->id])->count());
    }

    public function test_deleting_one_tenant_leaves_another_untouched(): void
    {
        $doomed = Tenant::factory()->create();
        $bystander = Tenant::factory()->create();

        Hotspot::factory()->for($bystander, 'tenant')->create();
        Voucher::factory()->for($bystander, 'tenant')->create();

        Livewire::test(ListTenants::class)
            ->callAction(TestAction::make('deleteWithData')->table($doomed), [
                'confirmation' => $doomed->email,
            ]);

        $this->assertDatabaseHas('tenants', ['id' => $bystander->id]);
        $this->assertSame(1, Hotspot::where('tenant_id', $bystander->id)->count());
        $this->assertSame(1, Voucher::where('tenant_id', $bystander->id)->count());
    }

    public function test_a_tenant_cannot_reach_the_admin_tenant_list(): void
    {
        // setUp() signed in an admin; drop that guard or both would be
        // authenticated at once and the page would legitimately render.
        Auth::guard('admin')->logout();

        $this->actingAs(Tenant::factory()->create(), 'tenant')
            ->get('/admin/tenants')
            ->assertRedirect('/admin/login');
    }
}

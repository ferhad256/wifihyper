<?php

namespace Tests\Feature;

use App\Models\Hotspot;
use App\Models\Package;
use App\Models\Tenant;
use App\Models\Voucher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Multi-tenant data isolation.
 *
 * This is the security property that matters most in this codebase, and it is
 * currently enforced by ownership checks hand-written into each controller
 * action. Those checks are duplicated, and duplicated checks drift.
 *
 * Every assertion here is about STATE, not about the response body or redirect
 * target, so this file is intended to survive the move to Filament resources
 * unchanged and re-validate the new query scoping.
 */
class TenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $alice;
    private Tenant $bob;

    protected function setUp(): void
    {
        parent::setUp();

        $this->alice = Tenant::factory()->create();
        $this->bob = Tenant::factory()->create();
    }

    public function test_a_tenant_cannot_view_another_tenants_hotspot(): void
    {
        $hotspot = Hotspot::factory()->for($this->bob, 'tenant')->create();

        $this->loginAsTenant($this->alice)
            ->get("/hotspots/{$hotspot->id}")
            ->assertSessionHas('error', 'Unauthorized action.');
    }

    public function test_a_tenant_cannot_edit_another_tenants_hotspot(): void
    {
        $hotspot = Hotspot::factory()->for($this->bob, 'tenant')->create(['name' => 'Bob Cafe']);

        $this->loginAsTenant($this->alice)->put("/hotspots/{$hotspot->id}", [
            'name' => 'Stolen By Alice',
            'ssid' => 'stolen',
            'location' => 'Nowhere',
        ]);

        $this->assertSame('Bob Cafe', $hotspot->fresh()->name, "Alice renamed Bob's hotspot.");
    }

    public function test_a_tenant_cannot_delete_another_tenants_hotspot(): void
    {
        $hotspot = Hotspot::factory()->for($this->bob, 'tenant')->create();

        $this->loginAsTenant($this->alice)->delete("/hotspots/{$hotspot->id}");

        $this->assertDatabaseHas('hotspots', ['id' => $hotspot->id]);
    }

    public function test_a_tenant_cannot_delete_another_tenants_voucher(): void
    {
        $voucher = Voucher::factory()->for($this->bob, 'tenant')->create();

        $this->loginAsTenant($this->alice)->delete("/vouchers/{$voucher->id}");

        $this->assertDatabaseHas('vouchers', ['id' => $voucher->id]);
    }

    public function test_a_tenant_cannot_list_another_tenants_packages(): void
    {
        $hotspot = Hotspot::factory()->for($this->bob, 'tenant')->create();
        Package::factory()->for($hotspot)->create(['name' => 'Bob Secret Package']);

        $response = $this->loginAsTenant($this->alice)->get("/hotspots/{$hotspot->id}/packages");

        $response->assertDontSee('Bob Secret Package');
    }

    /**
     * Packages have no tenant_id - ownership is transitive through the
     * hotspot. That makes this the likeliest place for an isolation hole.
     */
    public function test_a_tenant_cannot_delete_a_package_under_another_tenants_hotspot(): void
    {
        $hotspot = Hotspot::factory()->for($this->bob, 'tenant')->create();
        $package = Package::factory()->for($hotspot)->create();

        $this->loginAsTenant($this->alice)
            ->delete("/hotspots/{$hotspot->id}/packages/{$package->id}");

        $this->assertDatabaseHas('packages', ['id' => $package->id]);
    }

    public function test_a_tenant_cannot_add_a_package_to_another_tenants_hotspot(): void
    {
        $hotspot = Hotspot::factory()->for($this->bob, 'tenant')->create();

        $this->loginAsTenant($this->alice)->post("/hotspots/{$hotspot->id}/packages", [
            'name' => 'Alice Injected Package',
            'price' => 1000,
            'duration_hours' => 1,
            'duration_unit' => 'hours',
        ]);

        $this->assertDatabaseMissing('packages', ['name' => 'Alice Injected Package']);
    }

    public function test_the_hotspot_list_only_shows_a_tenants_own_hotspots(): void
    {
        Hotspot::factory()->for($this->alice, 'tenant')->create(['name' => 'Alice Place']);
        Hotspot::factory()->for($this->bob, 'tenant')->create(['name' => 'Bob Place']);

        $response = $this->loginAsTenant($this->alice)->get('/hotspots');

        $response->assertSee('Alice Place');
        $response->assertDontSee('Bob Place');
    }

    public function test_the_voucher_list_only_shows_a_tenants_own_vouchers(): void
    {
        $aliceVoucher = Voucher::factory()->for($this->alice, 'tenant')->create(['code' => 'ALICE001']);
        $bobVoucher = Voucher::factory()->for($this->bob, 'tenant')->create(['code' => 'BOBB0001']);

        $response = $this->loginAsTenant($this->alice)->get('/vouchers');

        $response->assertDontSee($bobVoucher->code);
    }
}

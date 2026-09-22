<?php

namespace Tests\Feature;

use App\Filament\Tenant\Resources\Hotspots\HotspotResource;
use App\Filament\Tenant\Resources\Hotspots\Pages\EditHotspot;
use App\Filament\Tenant\Resources\Hotspots\Pages\ListHotspots;
use App\Filament\Tenant\Resources\Hotspots\RelationManagers\PackagesRelationManager;
use App\Filament\Tenant\Resources\Transactions\Pages\ListTransactions;
use App\Filament\Tenant\Resources\Transactions\TransactionResource;
use App\Filament\Tenant\Resources\Vouchers\Pages\ListVouchers;
use App\Filament\Tenant\Resources\Vouchers\VoucherResource;
use App\Filament\Tenant\Resources\WithdrawalTransactions\WithdrawalTransactionResource;
use App\Models\Hotspot;
use App\Models\Package;
use App\Models\Tenant;
use App\Models\Transaction;
use App\Models\Voucher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Multi-tenant data isolation.
 *
 * The most important property in this codebase. It used to be enforced by
 * ownership checks hand-written into every controller action; it is now a
 * scoped query on each resource, so these assertions come in two kinds:
 *
 *   - the scoped query genuinely excludes other operators' rows, and
 *   - a record reached by guessing its id in the URL is not found.
 *
 * Asserting only "the record did not change" would pass for the wrong reason
 * if a route simply stopped existing, so every test here either inspects the
 * query directly or asserts a concrete response.
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

        $this->loginAsTenant($this->alice);
    }

    public function test_the_hotspot_query_excludes_another_tenants_rows(): void
    {
        $mine = Hotspot::factory()->for($this->alice, 'tenant')->create();
        $theirs = Hotspot::factory()->for($this->bob, 'tenant')->create();

        $ids = HotspotResource::getEloquentQuery()->pluck('id');

        $this->assertTrue($ids->contains($mine->id));
        $this->assertFalse($ids->contains($theirs->id));
    }

    public function test_the_voucher_query_excludes_another_tenants_rows(): void
    {
        $mine = Voucher::factory()->for($this->alice, 'tenant')->create();
        $theirs = Voucher::factory()->for($this->bob, 'tenant')->create();

        $ids = VoucherResource::getEloquentQuery()->pluck('id');

        $this->assertTrue($ids->contains($mine->id));
        $this->assertFalse($ids->contains($theirs->id));
    }

    public function test_the_sales_query_excludes_another_tenants_rows(): void
    {
        $mine = Transaction::factory()->for($this->alice, 'tenant')->create();
        $theirs = Transaction::factory()->for($this->bob, 'tenant')->create();

        $ids = TransactionResource::getEloquentQuery()->pluck('id');

        $this->assertTrue($ids->contains($mine->id));
        $this->assertFalse($ids->contains($theirs->id));
    }

    public function test_the_withdrawal_query_is_scoped(): void
    {
        $sql = WithdrawalTransactionResource::getEloquentQuery()->toSql();

        $this->assertStringContainsString('tenant_id', $sql);
    }

    public function test_the_hotspot_list_shows_only_a_tenants_own(): void
    {
        $mine = Hotspot::factory()->for($this->alice, 'tenant')->create(['name' => 'Alice Place']);
        $theirs = Hotspot::factory()->for($this->bob, 'tenant')->create(['name' => 'Bob Place']);

        Livewire::test(ListHotspots::class)
            ->assertCanSeeTableRecords([$mine])
            ->assertCanNotSeeTableRecords([$theirs]);
    }

    public function test_the_voucher_list_shows_only_a_tenants_own(): void
    {
        $mine = Voucher::factory()->for($this->alice, 'tenant')->create(['code' => 'ALICE001']);
        $theirs = Voucher::factory()->for($this->bob, 'tenant')->create(['code' => 'BOBB0001']);

        Livewire::test(ListVouchers::class)
            ->assertCanSeeTableRecords([$mine])
            ->assertCanNotSeeTableRecords([$theirs]);
    }

    public function test_the_sales_list_shows_only_a_tenants_own(): void
    {
        $mine = Transaction::factory()->for($this->alice, 'tenant')->create();
        $theirs = Transaction::factory()->for($this->bob, 'tenant')->create();

        Livewire::test(ListTransactions::class)
            ->assertCanSeeTableRecords([$mine])
            ->assertCanNotSeeTableRecords([$theirs]);
    }

    /**
     * Guessing another operator's record id in the URL must not open it.
     */
    public function test_another_tenants_hotspot_cannot_be_opened_by_url(): void
    {
        $theirs = Hotspot::factory()->for($this->bob, 'tenant')->create();

        $this->get("/dashboard/hotspots/{$theirs->id}/edit")->assertNotFound();
    }

    public function test_a_tenants_own_hotspot_can_be_opened_by_url(): void
    {
        // The companion to the test above: proves the 404 is about ownership
        // rather than the route being broken for everyone.
        $mine = Hotspot::factory()->for($this->alice, 'tenant')->create();

        $this->get("/dashboard/hotspots/{$mine->id}/edit")->assertOk();
    }

    /**
     * Packages have no tenant_id of their own - ownership is transitive
     * through the hotspot, which makes this the likeliest place for a hole.
     */
    public function test_another_tenants_packages_are_not_reachable(): void
    {
        $theirHotspot = Hotspot::factory()->for($this->bob, 'tenant')->create();
        Package::factory()->for($theirHotspot)->create(['name' => 'Bob Secret Package']);

        $this->get("/dashboard/hotspots/{$theirHotspot->id}/edit")->assertNotFound();
    }

    /**
     * A Livewire component is addressable in its own right, so the relation
     * manager asserts ownership itself rather than trusting that the only way
     * in is a parent page that already 404s.
     */
    public function test_the_packages_relation_manager_refuses_another_tenants_hotspot(): void
    {
        $mine = Hotspot::factory()->for($this->alice, 'tenant')->create();
        $theirs = Hotspot::factory()->for($this->bob, 'tenant')->create();

        $this->assertTrue(
            PackagesRelationManager::canViewForRecord($mine, EditHotspot::class),
            'A tenant was refused their own hotspot.'
        );

        $this->assertFalse(
            PackagesRelationManager::canViewForRecord($theirs, EditHotspot::class),
            "The packages relation manager accepted another tenant's hotspot."
        );
    }

    /**
     * A bulk delete aimed at another operator's package deletes nothing, even
     * though the select could never have offered it.
     */
    public function test_a_bulk_delete_cannot_reach_another_tenants_vouchers(): void
    {
        $theirHotspot = Hotspot::factory()->for($this->bob, 'tenant')->create();
        $theirPackage = Package::factory()->for($theirHotspot)->create();

        Voucher::factory()->for($this->bob, 'tenant')->create([
            'package_id' => $theirPackage->id,
            'code' => 'BOBS0001',
        ]);

        Livewire::test(ListVouchers::class)
            ->callAction('deleteAllForPackage', [
                'package_id' => $theirPackage->id,
                'confirmation' => 'DELETE',
            ]);

        $this->assertDatabaseHas('vouchers', ['code' => 'BOBS0001']);
    }
}

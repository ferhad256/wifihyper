<?php

namespace Tests\Feature\Filament;

use App\Filament\Tenant\Resources\Vouchers\Pages\ListVouchers;
use App\Models\Hotspot;
use App\Models\Package;
use App\Models\Tenant;
use App\Models\Voucher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class VoucherResourceTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $alice;
    private Tenant $bob;
    private Package $alicePackage;

    protected function setUp(): void
    {
        parent::setUp();

        $this->alice = Tenant::factory()->create();
        $this->bob = Tenant::factory()->create();

        $this->alicePackage = Package::factory()
            ->for(Hotspot::factory()->for($this->alice, 'tenant'))
            ->create();

        $this->actingAs($this->alice, 'tenant');
    }

    public function test_the_list_page_loads(): void
    {
        $this->get('/app/vouchers')->assertOk();
    }

    /**
     * The isolation property the resource's getEloquentQuery() exists for.
     */
    public function test_only_the_signed_in_tenants_vouchers_are_listed(): void
    {
        $mine = Voucher::factory()->for($this->alice, 'tenant')->create(['code' => 'MINE0001']);
        $theirs = Voucher::factory()->for($this->bob, 'tenant')->create(['code' => 'THEIRS01']);

        Livewire::test(ListVouchers::class)
            ->assertCanSeeTableRecords([$mine])
            ->assertCanNotSeeTableRecords([$theirs]);
    }

    public function test_a_voucher_can_be_created_and_derives_its_hotspot_from_the_package(): void
    {
        Livewire::test(\App\Filament\Tenant\Resources\Vouchers\Pages\CreateVoucher::class)
            ->fillForm([
                'code' => 'NEWCODE1',
                'package_id' => $this->alicePackage->id,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('vouchers', [
            'code' => 'NEWCODE1',
            'tenant_id' => $this->alice->id,
            'hotspot_id' => $this->alicePackage->hotspot_id,
            'status' => 'unused',
        ]);
    }

    public function test_pasted_codes_are_imported(): void
    {
        Livewire::test(ListVouchers::class)
            ->callAction('importPasted', [
                'package_id' => $this->alicePackage->id,
                'codes' => "ABC123\nDEF456, GHI789",
            ]);

        foreach (['ABC123', 'DEF456', 'GHI789'] as $code) {
            $this->assertDatabaseHas('vouchers', [
                'code' => $code,
                'tenant_id' => $this->alice->id,
                'hotspot_id' => $this->alicePackage->hotspot_id,
            ]);
        }
    }

    public function test_malformed_pasted_codes_are_rejected_not_imported(): void
    {
        Livewire::test(ListVouchers::class)
            ->callAction('importPasted', [
                'package_id' => $this->alicePackage->id,
                'codes' => "GOOD123\nno!\nway-too-long-to-be-a-valid-code",
            ]);

        $this->assertDatabaseHas('vouchers', ['code' => 'GOOD123']);
        $this->assertDatabaseMissing('vouchers', ['code' => 'no!']);
        $this->assertSame(1, Voucher::count());
    }

    public function test_a_code_already_in_use_is_skipped_rather_than_duplicated(): void
    {
        Voucher::factory()->for($this->bob, 'tenant')->create(['code' => 'TAKEN01']);

        Livewire::test(ListVouchers::class)
            ->callAction('importPasted', [
                'package_id' => $this->alicePackage->id,
                'codes' => "TAKEN01\nFRESH01",
            ]);

        $this->assertDatabaseHas('vouchers', ['code' => 'FRESH01', 'tenant_id' => $this->alice->id]);
        $this->assertSame(1, Voucher::where('code', 'TAKEN01')->count());
        $this->assertSame($this->bob->id, Voucher::where('code', 'TAKEN01')->first()->tenant_id);
    }

    /**
     * A package belonging to someone else must not be usable as an import
     * target, even though the select would never offer it.
     */
    public function test_importing_into_another_tenants_package_is_refused(): void
    {
        $bobPackage = Package::factory()
            ->for(Hotspot::factory()->for($this->bob, 'tenant'))
            ->create();

        Livewire::test(ListVouchers::class)
            ->callAction('importPasted', [
                'package_id' => $bobPackage->id,
                'codes' => 'SNEAKY01',
            ]);

        $this->assertDatabaseMissing('vouchers', ['code' => 'SNEAKY01']);
    }

    public function test_deleting_unused_stock_for_a_package_spares_used_vouchers(): void
    {
        Voucher::factory()->for($this->alice, 'tenant')->create([
            'package_id' => $this->alicePackage->id,
            'code' => 'UNUSED01',
        ]);
        Voucher::factory()->for($this->alice, 'tenant')->used()->create([
            'package_id' => $this->alicePackage->id,
            'code' => 'SOLD0001',
        ]);

        Livewire::test(ListVouchers::class)
            ->callAction('deleteAllForPackage', [
                'package_id' => $this->alicePackage->id,
                'confirmation' => 'DELETE',
            ]);

        $this->assertDatabaseMissing('vouchers', ['code' => 'UNUSED01']);
        $this->assertDatabaseHas('vouchers', ['code' => 'SOLD0001']);
    }

    public function test_bulk_delete_requires_the_typed_confirmation(): void
    {
        Voucher::factory()->for($this->alice, 'tenant')->create([
            'package_id' => $this->alicePackage->id,
            'code' => 'KEEPME01',
        ]);

        Livewire::test(ListVouchers::class)
            ->callAction('deleteAllForPackage', [
                'package_id' => $this->alicePackage->id,
                'confirmation' => 'delete',
            ])
            ->assertHasActionErrors(['confirmation']);

        $this->assertDatabaseHas('vouchers', ['code' => 'KEEPME01']);
    }

    public function test_bulk_delete_cannot_reach_another_tenants_package(): void
    {
        $bobPackage = Package::factory()
            ->for(Hotspot::factory()->for($this->bob, 'tenant'))
            ->create();

        Voucher::factory()->for($this->bob, 'tenant')->create([
            'package_id' => $bobPackage->id,
            'code' => 'BOBS0001',
        ]);

        Livewire::test(ListVouchers::class)
            ->callAction('deleteAllForPackage', [
                'package_id' => $bobPackage->id,
                'confirmation' => 'DELETE',
            ]);

        $this->assertDatabaseHas('vouchers', ['code' => 'BOBS0001']);
    }
}

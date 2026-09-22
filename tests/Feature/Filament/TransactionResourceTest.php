<?php

namespace Tests\Feature\Filament;

use App\Filament\Tenant\Resources\Transactions\Pages\ListTransactions;
use App\Models\Tenant;
use App\Models\Transaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class TransactionResourceTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $alice;
    private Tenant $bob;

    protected function setUp(): void
    {
        parent::setUp();

        $this->alice = Tenant::factory()->create();
        $this->bob = Tenant::factory()->create();

        $this->actingAs($this->alice, 'tenant');
    }

    public function test_the_list_page_loads(): void
    {
        $this->get('/dashboard/transactions')->assertOk();
    }

    public function test_only_the_signed_in_tenants_sales_are_listed(): void
    {
        $mine = Transaction::factory()->for($this->alice, 'tenant')->create();
        $theirs = Transaction::factory()->for($this->bob, 'tenant')->create();

        Livewire::test(ListTransactions::class)
            ->assertCanSeeTableRecords([$mine])
            ->assertCanNotSeeTableRecords([$theirs]);
    }

    /**
     * Sales records are written by the payment flow. Nothing in the operator
     * UI may create, edit or delete one.
     */
    public function test_the_resource_is_read_only(): void
    {
        $transaction = Transaction::factory()->for($this->alice, 'tenant')->create();

        $this->assertFalse(\App\Filament\Tenant\Resources\Transactions\TransactionResource::canCreate());
        $this->assertFalse(\App\Filament\Tenant\Resources\Transactions\TransactionResource::canEdit($transaction));
        $this->assertFalse(\App\Filament\Tenant\Resources\Transactions\TransactionResource::canDelete($transaction));
    }

    public function test_there_is_no_create_or_edit_route(): void
    {
        $this->get('/dashboard/transactions/create')->assertNotFound();
    }

    public function test_sales_can_be_filtered_by_status(): void
    {
        $completed = Transaction::factory()->for($this->alice, 'tenant')->create();
        $failed = Transaction::factory()->for($this->alice, 'tenant')->failed()->create();

        Livewire::test(ListTransactions::class)
            ->filterTable('status', 'completed')
            ->assertCanSeeTableRecords([$completed])
            ->assertCanNotSeeTableRecords([$failed]);
    }

    /**
     * The export follows the current filters rather than dumping everything,
     * which is what someone who has just filtered to a date range expects.
     */
    public function test_the_export_respects_the_active_filter(): void
    {
        Transaction::factory()->for($this->alice, 'tenant')->create(['transaction_id' => 'TXN_KEEP']);
        Transaction::factory()->for($this->alice, 'tenant')->failed()->create(['transaction_id' => 'TXN_DROP']);

        $csv = $this->csvFrom(
            Livewire::test(ListTransactions::class)
                ->filterTable('status', 'completed')
                ->callAction('export')
        );

        $this->assertStringContainsString('TXN_KEEP', $csv);
        $this->assertStringNotContainsString('TXN_DROP', $csv);
    }

    public function test_the_export_never_includes_another_tenants_sales(): void
    {
        Transaction::factory()->for($this->alice, 'tenant')->create(['transaction_id' => 'TXN_MINE']);
        Transaction::factory()->for($this->bob, 'tenant')->create(['transaction_id' => 'TXN_THEIRS']);

        $csv = $this->csvFrom(Livewire::test(ListTransactions::class)->callAction('export'));

        $this->assertStringContainsString('TXN_MINE', $csv);
        $this->assertStringNotContainsString('TXN_THEIRS', $csv);
    }

    public function test_the_export_carries_the_same_columns_as_the_blade_export(): void
    {
        Transaction::factory()->for($this->alice, 'tenant')->create();

        $csv = $this->csvFrom(Livewire::test(ListTransactions::class)->callAction('export'));

        $this->assertStringStartsWith(
            'Date,"Transaction ID",Hotspot,Package,"Voucher Code",Amount,Fee,"Net Amount","Phone Number",Status',
            trim($csv)
        );
    }

    /**
     * Livewire captures a streamed download into its effects payload, base64
     * encoded, rather than writing it to the response.
     */
    private function csvFrom(\Livewire\Features\SupportTesting\Testable $test): string
    {
        $test->assertFileDownloaded();

        return base64_decode(data_get($test->effects, 'download.content'));
    }
}

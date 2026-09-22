<?php

namespace Tests\Feature\Filament;

use App\Filament\Admin\Resources\WithdrawalTransactions\Pages\ListWithdrawalTransactions;
use App\Models\Admin;
use App\Models\Tenant;
use App\Models\WithdrawalTransaction;
use App\Services\WithdrawalApprovalService;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AdminWithdrawalResourceTest extends TestCase
{
    use RefreshDatabase;

    private Admin $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel('admin');

        $this->admin = Admin::factory()->create();
        $this->actingAs($this->admin, 'admin');
    }

    private function pendingWithdrawal(Tenant $tenant, float $amount = 5000): WithdrawalTransaction
    {
        return WithdrawalTransaction::create([
            'tenant_id' => $tenant->id,
            'withdrawal_id' => 'WD_' . uniqid(),
            'amount' => $amount,
            'fee' => $amount * 0.05,
            'net_amount' => $amount - ($amount * 0.05),
            'phone_number' => '256700000000',
            'currency' => 'UGX',
            'status' => 'pending',
        ]);
    }

    public function test_the_list_page_loads(): void
    {
        $this->get('/console/withdrawal-transactions')->assertOk();
    }

    public function test_approving_deducts_the_amount_from_the_wallet(): void
    {
        $tenant = Tenant::factory()->create(['wallet_balance' => 20000]);
        $withdrawal = $this->pendingWithdrawal($tenant, 5000);

        Livewire::test(ListWithdrawalTransactions::class)
            ->callAction(TestAction::make('approve')->table($withdrawal), ['admin_notes' => 'Paid via MoMo']);

        $this->assertSame('completed', $withdrawal->fresh()->status);
        $this->assertSame('15000.00', $tenant->fresh()->wallet_balance);
        $this->assertSame($this->admin->id, $withdrawal->fresh()->admin_id);
    }

    /**
     * The controller called decrement() with no balance check, so a tenant
     * whose balance had fallen since requesting went negative.
     */
    public function test_approving_is_refused_when_the_wallet_no_longer_covers_it(): void
    {
        $tenant = Tenant::factory()->create(['wallet_balance' => 1000]);
        $withdrawal = $this->pendingWithdrawal($tenant, 5000);

        Livewire::test(ListWithdrawalTransactions::class)
            ->callAction(TestAction::make('approve')->table($withdrawal), ['admin_notes' => null]);

        $this->assertSame('pending', $withdrawal->fresh()->status);
        $this->assertSame('1000.00', $tenant->fresh()->wallet_balance);
    }

    public function test_a_wallet_can_never_be_driven_negative(): void
    {
        $tenant = Tenant::factory()->create(['wallet_balance' => 0]);
        $withdrawal = $this->pendingWithdrawal($tenant, 5000);

        app(WithdrawalApprovalService::class)->approve($withdrawal, $this->admin, null);

        $this->assertGreaterThanOrEqual(0, (float) $tenant->fresh()->wallet_balance);
    }

    /**
     * The controller read the status BEFORE opening its transaction, so two
     * admins approving at the same moment could both deduct. The status is
     * now re-read inside the transaction.
     */
    public function test_a_second_approval_deducts_nothing_further(): void
    {
        $tenant = Tenant::factory()->create(['wallet_balance' => 20000]);
        $withdrawal = $this->pendingWithdrawal($tenant, 5000);

        $service = app(WithdrawalApprovalService::class);

        $first = $service->approve($withdrawal, $this->admin, null);
        $second = $service->approve($withdrawal, $this->admin, null);

        $this->assertTrue($first['ok']);
        $this->assertFalse($second['ok'], 'A withdrawal was approved twice.');
        $this->assertSame('15000.00', $tenant->fresh()->wallet_balance);
    }

    public function test_rejecting_requires_a_reason(): void
    {
        $tenant = Tenant::factory()->create(['wallet_balance' => 20000]);
        $withdrawal = $this->pendingWithdrawal($tenant);

        Livewire::test(ListWithdrawalTransactions::class)
            ->callAction(TestAction::make('reject')->table($withdrawal), ['admin_notes' => ''])
            ->assertHasActionErrors(['admin_notes']);

        $this->assertSame('pending', $withdrawal->fresh()->status);
    }

    public function test_rejecting_records_the_reason_and_leaves_the_wallet_alone(): void
    {
        $tenant = Tenant::factory()->create(['wallet_balance' => 20000]);
        $withdrawal = $this->pendingWithdrawal($tenant);

        Livewire::test(ListWithdrawalTransactions::class)
            ->callAction(TestAction::make('reject')->table($withdrawal), [
                'admin_notes' => 'Phone number does not match records',
            ]);

        $fresh = $withdrawal->fresh();

        $this->assertSame('failed', $fresh->status);
        $this->assertSame('Phone number does not match records', $fresh->admin_notes);
        $this->assertNotNull($fresh->rejected_at);
        // Funds are only deducted on approval, so nothing to return.
        $this->assertSame('20000.00', $tenant->fresh()->wallet_balance);
    }

    public function test_an_already_processed_withdrawal_cannot_be_rejected(): void
    {
        $tenant = Tenant::factory()->create(['wallet_balance' => 20000]);
        $withdrawal = $this->pendingWithdrawal($tenant);

        $service = app(WithdrawalApprovalService::class);
        $service->approve($withdrawal, $this->admin, null);

        $result = $service->reject($withdrawal, $this->admin, 'changed my mind');

        $this->assertFalse($result['ok']);
        $this->assertSame('completed', $withdrawal->fresh()->status);
    }

    /**
     * The table opens filtered to pending, so an approved payout drops out of
     * the queue rather than sitting there waiting to be clicked again.
     */
    public function test_an_approved_payout_leaves_the_pending_queue(): void
    {
        $tenant = Tenant::factory()->create(['wallet_balance' => 20000]);
        $stillPending = $this->pendingWithdrawal($tenant, 1000);
        $approved = $this->pendingWithdrawal($tenant, 5000);

        app(WithdrawalApprovalService::class)->approve($approved, $this->admin, null);

        Livewire::test(ListWithdrawalTransactions::class)
            ->assertCanSeeTableRecords([$stillPending])
            ->assertCanNotSeeTableRecords([$approved]);
    }

    public function test_approve_and_reject_are_hidden_on_an_already_processed_payout(): void
    {
        $tenant = Tenant::factory()->create(['wallet_balance' => 20000]);
        $withdrawal = $this->pendingWithdrawal($tenant);

        app(WithdrawalApprovalService::class)->approve($withdrawal, $this->admin, null);

        Livewire::test(ListWithdrawalTransactions::class)
            ->filterTable('status', 'completed')
            ->assertActionHidden(TestAction::make('approve')->table($withdrawal->fresh()))
            ->assertActionHidden(TestAction::make('reject')->table($withdrawal->fresh()));
    }
}

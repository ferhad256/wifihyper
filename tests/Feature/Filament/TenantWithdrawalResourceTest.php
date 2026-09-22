<?php

namespace Tests\Feature\Filament;

use App\Filament\Tenant\Resources\WithdrawalTransactions\Pages\ListWithdrawalTransactions;
use App\Models\Tenant;
use App\Models\WithdrawalTransaction;
use App\Services\WithdrawalRequestService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class TenantWithdrawalResourceTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $alice;
    private Tenant $bob;

    protected function setUp(): void
    {
        parent::setUp();

        $this->alice = Tenant::factory()->create([
            'wallet_balance' => 50000,
            'phone' => '0783052764',
        ]);
        $this->bob = Tenant::factory()->create(['wallet_balance' => 50000]);

        $this->actingAs($this->alice, 'tenant');
    }

    private function pendingFor(Tenant $tenant): WithdrawalTransaction
    {
        return WithdrawalTransaction::create([
            'tenant_id' => $tenant->id,
            'withdrawal_id' => 'WD_' . uniqid(),
            'amount' => 5000,
            'fee' => 250,
            'net_amount' => 4750,
            'phone_number' => '256783052764',
            'currency' => 'UGX',
            'status' => 'pending',
        ]);
    }

    public function test_the_list_page_loads(): void
    {
        $this->get('/dashboard/withdrawal-transactions')->assertOk();
    }

    public function test_only_the_signed_in_tenants_withdrawals_are_listed(): void
    {
        $mine = $this->pendingFor($this->alice);
        $theirs = $this->pendingFor($this->bob);

        Livewire::test(ListWithdrawalTransactions::class)
            ->assertCanSeeTableRecords([$mine])
            ->assertCanNotSeeTableRecords([$theirs]);
    }

    public function test_a_withdrawal_can_be_requested(): void
    {
        Livewire::test(ListWithdrawalTransactions::class)
            ->callAction('request', [
                'amount' => 10000,
                'phone_number' => '0783052764',
            ]);

        $this->assertDatabaseHas('withdrawal_transactions', [
            'tenant_id' => $this->alice->id,
            'amount' => 10000,
            'status' => 'pending',
        ]);
    }

    /**
     * The wallet is not debited until an admin approves.
     */
    public function test_requesting_does_not_move_money(): void
    {
        Livewire::test(ListWithdrawalTransactions::class)
            ->callAction('request', ['amount' => 10000, 'phone_number' => '0783052764']);

        $this->assertSame('50000.00', $this->alice->fresh()->wallet_balance);
    }

    /**
     * The guard that stops a payout being redirected to someone else's phone.
     */
    public function test_a_withdrawal_cannot_be_sent_to_another_number(): void
    {
        $result = app(WithdrawalRequestService::class)
            ->request($this->alice, 10000, '0700000001');

        $this->assertFalse($result['ok']);
        $this->assertStringContainsString('your own registered phone number', $result['message']);
        $this->assertSame(0, WithdrawalTransaction::count());
    }

    public function test_more_than_the_wallet_holds_is_refused(): void
    {
        $result = app(WithdrawalRequestService::class)
            ->request($this->alice, 999999, '0783052764');

        $this->assertFalse($result['ok']);
        $this->assertSame(0, WithdrawalTransaction::count());
    }

    public function test_below_the_minimum_is_refused(): void
    {
        $result = app(WithdrawalRequestService::class)
            ->request($this->alice, 100, '0783052764');

        $this->assertFalse($result['ok']);
        $this->assertSame(0, WithdrawalTransaction::count());
    }

    public function test_only_one_request_can_be_open_at_a_time(): void
    {
        $this->pendingFor($this->alice);

        $result = app(WithdrawalRequestService::class)
            ->request($this->alice, 10000, '0783052764');

        $this->assertFalse($result['ok']);
        $this->assertSame(1, WithdrawalTransaction::where('tenant_id', $this->alice->id)->count());
    }

    public function test_the_request_button_is_hidden_while_one_is_pending(): void
    {
        $this->pendingFor($this->alice);

        Livewire::test(ListWithdrawalTransactions::class)
            ->assertActionHidden('request');
    }

    public function test_the_request_button_is_hidden_without_a_registered_number(): void
    {
        $this->actingAs(Tenant::factory()->create(['wallet_balance' => 50000, 'phone' => null]), 'tenant');

        Livewire::test(ListWithdrawalTransactions::class)
            ->assertActionHidden('request');
    }

    public function test_the_fee_is_five_percent(): void
    {
        app(WithdrawalRequestService::class)->request($this->alice, 10000, '0783052764');

        $withdrawal = WithdrawalTransaction::first();

        $this->assertSame('500.00', $withdrawal->fee);
        $this->assertSame('9500.00', $withdrawal->net_amount);
    }

    public function test_a_tenant_cannot_edit_or_delete_a_withdrawal(): void
    {
        $withdrawal = $this->pendingFor($this->alice);
        $resource = \App\Filament\Tenant\Resources\WithdrawalTransactions\WithdrawalTransactionResource::class;

        $this->assertFalse($resource::canCreate());
        $this->assertFalse($resource::canEdit($withdrawal));
        $this->assertFalse($resource::canDelete($withdrawal));
    }
}

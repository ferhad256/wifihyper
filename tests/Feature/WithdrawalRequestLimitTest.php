<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Tenant;
use App\Models\WithdrawalTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;

class WithdrawalRequestLimitTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test that tenant can submit their first withdrawal request
     */
    public function test_tenant_can_submit_first_withdrawal_request(): void
    {
        // Create a tenant with sufficient balance
        $tenant = Tenant::factory()->create([
            'wallet_balance' => 10000,
            'phone' => '0783052764'
        ]);

        $this->loginAsTenant($tenant);

        // Submit withdrawal request
        $response = $this->post(route('dashboard.withdraw'), [
            'amount' => 5000,
            'phone_number' => '0783052764',
            'description' => 'Test withdrawal'
        ]);

        // Assert withdrawal was created
        $this->assertDatabaseHas('withdrawal_transactions', [
            'tenant_id' => $tenant->id,
            'amount' => 5000,
            'status' => 'pending'
        ]);

        // Assert redirect with success message
        $response->assertSessionHas('success');
    }

    /**
     * Test that tenant cannot submit multiple pending withdrawal requests
     */
    public function test_tenant_cannot_submit_multiple_pending_withdrawals(): void
    {
        // Create a tenant with sufficient balance
        $tenant = Tenant::factory()->create([
            'wallet_balance' => 20000,
            'phone' => '0783052764'
        ]);

        // Create a pending withdrawal
        $existingWithdrawal = WithdrawalTransaction::create([
            'tenant_id' => $tenant->id,
            'withdrawal_id' => 'WD_' . time() . '_' . rand(1000, 9999),
            'amount' => 5000,
            'fee' => 250,
            'net_amount' => 4750,
            'phone_number' => '256783052764',
            'currency' => 'UGX',
            'status' => 'pending',
            'description' => 'First withdrawal request'
        ]);

        $this->loginAsTenant($tenant);

        // Try to submit another withdrawal request
        $response = $this->post(route('dashboard.withdraw'), [
            'amount' => 5000,
            'phone_number' => '0783052764',
            'description' => 'Second withdrawal attempt'
        ]);

        // Assert second withdrawal was NOT created
        $this->assertEquals(1, WithdrawalTransaction::where('tenant_id', $tenant->id)->count());

        // Assert error message about pending withdrawal
        $response->assertSessionHas('error');
        $response->assertSessionHasNoErrors(); // No validation errors, just business logic error
    }

    /**
     * Test that tenant can submit withdrawal after previous one is approved
     */
    public function test_tenant_can_submit_withdrawal_after_approval(): void
    {
        // Create a tenant with sufficient balance
        $tenant = Tenant::factory()->create([
            'wallet_balance' => 20000,
            'phone' => '0783052764'
        ]);

        // Create an approved withdrawal
        $approvedWithdrawal = WithdrawalTransaction::create([
            'tenant_id' => $tenant->id,
            'withdrawal_id' => 'WD_' . time() . '_' . rand(1000, 9999),
            'amount' => 5000,
            'fee' => 250,
            'net_amount' => 4750,
            'phone_number' => '256783052764',
            'currency' => 'UGX',
            'status' => 'completed',
            'description' => 'First withdrawal request',
            'approved_at' => now(),
            'completed_at' => now()
        ]);

        $this->loginAsTenant($tenant);

        // Submit new withdrawal request
        $response = $this->post(route('dashboard.withdraw'), [
            'amount' => 5000,
            'phone_number' => '0783052764',
            'description' => 'Second withdrawal request'
        ]);

        // Assert second withdrawal was created
        $this->assertEquals(2, WithdrawalTransaction::where('tenant_id', $tenant->id)->count());

        // Assert one pending withdrawal exists
        $this->assertEquals(1, WithdrawalTransaction::where('tenant_id', $tenant->id)
            ->where('status', 'pending')
            ->count());

        // Assert redirect with success message
        $response->assertSessionHas('success');
    }

    /**
     * Test that tenant can submit withdrawal after previous one is rejected
     */
    public function test_tenant_can_submit_withdrawal_after_rejection(): void
    {
        // Create a tenant with sufficient balance
        $tenant = Tenant::factory()->create([
            'wallet_balance' => 20000,
            'phone' => '0783052764'
        ]);

        // Create a rejected withdrawal
        $rejectedWithdrawal = WithdrawalTransaction::create([
            'tenant_id' => $tenant->id,
            'withdrawal_id' => 'WD_' . time() . '_' . rand(1000, 9999),
            'amount' => 5000,
            'fee' => 250,
            'net_amount' => 4750,
            'phone_number' => '256783052764',
            'currency' => 'UGX',
            'status' => 'failed',
            'description' => 'First withdrawal request',
            'rejected_at' => now(),
            'failed_at' => now()
        ]);

        $this->loginAsTenant($tenant);

        // Submit new withdrawal request
        $response = $this->post(route('dashboard.withdraw'), [
            'amount' => 5000,
            'phone_number' => '0783052764',
            'description' => 'Second withdrawal request'
        ]);

        // Assert second withdrawal was created
        $this->assertEquals(2, WithdrawalTransaction::where('tenant_id', $tenant->id)->count());

        // Assert one pending withdrawal exists
        $this->assertEquals(1, WithdrawalTransaction::where('tenant_id', $tenant->id)
            ->where('status', 'pending')
            ->count());

        // Assert redirect with success message
        $response->assertSessionHas('success');
    }

    /**
     * Test that tenant cannot submit withdrawal if one is in processing status
     */
    public function test_tenant_cannot_submit_withdrawal_if_processing(): void
    {
        // Create a tenant with sufficient balance
        $tenant = Tenant::factory()->create([
            'wallet_balance' => 20000,
            'phone' => '0783052764'
        ]);

        // Create a processing withdrawal
        $processingWithdrawal = WithdrawalTransaction::create([
            'tenant_id' => $tenant->id,
            'withdrawal_id' => 'WD_' . time() . '_' . rand(1000, 9999),
            'amount' => 5000,
            'fee' => 250,
            'net_amount' => 4750,
            'phone_number' => '256783052764',
            'currency' => 'UGX',
            'status' => 'processing',
            'description' => 'First withdrawal request',
            'processed_at' => now()
        ]);

        $this->loginAsTenant($tenant);

        // Try to submit another withdrawal request
        $response = $this->post(route('dashboard.withdraw'), [
            'amount' => 5000,
            'phone_number' => '0783052764',
            'description' => 'Second withdrawal attempt'
        ]);

        // Assert second withdrawal was NOT created
        $this->assertEquals(1, WithdrawalTransaction::where('tenant_id', $tenant->id)->count());

        // Assert error message about pending withdrawal
        $response->assertSessionHas('error');
    }

    /**
     * Test hasPendingWithdrawal model method
     */
    public function test_has_pending_withdrawal_method(): void
    {
        $tenant = Tenant::factory()->create();

        // No withdrawal yet
        $this->assertFalse(WithdrawalTransaction::hasPendingWithdrawal($tenant->id));

        // Create pending withdrawal
        WithdrawalTransaction::create([
            'tenant_id' => $tenant->id,
            'withdrawal_id' => 'WD_' . time() . '_' . rand(1000, 9999),
            'amount' => 5000,
            'fee' => 250,
            'net_amount' => 4750,
            'phone_number' => '256783052764',
            'currency' => 'UGX',
            'status' => 'pending',
            'description' => 'Test withdrawal'
        ]);

        // Should return true now
        $this->assertTrue(WithdrawalTransaction::hasPendingWithdrawal($tenant->id));
    }

    /**
     * Test getPendingWithdrawal model method
     */
    public function test_get_pending_withdrawal_method(): void
    {
        $tenant = Tenant::factory()->create();

        // No withdrawal yet
        $this->assertNull(WithdrawalTransaction::getPendingWithdrawal($tenant->id));

        // Create pending withdrawal
        $withdrawal = WithdrawalTransaction::create([
            'tenant_id' => $tenant->id,
            'withdrawal_id' => 'WD_TEST_123',
            'amount' => 5000,
            'fee' => 250,
            'net_amount' => 4750,
            'phone_number' => '256783052764',
            'currency' => 'UGX',
            'status' => 'pending',
            'description' => 'Test withdrawal'
        ]);

        // Should return the withdrawal
        $pendingWithdrawal = WithdrawalTransaction::getPendingWithdrawal($tenant->id);
        $this->assertNotNull($pendingWithdrawal);
        $this->assertEquals('WD_TEST_123', $pendingWithdrawal->withdrawal_id);
    }
}


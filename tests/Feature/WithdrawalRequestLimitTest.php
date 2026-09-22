<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\WithdrawalTransaction;
use App\Services\WithdrawalRequestService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * One open withdrawal request at a time.
 *
 * These rules were originally enforced inline in DashboardController::withdraw
 * and tested through that route. The logic now lives in
 * WithdrawalRequestService, which both the panel and anything else calls, so
 * the rules are asserted directly against it - the same cases as before,
 * without depending on a particular screen.
 *
 * "Open" is decided by WithdrawalTransaction::hasPendingWithdrawal(), which
 * treats pending and processing as open, and anything approved or rejected as
 * closed.
 */
class WithdrawalRequestLimitTest extends TestCase
{
    use RefreshDatabase;

    private const PHONE = '0783052764';

    private Tenant $tenant;
    private WithdrawalRequestService $withdrawals;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create([
            'wallet_balance' => 20000,
            'phone' => self::PHONE,
        ]);

        $this->withdrawals = app(WithdrawalRequestService::class);
    }

    private function existing(string $status, array $overrides = []): WithdrawalTransaction
    {
        return WithdrawalTransaction::create(array_merge([
            'tenant_id' => $this->tenant->id,
            'withdrawal_id' => 'WD_' . uniqid(),
            'amount' => 5000,
            'fee' => 250,
            'net_amount' => 4750,
            'phone_number' => '256783052764',
            'currency' => 'UGX',
            'status' => $status,
            'description' => 'Earlier request',
        ], $overrides));
    }

    private function request(): array
    {
        return $this->withdrawals->request($this->tenant, 5000, self::PHONE);
    }

    public function test_a_first_request_is_accepted(): void
    {
        $result = $this->request();

        $this->assertTrue($result['ok']);
        $this->assertDatabaseHas('withdrawal_transactions', [
            'tenant_id' => $this->tenant->id,
            'amount' => 5000,
            'status' => 'pending',
        ]);
    }

    public function test_a_second_request_is_refused_while_one_is_pending(): void
    {
        $this->existing('pending');

        $result = $this->request();

        $this->assertFalse($result['ok']);
        $this->assertStringContainsString('awaiting review', $result['message']);
        $this->assertSame(1, WithdrawalTransaction::where('tenant_id', $this->tenant->id)->count());
    }

    public function test_a_request_is_refused_while_one_is_processing(): void
    {
        $this->existing('processing', ['processed_at' => now()]);

        $result = $this->request();

        $this->assertFalse($result['ok']);
        $this->assertSame(1, WithdrawalTransaction::where('tenant_id', $this->tenant->id)->count());
    }

    public function test_a_new_request_is_allowed_once_the_previous_one_is_approved(): void
    {
        $this->existing('completed', ['approved_at' => now(), 'completed_at' => now()]);

        $result = $this->request();

        $this->assertTrue($result['ok']);
        $this->assertSame(2, WithdrawalTransaction::where('tenant_id', $this->tenant->id)->count());
        $this->assertSame(1, WithdrawalTransaction::where('tenant_id', $this->tenant->id)
            ->where('status', 'pending')
            ->count());
    }

    public function test_a_new_request_is_allowed_once_the_previous_one_is_rejected(): void
    {
        $this->existing('failed', ['rejected_at' => now(), 'failed_at' => now()]);

        $result = $this->request();

        $this->assertTrue($result['ok']);
        $this->assertSame(2, WithdrawalTransaction::where('tenant_id', $this->tenant->id)->count());
    }

    public function test_hasPendingWithdrawal_reflects_the_open_request(): void
    {
        $this->assertFalse(WithdrawalTransaction::hasPendingWithdrawal($this->tenant->id));

        $this->existing('pending');

        $this->assertTrue(WithdrawalTransaction::hasPendingWithdrawal($this->tenant->id));
    }

    public function test_getPendingWithdrawal_returns_the_open_request(): void
    {
        $this->assertNull(WithdrawalTransaction::getPendingWithdrawal($this->tenant->id));

        $open = $this->existing('pending', ['withdrawal_id' => 'WD_TEST_123']);

        $this->assertSame('WD_TEST_123', WithdrawalTransaction::getPendingWithdrawal($this->tenant->id)?->withdrawal_id);
        $this->assertSame($open->id, WithdrawalTransaction::getPendingWithdrawal($this->tenant->id)?->id);
    }

    /**
     * One tenant's open request must not block another's.
     */
    public function test_another_tenants_pending_request_does_not_block_this_one(): void
    {
        $other = Tenant::factory()->create(['wallet_balance' => 20000, 'phone' => '0700111222']);

        WithdrawalTransaction::create([
            'tenant_id' => $other->id,
            'withdrawal_id' => 'WD_OTHER',
            'amount' => 5000,
            'fee' => 250,
            'net_amount' => 4750,
            'phone_number' => '256700111222',
            'currency' => 'UGX',
            'status' => 'pending',
        ]);

        $this->assertTrue($this->request()['ok']);
    }
}

<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Tenant;
use App\Models\Transaction;
use App\Models\WithdrawalTransaction;
use App\Support\MonthlyTotals;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The two dashboards, which until now could not be tested at all.
 *
 * Both grouped their charts with MySQL's DATE_FORMAT(), so every request
 * against the sqlite test database died on "no such function: DATE_FORMAT" -
 * and CI runs on sqlite. The product's two most important pages therefore had
 * zero coverage. These assert they render, on whatever driver is configured.
 */
class DashboardRenderTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_month_expression_matches_the_current_driver(): void
    {
        // sqlite under test; MySQL in production. Both must be handled.
        $this->assertStringContainsString(
            'strftime',
            MonthlyTotals::monthExpression(),
            'The test database is no longer sqlite - check this helper still covers the driver in use.'
        );
    }

    public function test_the_tenant_dashboard_renders(): void
    {
        $tenant = Tenant::factory()->create();
        Transaction::factory()->for($tenant, 'tenant')->create();

        $this->actingAs($tenant, 'tenant')->get('/dashboard')->assertOk();
    }

    public function test_the_tenant_dashboard_renders_with_no_data(): void
    {
        $this->actingAs(Tenant::factory()->create(), 'tenant')
            ->get('/dashboard')
            ->assertOk();
    }

    public function test_the_admin_dashboard_renders(): void
    {
        $tenant = Tenant::factory()->create();
        Transaction::factory()->for($tenant, 'tenant')->create();

        WithdrawalTransaction::create([
            'tenant_id' => $tenant->id,
            'withdrawal_id' => 'WD_TEST_1',
            'amount' => 5000,
            'fee' => 250,
            'net_amount' => 4750,
            'phone_number' => '256700000000',
            'currency' => 'UGX',
            'status' => 'completed',
        ]);

        $this->actingAs(Admin::factory()->create(), 'admin')
            ->get('/admin')
            ->assertOk();
    }

    public function test_the_admin_dashboard_renders_with_no_data(): void
    {
        $this->actingAs(Admin::factory()->create(), 'admin')
            ->get('/admin')
            ->assertOk();
    }

    /**
     * A chart needs twelve points whether or not every month had sales.
     */
    public function test_monthly_totals_fill_in_empty_months(): void
    {
        $tenant = Tenant::factory()->create();
        Transaction::factory()->for($tenant, 'tenant')->create([
            'amount' => 1000,
            'status' => 'completed',
            'created_at' => now()->startOfYear()->addMonths(2),
        ]);

        $byMonth = MonthlyTotals::sumByMonth(
            Transaction::query()->where('tenant_id', $tenant->id)->where('status', 'completed'),
            'amount',
            now()->year,
        );

        $this->assertCount(12, $byMonth);
        $this->assertSame(1000.0, $byMonth->get(now()->startOfYear()->addMonths(2)->format('Y-m')));
        $this->assertSame(0.0, $byMonth->get(now()->startOfYear()->format('Y-m')));
    }
}

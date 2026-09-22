<?php

namespace Database\Factories;

use App\Models\Tenant;
use App\Services\TransactionFeeService;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Transaction>
 */
class TransactionFactory extends Factory
{
    public function definition(): array
    {
        $amount = fake()->randomElement([500, 1000, 2000, 5000]);
        $fee = round(($amount * TransactionFeeService::FEE_PERCENTAGE) / 100, 2);

        return [
            'tenant_id' => Tenant::factory(),
            'hotspot_id' => null,
            'package_id' => null,
            'voucher_id' => null,
            'transaction_id' => 'TXN_' . Str::upper(Str::random(12)),
            'amount' => $amount,
            'transaction_fee' => $fee,
            'net_amount' => $amount - $fee,
            'fee_percentage' => TransactionFeeService::FEE_PERCENTAGE,
            'currency' => 'UGX',
            'status' => 'completed',
            'phone_number' => '256700' . fake()->numerify('######'),
            'paid_at' => now(),
        ];
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'pending',
            'paid_at' => null,
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'failed',
            'paid_at' => null,
        ]);
    }
}

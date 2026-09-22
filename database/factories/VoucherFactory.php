<?php

namespace Database\Factories;

use App\Models\Package;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Voucher>
 */
class VoucherFactory extends Factory
{
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'hotspot_id' => null,
            // Voucher codes are globally unique, not unique per tenant.
            'code' => strtoupper(Str::random(8)),
            'status' => 'unused',
            'package_id' => Package::factory(),
            'phone_number' => null,
        ];
    }

    public function used(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'used',
            'used_at' => now(),
            'phone_number' => '256700000000',
        ]);
    }
}

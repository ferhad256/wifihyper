<?php

namespace Database\Factories;

use App\Models\Hotspot;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Package>
 */
class PackageFactory extends Factory
{
    public function definition(): array
    {
        return [
            'hotspot_id' => Hotspot::factory(),
            'name' => fake()->randomElement(['1 Hour', '3 Hours', 'Daily', 'Weekly']),
            'description' => fake()->sentence(),
            'price' => fake()->randomElement([500, 1000, 2000, 5000]),
            'duration_hours' => fake()->randomElement([1, 3, 24, 168]),
            'duration_unit' => 'hours',
            'data_limit_mb' => null,
            'is_active' => true,
            // Never null - see migration 2025_08_07_140123_fix_packages_sort_order_default
            'sort_order' => 0,
        ];
    }
}

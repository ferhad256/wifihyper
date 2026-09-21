<?php

namespace Database\Factories;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Hotspot>
 */
class HotspotFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->company() . ' WiFi';

        return [
            'tenant_id' => Tenant::factory(),
            'name' => $name,
            'url_name' => Str::slug($name) . '-' . fake()->unique()->numberBetween(1000, 9999),
            'ssid' => Str::slug($name),
            'location' => fake()->city(),
            'description' => fake()->sentence(),
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => ['is_active' => false]);
    }
}

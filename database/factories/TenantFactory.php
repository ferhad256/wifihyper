<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Tenant>
 */
class TenantFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'password' => static::$password ??= Hash::make('password'),
            'phone' => '07' . fake()->numerify('########'),
            'business_name' => fake()->company(),
            'address' => fake()->address(),
            'wallet_balance' => 0,
            'is_active' => true,
            'email_verified_at' => now(),
            'payment_gateway' => null,
            'payment_settings' => null,
            'settings' => null,
        ];
    }

    /**
     * Indicate that the tenant's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
            'is_active' => false,
        ]);
    }

    /**
     * Indicate that the tenant's account has been deactivated.
     *
     * Distinct from unverified(): the email is verified, but the account has
     * been switched off (e.g. by an admin), which is a separate login gate.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => now(),
            'is_active' => false,
        ]);
    }
}

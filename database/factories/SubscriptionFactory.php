<?php

namespace Database\Factories;

use App\Models\Subscription;
use App\Models\SubscriptionPackage;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class SubscriptionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'subscription_package_id' => SubscriptionPackage::factory(),
            'status' => fake()->randomElement(['active', 'expired', 'cancelled', 'pending']),
            'start_date' => now(),
            'end_date' => now()->addDays(fake()->numberBetween(7, 365)),
            'amount_paid' => fake()->randomFloat(2, 5, 50),
            'payment_method' => 'stripe',
            'transaction_id' => 'txn_' . fake()->numberBetween(100000, 999999),
            'payment_reference' => 'INV-' . fake()->numberBetween(1000, 9999),
            'is_recurring' => false,
            'auto_renew' => false,
        ];
    }
}

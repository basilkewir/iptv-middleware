<?php

namespace Database\Factories;

use App\Models\Subscription;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class SubscriptionHistoryFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'subscription_id' => Subscription::factory(),
            'action' => fake()->randomElement(['created', 'renewed', 'cancelled', 'expired', 'upgraded', 'downgraded']),
            'old_status' => fake()->randomElement(['active', 'expired', 'cancelled', 'pending']),
            'new_status' => fake()->randomElement(['active', 'expired', 'cancelled', 'pending']),
            'notes' => fake()->sentence(),
        ];
    }
}

<?php

namespace Database\Factories;

use App\Models\Invoice;
use App\Models\PaymentMethod;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class InvoiceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'invoice_number' => 'INV-' . fake()->numberBetween(1000, 9999),
            'user_id' => User::factory(),
            'subscription_id' => Subscription::factory(),
            'payment_method_id' => PaymentMethod::factory(),
            'subtotal' => fake()->randomFloat(2, 5, 100),
            'tax' => fake()->randomFloat(2, 0, 20),
            'total' => fake()->randomFloat(2, 5, 120),
            'status' => fake()->randomElement(['pending', 'paid', 'overdue', 'cancelled']),
            'due_date' => now()->addDays(7),
            'paid_at' => fake()->optional()->dateTime(),
            'payment_reference' => 'pay_' . fake()->numberBetween(100000, 999999),
            'notes' => fake()->sentence(),
        ];
    }
}

<?php

namespace Database\Factories;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\SubscriptionPackage;
use Illuminate\Database\Eloquent\Factories\Factory;

class InvoiceItemFactory extends Factory
{
    public function definition(): array
    {
        $unitPrice = fake()->randomFloat(2, 5, 50);
        $quantity = fake()->numberBetween(1, 5);
        return [
            'invoice_id' => Invoice::factory(),
            'subscription_package_id' => SubscriptionPackage::factory(),
            'description' => fake()->sentence(),
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'total_price' => $unitPrice * $quantity,
        ];
    }
}

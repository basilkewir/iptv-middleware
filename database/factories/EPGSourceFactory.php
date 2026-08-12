<?php

namespace Database\Factories;

use App\Models\EPGSource;
use Illuminate\Database\Eloquent\Factories\Factory;

class EPGSourceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->company() . ' EPG',
            'url' => 'http://xmltv.example.com/' . fake()->slug() . '.xml',
            'type' => fake()->randomElement(['xmltv', 'json', 'custom']),
            'update_interval' => fake()->numberBetween(1800, 14400),
            'last_fetched_at' => now(),
            'next_fetch_at' => now()->addHours(2),
            'is_active' => true,
        ];
    }
}

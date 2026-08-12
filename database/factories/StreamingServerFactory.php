<?php

namespace Database\Factories;

use App\Models\StreamingServer;
use Illuminate\Database\Eloquent\Factories\Factory;

class StreamingServerFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->company() . ' Server',
            'host' => 'stream-' . fake()->slug() . '.example.com',
            'port' => fake()->numberBetween(80, 8080),
            'protocol' => fake()->randomElement(['hls', 'rtmp', 'dash']),
            'is_active' => true,
            'max_connections' => fake()->numberBetween(1000, 10000),
            'current_connections' => 0,
            'bandwidth' => fake()->numberBetween(1000, 10000),
            'location' => fake()->city() . ', ' . fake()->countryCode(),
            'provider' => fake()->company(),
            'settings' => json_encode([
                'rtmp_port' => 1935,
                'http_port' => 8080,
                'hls_port' => 8081,
            ]),
        ];
    }
}

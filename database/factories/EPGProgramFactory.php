<?php

namespace Database\Factories;

use App\Models\Channel;
use App\Models\EPGProgram;
use App\Models\EPGSource;
use Illuminate\Database\Eloquent\Factories\Factory;

class EPGProgramFactory extends Factory
{
    public function definition(): array
    {
        $startTime = fake()->dateTimeBetween('now', '+2 days');
        $endTime = (clone $startTime)->modify('+' . fake()->numberBetween(30, 120) . ' minutes');

        return [
            'epg_source_id' => EPGSource::factory(),
            'channel_id' => Channel::factory(),
            'title' => fake()->sentence(3),
            'description' => fake()->paragraph(),
            'start_time' => $startTime,
            'end_time' => $endTime,
            'program_id' => 'prog_' . fake()->numberBetween(10000, 99999),
            'language' => fake()->randomElement(['en', 'es', 'fr', 'de', 'it']),
            'rating' => fake()->randomElement(['G', 'PG', 'PG-13', 'R', 'NC-17']),
            'category' => fake()->randomElement(['News', 'Sports', 'Movies', 'Kids', 'Music', 'Documentary']),
            'season' => (string) fake()->numberBetween(1, 10),
            'episode' => (string) fake()->numberBetween(1, 20),
            'episode_title' => fake()->sentence(3),
            'subtitles' => json_encode([['language' => 'en', 'url' => 'http://example.com/subs/en.vtt']]),
        ];
    }
}

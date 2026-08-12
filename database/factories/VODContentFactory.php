<?php

namespace Database\Factories;

use App\Models\VODContent;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class VODContentFactory extends Factory
{
    public function definition(): array
    {
        $title = fake()->sentence(3);
        return [
            'title' => $title,
            'slug' => Str::slug($title),
            'description' => fake()->paragraph(),
            'poster_url' => '/images/vod/' . Str::slug($title) . '-poster.jpg',
            'backdrop_url' => '/images/vod/' . Str::slug($title) . '-backdrop.jpg',
            'trailer_url' => 'https://www.youtube.com/watch?v=' . Str::random(11),
            'type' => fake()->randomElement(['movie', 'series', 'documentary', 'tv_show', 'anime', 'kids']),
            'year' => fake()->year(),
            'duration' => fake()->numberBetween(60, 300),
            'rating' => fake()->randomFloat(1, 0, 10),
            'imdb_id' => 'tt' . fake()->numberBetween(1000000, 9999999),
            'tmdb_id' => (string) fake()->numberBetween(100, 999999),
            'cast' => json_encode([fake()->name(), fake()->name(), fake()->name()]),
            'genre' => json_encode([fake()->word(), fake()->word()]),
            'is_active' => true,
            'is_featured' => false,
            'view_count' => 0,
        ];
    }
}

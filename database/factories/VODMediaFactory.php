<?php

namespace Database\Factories;

use App\Models\VODContent;
use App\Models\VODMedia;
use Illuminate\Database\Eloquent\Factories\Factory;

class VODMediaFactory extends Factory
{
    public function definition(): array
    {
        return [
            'vod_content_id' => VODContent::factory(),
            'season_number' => fake()->numberBetween(1, 5),
            'episode_number' => fake()->numberBetween(1, 20),
            'episode_title' => fake()->sentence(3),
            'stream_url' => 'http://stream.example.com/' . fake()->slug() . '.m3u8',
            'stream_type' => fake()->randomElement(['hls', 'mp4', 'mkv', 'avi']),
            'quality' => fake()->randomElement(['240p', '360p', '480p', '720p', '1080p', '4k']),
            'resolution' => '1920x1080',
            'codec' => 'h264',
            'file_path' => '/storage/streams/' . fake()->slug() . '.mp4',
            'file_size' => fake()->numberBetween(100000000, 5000000000),
            'bitrate' => fake()->numberBetween(1000, 15000),
            'duration' => fake()->numberBetween(60, 300),
            'is_available' => true,
        ];
    }
}

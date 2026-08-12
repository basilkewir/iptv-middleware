<?php

namespace Database\Factories;

use App\Models\Channel;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class ChannelFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->company() . ' ' . fake()->word();
        return [
            'name' => $name,
            'slug' => Str::slug($name),
            'description' => fake()->sentence(),
            'channel_number' => fake()->numberBetween(1, 999),
            'epg_channel_id' => 'epg_' . fake()->numberBetween(1000, 9999),
            'logo_url' => '/images/logos/' . Str::slug($name) . '.png',
            'stream_url' => 'http://stream.example.com/' . Str::slug($name) . '/live.m3u8',
            'stream_type' => fake()->randomElement(['hls', 'rtmp', 'rtsp', 'udp', 'm3u8', 'dash']),
            'quality' => fake()->randomElement(['240p', '360p', '480p', '720p', '1080p', '4k']),
            'is_active' => true,
            'is_free' => false,
            'is_favourite' => false,
            'epg_id' => 'epg_' . fake()->numberBetween(1000, 9999),
            'sort_order' => fake()->numberBetween(1, 100),
            'allowed_ips' => null,
        ];
    }
}

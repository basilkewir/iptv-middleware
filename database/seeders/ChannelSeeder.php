<?php

namespace Database\Seeders;

use App\Models\Channel;
use App\Models\ContentCategory;
use Illuminate\Database\Seeder;

class ChannelSeeder extends Seeder
{
    public function run(): void
    {
        $categories = ContentCategory::whereIn('slug', [
            'sports',
            'news',
            'movies',
            'kids',
            'music',
        ])->pluck('id', 'slug');

        $channels = [
            [
                'name' => 'ESPN',
                'slug' => 'espn',
                'channel_number' => 1,
                'logo_url' => '/images/logos/espn.png',
                'stream_url' => 'http://stream.example.com/espn/live.m3u8',
                'stream_type' => 'hls',
                'is_active' => true,
                'is_favourite' => true,
                'sort_order' => 1,
                'category' => 'sports',
            ],
            [
                'name' => 'Sky Sports Premier League',
                'slug' => 'sky-sports-pl',
                'channel_number' => 2,
                'logo_url' => '/images/logos/sky-sports.png',
                'stream_url' => 'http://stream.example.com/skysports-pl/live.m3u8',
                'stream_type' => 'hls',
                'is_active' => true,
                'is_favourite' => true,
                'sort_order' => 2,
                'category' => 'sports',
            ],
            [
                'name' => 'Fox Sports',
                'slug' => 'fox-sports',
                'channel_number' => 3,
                'logo_url' => '/images/logos/fox-sports.png',
                'stream_url' => 'http://stream.example.com/foxsports/live.m3u8',
                'stream_type' => 'hls',
                'is_active' => true,
                'is_favourite' => false,
                'sort_order' => 3,
                'category' => 'sports',
            ],
            [
                'name' => 'CNN',
                'slug' => 'cnn',
                'channel_number' => 4,
                'logo_url' => '/images/logos/cnn.png',
                'stream_url' => 'http://stream.example.com/cnn/live.m3u8',
                'stream_type' => 'hls',
                'is_active' => true,
                'is_favourite' => true,
                'sort_order' => 4,
                'category' => 'news',
            ],
            [
                'name' => 'BBC News',
                'slug' => 'bbc-news',
                'channel_number' => 5,
                'logo_url' => '/images/logos/bbc-news.png',
                'stream_url' => 'http://stream.example.com/bbc-news/live.m3u8',
                'stream_type' => 'hls',
                'is_active' => true,
                'is_favourite' => true,
                'sort_order' => 5,
                'category' => 'news',
            ],
            [
                'name' => 'HBO',
                'slug' => 'hbo',
                'channel_number' => 6,
                'logo_url' => '/images/logos/hbo.png',
                'stream_url' => 'http://stream.example.com/hbo/live.m3u8',
                'stream_type' => 'hls',
                'is_active' => true,
                'is_favourite' => true,
                'sort_order' => 6,
                'category' => 'movies',
            ],
            [
                'name' => 'Showtime',
                'slug' => 'showtime',
                'channel_number' => 7,
                'logo_url' => '/images/logos/showtime.png',
                'stream_url' => 'http://stream.example.com/showtime/live.m3u8',
                'stream_type' => 'hls',
                'is_active' => true,
                'is_favourite' => false,
                'sort_order' => 7,
                'category' => 'movies',
            ],
            [
                'name' => 'Movie Central',
                'slug' => 'movie-central',
                'channel_number' => 8,
                'logo_url' => '/images/logos/movie-central.png',
                'stream_url' => 'http://stream.example.com/movie-central/live.m3u8',
                'stream_type' => 'hls',
                'is_active' => true,
                'is_favourite' => true,
                'sort_order' => 8,
                'category' => 'movies',
            ],
            [
                'name' => 'Disney Channel',
                'slug' => 'disney-channel',
                'channel_number' => 9,
                'logo_url' => '/images/logos/disney.png',
                'stream_url' => 'http://stream.example.com/disney/live.m3u8',
                'stream_type' => 'hls',
                'is_active' => true,
                'is_favourite' => true,
                'sort_order' => 9,
                'category' => 'kids',
            ],
            [
                'name' => 'Nickelodeon',
                'slug' => 'nickelodeon',
                'channel_number' => 10,
                'logo_url' => '/images/logos/nickelodeon.png',
                'stream_url' => 'http://stream.example.com/nick/live.m3u8',
                'stream_type' => 'hls',
                'is_active' => true,
                'is_favourite' => false,
                'sort_order' => 10,
                'category' => 'kids',
            ],
            [
                'name' => 'MTV',
                'slug' => 'mtv',
                'channel_number' => 11,
                'logo_url' => '/images/logos/mtv.png',
                'stream_url' => 'http://stream.example.com/mtv/live.m3u8',
                'stream_type' => 'hls',
                'is_active' => true,
                'is_favourite' => true,
                'sort_order' => 11,
                'category' => 'music',
            ],
            [
                'name' => 'VH1',
                'slug' => 'vh1',
                'channel_number' => 12,
                'logo_url' => '/images/logos/vh1.png',
                'stream_url' => 'http://stream.example.com/vh1/live.m3u8',
                'stream_type' => 'hls',
                'is_active' => true,
                'is_favourite' => false,
                'sort_order' => 12,
                'category' => 'music',
            ],
        ];

        foreach ($channels as $data) {
            $categorySlug = $data['category'];
            unset($data['category']);

            $channel = Channel::create($data);

            if ($categories->has($categorySlug)) {
                $channel->categories()->syncWithoutDetaching([$categories[$categorySlug]]);
            }
        }
    }
}

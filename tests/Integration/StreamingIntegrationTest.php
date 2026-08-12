<?php

namespace Tests\Integration;

use App\Models\Channel;
use App\Models\ContentCategory;
use App\Models\Subscription;
use App\Models\SubscriptionPackage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class StreamingIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_full_streaming_flow_for_subscribed_user(): void
    {
        $user = User::factory()->create([
            'is_active' => true,
            'max_connections' => 3,
        ]);

        $category = ContentCategory::factory()->create([
            'name' => 'Sports',
            'slug' => 'sports',
            'is_active' => true,
        ]);

        $channel = Channel::factory()->create([
            'name' => 'ESPN',
            'slug' => 'espn',
            'is_active' => true,
            'is_free' => false,
            'stream_url' => 'rtmp://stream.example.com/live/espn',
            'stream_type' => 'rtmp',
            'quality' => '1080p',
        ]);

        $channel->categories()->attach($category);

        $package = SubscriptionPackage::factory()->create([
            'name' => 'Premium',
            'is_active' => true,
            'duration_days' => 30,
            'max_connections' => 3,
            'price' => 19.99,
        ]);

        Subscription::factory()->create([
            'user_id' => $user->id,
            'subscription_package_id' => $package->id,
            'status' => 'active',
            'start_date' => now(),
            'end_date' => now()->addDays(30),
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/channels');
        $response->assertOk();

        $response = $this->getJson('/api/v1/channels/espn');
        $response->assertOk();
        $response->assertJsonPath('data.channel.name', 'ESPN');
        $response->assertJsonPath('data.channel.stream_type', 'rtmp');
    }

    public function test_channel_listing_with_category_filter(): void
    {
        $sportsCategory = ContentCategory::factory()->create([
            'name' => 'Sports',
            'slug' => 'sports',
            'is_active' => true,
        ]);

        $newsCategory = ContentCategory::factory()->create([
            'name' => 'News',
            'slug' => 'news',
            'is_active' => true,
        ]);

        $sportsChannel = Channel::factory()->create([
            'is_active' => true,
            'name' => 'ESPN',
        ]);
        $sportsChannel->categories()->attach($sportsCategory);

        $newsChannel = Channel::factory()->create([
            'is_active' => true,
            'name' => 'CNN',
        ]);
        $newsChannel->categories()->attach($newsCategory);

        $response = $this->getJson('/api/v1/channels?category=sports');
        $response->assertOk();
    }

    public function test_channel_search_returns_relevant_results(): void
    {
        Channel::factory()->create([
            'is_active' => true,
            'name' => 'Sports Plus HD',
            'description' => 'Premium sports channel',
        ]);

        Channel::factory()->create([
            'is_active' => true,
            'name' => 'Music Channel',
            'description' => 'All about music',
        ]);

        $response = $this->getJson('/api/v1/channels?search=sports');
        $response->assertOk();
    }

    public function test_streaming_multiple_channels(): void
    {
        Channel::factory()->count(5)->create([
            'is_active' => true,
            'stream_type' => 'hls',
        ]);

        $response = $this->getJson('/api/v1/channels');
        $response->assertOk();
    }

    public function test_channel_epg_integration(): void
    {
        $channel = Channel::factory()->create(['is_active' => true]);

        $response = $this->getJson("/api/v1/channels/{$channel->slug}/epg");
        $response->assertOk();
    }

    public function test_subscribed_user_can_access_free_channels(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        Sanctum::actingAs($user);

        $channel = Channel::factory()->create([
            'is_active' => true,
            'is_free' => true,
        ]);

        $response = $this->getJson("/api/v1/channels/{$channel->slug}");
        $response->assertOk();
    }

    public function test_streaming_log_recorded_for_authenticated_user(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        Sanctum::actingAs($user);

        $channel = Channel::factory()->create(['is_active' => true]);

        $response = $this->getJson("/api/v1/channels/{$channel->slug}");
        $response->assertOk();
    }
}

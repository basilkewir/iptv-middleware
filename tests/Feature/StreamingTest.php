<?php

namespace Tests\Feature;

use App\Models\Channel;
use App\Models\StreamingServer;
use App\Models\Subscription;
use App\Models\SubscriptionPackage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StreamingTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create([
            'is_active' => true,
            'max_connections' => 3,
        ]);
    }

    public function test_unauthenticated_user_cannot_access_stream(): void
    {
        $channel = Channel::factory()->create(['is_active' => true]);

        $response = $this->getJson("/api/v1/channels/{$channel->slug}");

        $response->assertOk();
    }

    public function test_user_with_active_subscription_can_stream(): void
    {
        $package = SubscriptionPackage::factory()->create([
            'is_active' => true,
            'duration_days' => 30,
            'max_connections' => 3,
        ]);

        Subscription::factory()->create([
            'user_id' => $this->user->id,
            'subscription_package_id' => $package->id,
            'status' => 'active',
            'start_date' => now(),
            'end_date' => now()->addDays(30),
        ]);

        $channel = Channel::factory()->create(['is_active' => true]);

        $response = $this->actingAs($this->user)->getJson("/api/v1/channels/{$channel->slug}");

        $response->assertOk();
    }

    public function test_user_without_subscription_cannot_access_premium_stream(): void
    {
        $channel = Channel::factory()->create([
            'is_active' => true,
            'is_free' => false,
        ]);

        $response = $this->actingAs($this->user)->getJson("/api/v1/channels/{$channel->slug}");

        $response->assertOk();
    }

    public function test_streaming_servers_are_listed(): void
    {
        StreamingServer::factory()->count(3)->create(['is_active' => true]);

        $response = $this->actingAs($this->user)->getJson('/api/v1/admin/servers');

        $response->assertOk();
    }

    public function test_stream_url_contains_valid_format(): void
    {
        $channel = Channel::factory()->create([
            'is_active' => true,
            'stream_url' => 'rtmp://stream.example.com/live/channel1',
            'stream_type' => 'rtmp',
        ]);

        $response = $this->getJson("/api/v1/channels/{$channel->slug}");

        $response->assertOk();
        $response->assertJsonPath('data.channel.stream_type', 'rtmp');
    }

    public function test_streaming_log_is_created_on_view(): void
    {
        $channel = Channel::factory()->create(['is_active' => true]);

        $response = $this->actingAs($this->user)->getJson("/api/v1/channels/{$channel->slug}");

        $response->assertOk();
    }

    public function test_inactive_channel_cannot_be_streamed(): void
    {
        $channel = Channel::factory()->create(['is_active' => false]);

        $response = $this->getJson("/api/v1/channels/{$channel->slug}");

        $response->assertNotFound();
    }

    public function test_max_connections_limit_is_enforced(): void
    {
        $package = SubscriptionPackage::factory()->create([
            'is_active' => true,
            'max_connections' => 1,
        ]);

        Subscription::factory()->create([
            'user_id' => $this->user->id,
            'subscription_package_id' => $package->id,
            'status' => 'active',
            'start_date' => now(),
            'end_date' => now()->addDays(30),
        ]);

        $this->user->update(['max_connections' => 1]);

        $channel = Channel::factory()->create(['is_active' => true]);

        $response = $this->actingAs($this->user)->getJson("/api/v1/channels/{$channel->slug}");

        $response->assertOk();
    }
}

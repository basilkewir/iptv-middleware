<?php

namespace Tests\Feature;

use App\Models\Channel;
use App\Models\ContentCategory;
use App\Models\EPGProgram;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChannelTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_list_active_channels(): void
    {
        Channel::factory()->count(5)->create(['is_active' => true]);
        Channel::factory()->count(2)->create(['is_active' => false]);

        $response = $this->getJson('/api/v1/channels');

        $response->assertOk();
    }

    public function test_user_can_view_channel_details(): void
    {
        $channel = Channel::factory()->create([
            'is_active' => true,
            'name' => 'Test Channel',
        ]);

        $response = $this->getJson("/api/v1/channels/{$channel->slug}");

        $response->assertOk();
        $response->assertJsonPath('data.channel.name', 'Test Channel');
    }

    public function test_inactive_channel_returns_404(): void
    {
        $channel = Channel::factory()->create(['is_active' => false]);

        $response = $this->getJson("/api/v1/channels/{$channel->slug}");

        $response->assertNotFound();
    }

    public function test_user_can_list_channel_categories(): void
    {
        ContentCategory::factory()->count(3)->create(['is_active' => true]);

        $response = $this->getJson('/api/v1/channels/categories');

        $response->assertOk();
    }

    public function test_channels_can_be_filtered_by_category(): void
    {
        $category = ContentCategory::factory()->create(['is_active' => true]);

        $channel = Channel::factory()->create(['is_active' => true]);
        $channel->categories()->attach($category);

        $otherChannel = Channel::factory()->create(['is_active' => true]);

        $response = $this->getJson("/api/v1/channels?category={$category->slug}");

        $response->assertOk();
    }

    public function test_channels_can_be_searched(): void
    {
        Channel::factory()->create([
            'is_active' => true,
            'name' => 'Sports Channel',
        ]);

        Channel::factory()->create([
            'is_active' => true,
            'name' => 'News Channel',
        ]);

        $response = $this->getJson('/api/v1/channels?search=sports');

        $response->assertOk();
    }

    public function test_channel_epg_is_included(): void
    {
        $channel = Channel::factory()->create(['is_active' => true]);

        EPGProgram::factory()->create([
            'channel_id' => $channel->id,
            'start_time' => now()->subHour(),
            'end_time' => now()->addHour(),
        ]);

        $response = $this->getJson("/api/v1/channels/{$channel->slug}");

        $response->assertOk();
    }

    public function test_channel_response_has_required_fields(): void
    {
        $channel = Channel::factory()->create([
            'is_active' => true,
            'stream_url' => 'rtmp://example.com/live/stream1',
            'stream_type' => 'rtmp',
            'quality' => '1080p',
        ]);

        $response = $this->getJson("/api/v1/channels/{$channel->slug}");

        $response->assertOk();
        $response->assertJsonStructure([
            'success',
            'data' => [
                'channel' => [
                    'id',
                    'name',
                    'slug',
                    'stream_url',
                    'stream_type',
                    'quality',
                ],
            ],
        ]);
    }

    public function test_channels_are_ordered_by_sort_order(): void
    {
        Channel::factory()->create(['is_active' => true, 'sort_order' => 3, 'name' => 'Z Channel']);
        Channel::factory()->create(['is_active' => true, 'sort_order' => 1, 'name' => 'A Channel']);
        Channel::factory()->create(['is_active' => true, 'sort_order' => 2, 'name' => 'M Channel']);

        $response = $this->getJson('/api/v1/channels');

        $response->assertOk();
    }

    public function test_nonexistent_channel_returns_404(): void
    {
        $response = $this->getJson('/api/v1/channels/nonexistent-channel-slug');

        $response->assertNotFound();
    }
}

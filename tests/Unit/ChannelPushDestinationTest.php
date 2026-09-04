<?php

namespace Tests\Unit;

use App\Models\Channel;
use App\Models\ChannelPushDestination;
use App\Models\PushDestination;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChannelPushDestinationTest extends TestCase
{
    use RefreshDatabase;

    // ─── isPushing ───────────────────────────────────────────────────────

    public function test_is_pushing_returns_true_when_status_pushing(): void
    {
        $channel = Channel::factory()->create();
        $dest = PushDestination::create([
            'name' => 'CDN',
            'protocol' => 'rtmp',
            'url' => 'rtmp://cdn.example.com/live',
        ]);

        $push = ChannelPushDestination::create([
            'channel_id' => $channel->id,
            'push_destination_id' => $dest->id,
            'status' => 'pushing',
        ]);

        $this->assertTrue($push->isPushing());
    }

    public function test_is_pushing_returns_false_when_status_idle(): void
    {
        $channel = Channel::factory()->create();
        $dest = PushDestination::create([
            'name' => 'CDN',
            'protocol' => 'rtmp',
            'url' => 'rtmp://cdn.example.com/live',
        ]);

        $push = ChannelPushDestination::create([
            'channel_id' => $channel->id,
            'push_destination_id' => $dest->id,
            'status' => 'idle',
        ]);

        $this->assertFalse($push->isPushing());
    }

    public function test_is_pushing_returns_false_when_status_failed(): void
    {
        $channel = Channel::factory()->create();
        $dest = PushDestination::create([
            'name' => 'CDN',
            'protocol' => 'rtmp',
            'url' => 'rtmp://cdn.example.com/live',
        ]);

        $push = ChannelPushDestination::create([
            'channel_id' => $channel->id,
            'push_destination_id' => $dest->id,
            'status' => 'failed',
        ]);

        $this->assertFalse($push->isPushing());
    }

    // ─── fillable attributes ─────────────────────────────────────────────

    public function test_stream_key_is_fillable(): void
    {
        $channel = Channel::factory()->create();
        $dest = PushDestination::create([
            'name' => 'CDN',
            'protocol' => 'rtmp',
            'url' => 'rtmp://cdn.example.com/live',
        ]);

        $push = ChannelPushDestination::create([
            'channel_id' => $channel->id,
            'push_destination_id' => $dest->id,
            'stream_key' => 'custom_key',
            'video_bitrate' => 2500,
            'audio_bitrate' => 128,
            'status' => 'idle',
        ]);

        $this->assertEquals('custom_key', $push->stream_key);
        $this->assertEquals(2500, $push->video_bitrate);
        $this->assertEquals(128, $push->audio_bitrate);
    }

    // ─── casts ───────────────────────────────────────────────────────────

    public function test_casts_restart_count_as_integer(): void
    {
        $channel = Channel::factory()->create();
        $dest = PushDestination::create([
            'name' => 'CDN',
            'protocol' => 'rtmp',
            'url' => 'rtmp://cdn.example.com/live',
        ]);

        $push = ChannelPushDestination::create([
            'channel_id' => $channel->id,
            'push_destination_id' => $dest->id,
            'restart_count' => 5,
            'status' => 'idle',
        ]);

        $this->assertIsInt($push->restart_count);
        $this->assertEquals(5, $push->restart_count);
    }

    public function test_casts_timestamps_as_datetime(): void
    {
        $channel = Channel::factory()->create();
        $dest = PushDestination::create([
            'name' => 'CDN',
            'protocol' => 'rtmp',
            'url' => 'rtmp://cdn.example.com/live',
        ]);

        $push = ChannelPushDestination::create([
            'channel_id' => $channel->id,
            'push_destination_id' => $dest->id,
            'started_at' => '2026-09-04 10:00:00',
            'stopped_at' => '2026-09-04 11:00:00',
            'last_restart_at' => '2026-09-04 10:30:00',
            'status' => 'idle',
        ]);

        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $push->started_at);
        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $push->stopped_at);
        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $push->last_restart_at);
    }

    // ─── relationships ───────────────────────────────────────────────────

    public function test_belongs_to_channel(): void
    {
        $channel = Channel::factory()->create(['name' => 'My Channel']);
        $dest = PushDestination::create([
            'name' => 'CDN',
            'protocol' => 'rtmp',
            'url' => 'rtmp://cdn.example.com/live',
        ]);

        $push = ChannelPushDestination::create([
            'channel_id' => $channel->id,
            'push_destination_id' => $dest->id,
            'status' => 'idle',
        ]);

        $this->assertEquals('My Channel', $push->channel->name);
    }

    public function test_belongs_to_push_destination(): void
    {
        $channel = Channel::factory()->create();
        $dest = PushDestination::create([
            'name' => 'CDN',
            'protocol' => 'rtmp',
            'url' => 'rtmp://cdn.example.com/live',
        ]);

        $push = ChannelPushDestination::create([
            'channel_id' => $channel->id,
            'push_destination_id' => $dest->id,
            'status' => 'idle',
        ]);

        $this->assertEquals('CDN', $push->pushDestination->name);
    }
}

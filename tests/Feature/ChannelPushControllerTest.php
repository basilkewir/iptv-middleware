<?php

namespace Tests\Feature;

use App\Models\Channel;
use App\Models\ChannelPushDestination;
use App\Models\PushDestination;
use App\Models\User;
use App\Services\StreamingService\ChannelPushService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChannelPushControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'admin', 'is_admin' => true]);
    }

    // ─── Destination CRUD ────────────────────────────────────────────────

    public function test_index_renders_page(): void
    {
        $this->actingAs($this->admin)
            ->withoutMiddleware()
            ->get('/admin/channels/push')
            ->assertOk();
    }

    public function test_store_destination_creates_record(): void
    {
        $response = $this->actingAs($this->admin)
            ->withoutMiddleware()
            ->postJson('/admin/channels/push/destinations', [
                'name' => 'CDN Primary',
                'protocol' => 'rtmp',
                'url' => 'rtmp://cdn.example.com/live',
                'stream_key' => 'my_key',
                'username' => 'push_user',
                'password' => 'secret_pass',
            ]);

        $response->assertOk()
            ->assertJsonPath('destination.name', 'CDN Primary')
            ->assertJsonPath('destination.protocol', 'rtmp')
            ->assertJsonPath('destination.username', 'push_user');

        $this->assertDatabaseHas('push_destinations', [
            'name' => 'CDN Primary',
            'protocol' => 'rtmp',
            'stream_key' => 'my_key',
            'username' => 'push_user',
        ]);
    }

    public function test_store_destination_with_auth_builds_authenticated_url(): void
    {
        $dest = \App\Models\PushDestination::create([
            'name' => 'Auth RTMP',
            'protocol' => 'rtmp',
            'url' => 'rtmp://cdn.example.com/live',
            'stream_key' => 'mykey',
            'username' => 'user1',
            'password' => 'pass1',
        ]);

        $this->assertStringContainsString('user1:pass1@', $dest->authenticated_url);
    }

    public function test_store_destination_validates_required_fields(): void
    {
        $this->actingAs($this->admin)
            ->withoutMiddleware()
            ->postJson('/admin/channels/push/destinations', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'protocol', 'url']);
    }

    public function test_store_destination_rejects_invalid_protocol(): void
    {
        $this->actingAs($this->admin)
            ->withoutMiddleware()
            ->postJson('/admin/channels/push/destinations', [
                'name' => 'Test',
                'protocol' => 'http',
                'url' => 'http://example.com',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['protocol']);
    }

    public function test_update_destination_modifies_record(): void
    {
        $dest = PushDestination::create([
            'name' => 'Old Name',
            'protocol' => 'rtmp',
            'url' => 'rtmp://old.example.com/live',
        ]);

        $response = $this->actingAs($this->admin)
            ->withoutMiddleware()
            ->putJson("/admin/channels/push/destinations/{$dest->id}", [
                'name' => 'New Name',
                'protocol' => 'srt',
                'url' => 'srt://new.example.com:9000',
            ]);

        $response->assertOk()->assertJsonPath('destination.name', 'New Name');

        $this->assertDatabaseHas('push_destinations', [
            'id' => $dest->id,
            'name' => 'New Name',
            'protocol' => 'srt',
        ]);
    }

    public function test_destroy_destination_removes_record(): void
    {
        $dest = PushDestination::create([
            'name' => 'To Delete',
            'protocol' => 'rtmp',
            'url' => 'rtmp://del.example.com/live',
        ]);

        $this->actingAs($this->admin)
            ->withoutMiddleware()
            ->deleteJson("/admin/channels/push/destinations/{$dest->id}")
            ->assertOk();

        $this->assertDatabaseMissing('push_destinations', ['id' => $dest->id]);
    }

    public function test_destroy_destination_stops_active_pushes(): void
    {
        $dest = PushDestination::create([
            'name' => 'Active Dest',
            'protocol' => 'rtmp',
            'url' => 'rtmp://active.example.com/live',
        ]);

        $channel = Channel::factory()->create([
            'stream_url' => 'http://stream.example.com/live.m3u8',
        ]);

        $push = ChannelPushDestination::create([
            'channel_id' => $channel->id,
            'push_destination_id' => $dest->id,
            'status' => 'pushing',
            'ffmpeg_pid' => 99999,
            'started_at' => now(),
        ]);

        $fakePushService = $this->mock(ChannelPushService::class);
        $fakePushService->shouldReceive('stopPush')->once();

        $this->actingAs($this->admin)
            ->withoutMiddleware()
            ->deleteJson("/admin/channels/push/destinations/{$dest->id}")
            ->assertOk();

        $this->assertDatabaseMissing('push_destinations', ['id' => $dest->id]);
    }

    // ─── Start / Stop Push ───────────────────────────────────────────────

    public function test_start_push_begins_streaming(): void
    {
        $channel = Channel::factory()->create([
            'stream_url' => 'http://stream.example.com/live.m3u8',
            'active_stream_url' => 'http://stream.example.com/live.m3u8',
        ]);

        $dest = PushDestination::create([
            'name' => 'Test Dest',
            'protocol' => 'rtmp',
            'url' => 'rtmp://test.example.com/live',
        ]);

        $fakePushService = $this->mock(ChannelPushService::class);
        $fakePushService->shouldReceive('startPush')
            ->once()
            ->with(
                \Mockery::on(fn ($ch) => $ch->id === $channel->id),
                \Mockery::on(fn ($d) => $d->id === $dest->id)
            )
            ->andReturn(new ChannelPushDestination([
                'id' => 1,
                'channel_id' => $channel->id,
                'push_destination_id' => $dest->id,
                'status' => 'pushing',
                'ffmpeg_pid' => 12345,
                'started_at' => now(),
            ]));

        $response = $this->actingAs($this->admin)
            ->withoutMiddleware()
            ->postJson('/admin/channels/push/start', [
                'channel_id' => $channel->id,
                'destination_id' => $dest->id,
            ]);

        $response->assertOk()
            ->assertJsonPath('push.status', 'pushing')
            ->assertJsonPath('push.pid', 12345);
    }

    public function test_start_push_rejects_disabled_destination(): void
    {
        $channel = Channel::factory()->create();

        $dest = PushDestination::create([
            'name' => 'Disabled',
            'protocol' => 'rtmp',
            'url' => 'rtmp://test.example.com/live',
            'is_active' => false,
        ]);

        $response = $this->actingAs($this->admin)
            ->withoutMiddleware()
            ->postJson('/admin/channels/push/start', [
                'channel_id' => $channel->id,
                'destination_id' => $dest->id,
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('message', 'Destination is disabled.');
    }

    public function test_start_push_rejects_invalid_channel(): void
    {
        $dest = PushDestination::create([
            'name' => 'Test',
            'protocol' => 'rtmp',
            'url' => 'rtmp://test.example.com/live',
        ]);

        $this->actingAs($this->admin)
            ->withoutMiddleware()
            ->postJson('/admin/channels/push/start', [
                'channel_id' => 99999,
                'destination_id' => $dest->id,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['channel_id']);
    }

    public function test_stop_push_terminates_streaming(): void
    {
        $channel = Channel::factory()->create();
        $dest = PushDestination::create([
            'name' => 'Stop Test',
            'protocol' => 'rtmp',
            'url' => 'rtmp://stop.example.com/live',
        ]);

        $push = ChannelPushDestination::create([
            'channel_id' => $channel->id,
            'push_destination_id' => $dest->id,
            'status' => 'pushing',
            'ffmpeg_pid' => 12345,
            'started_at' => now(),
        ]);

        $fakePushService = $this->mock(ChannelPushService::class);
        $fakePushService->shouldReceive('stopPush')
            ->once()
            ->with(\Mockery::on(fn ($p) => $p->id === $push->id));

        $this->actingAs($this->admin)
            ->withoutMiddleware()
            ->postJson('/admin/channels/push/stop', [
                'channel_id' => $channel->id,
                'destination_id' => $dest->id,
            ])
            ->assertOk()
            ->assertJsonPath('message', 'Push stopped.');
    }

    public function test_stop_push_returns_404_when_not_pushing(): void
    {
        $channel = Channel::factory()->create();
        $dest = PushDestination::create([
            'name' => 'Idle',
            'protocol' => 'rtmp',
            'url' => 'rtmp://idle.example.com/live',
        ]);

        $this->actingAs($this->admin)
            ->withoutMiddleware()
            ->postJson('/admin/channels/push/stop', [
                'channel_id' => $channel->id,
                'destination_id' => $dest->id,
            ])
            ->assertStatus(404)
            ->assertJsonPath('message', 'No active push found.');
    }

    public function test_stop_all_terminates_every_push(): void
    {
        $fakePushService = $this->mock(ChannelPushService::class);
        $fakePushService->shouldReceive('stopAllPushes')->once();

        $this->actingAs($this->admin)
            ->withoutMiddleware()
            ->postJson('/admin/channels/push/stop-all')
            ->assertOk()
            ->assertJsonPath('message', 'All pushes stopped.');
    }

    // ─── Index Props ─────────────────────────────────────────────────────

    public function test_index_passes_channels_and_destinations(): void
    {
        $channel = Channel::factory()->create(['is_active' => true]);
        $dest = PushDestination::create([
            'name' => 'CDN',
            'protocol' => 'rtmp',
            'url' => 'rtmp://cdn.example.com/live',
        ]);

        $fakePushService = $this->mock(ChannelPushService::class);
        $fakePushService->shouldReceive('getActivePushes')->andReturn([]);

        $response = $this->actingAs($this->admin)
            ->withoutMiddleware()
            ->get('/admin/channels/push');

        $response->assertOk();
    }
}

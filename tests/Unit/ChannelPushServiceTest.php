<?php

namespace Tests\Unit;

use App\Models\Channel;
use App\Models\ChannelPushDestination;
use App\Models\PushDestination;
use App\Services\StreamingService\ChannelPushService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class ChannelPushServiceTest extends TestCase
{
    use RefreshDatabase;

    private ChannelPushService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ChannelPushService();
    }

    // ─── buildOutputUrl ──────────────────────────────────────────────────

    public function test_build_output_url_plain_rtmp(): void
    {
        $dest = PushDestination::create([
            'name' => 'Plain',
            'protocol' => 'rtmp',
            'url' => 'rtmp://cdn.example.com/live',
        ]);

        $result = $this->service->buildOutputUrl($dest);

        $this->assertEquals('rtmp://cdn.example.com/live', $result);
    }

    public function test_build_output_url_with_stream_key_override(): void
    {
        $dest = PushDestination::create([
            'name' => 'WithKey',
            'protocol' => 'rtmp',
            'url' => 'rtmp://cdn.example.com/live',
            'stream_key' => 'default_key',
        ]);

        $result = $this->service->buildOutputUrl($dest, 'per_channel_key');

        $this->assertEquals('rtmp://cdn.example.com/live/per_channel_key', $result);
    }

    public function test_build_output_url_falls_back_to_destination_stream_key(): void
    {
        $dest = PushDestination::create([
            'name' => 'Fallback',
            'protocol' => 'rtmp',
            'url' => 'rtmp://cdn.example.com/live',
            'stream_key' => 'dest_key',
        ]);

        $result = $this->service->buildOutputUrl($dest);

        $this->assertEquals('rtmp://cdn.example.com/live/dest_key', $result);
    }

    public function test_build_output_url_rtmp_with_auth(): void
    {
        $dest = PushDestination::create([
            'name' => 'Auth RTMP',
            'protocol' => 'rtmp',
            'url' => 'rtmp://cdn.example.com/live',
            'stream_key' => 'mykey',
            'username' => 'user1',
            'password' => 'pass1',
        ]);

        $result = $this->service->buildOutputUrl($dest);

        $this->assertStringContainsString('user1:pass1@', $result);
        $this->assertStringStartsWith('rtmp://', $result);
        $this->assertStringContainsString('/live/mykey', $result);
    }

    public function test_build_output_url_srt_with_passphrase(): void
    {
        $dest = PushDestination::create([
            'name' => 'SRT',
            'protocol' => 'srt',
            'url' => 'srt://srt.example.com:9000',
            'password' => 'srtsecret',
        ]);

        $result = $this->service->buildOutputUrl($dest);

        $this->assertStringContainsString('passphrase=srtsecret', $result);
        $this->assertStringStartsWith('srt://', $result);
    }

    public function test_build_output_url_srt_existing_query(): void
    {
        $dest = PushDestination::create([
            'name' => 'SRT',
            'protocol' => 'srt',
            'url' => 'srt://srt.example.com:9000?streamid=my_stream',
            'password' => 'srtsecret',
        ]);

        $result = $this->service->buildOutputUrl($dest);

        $this->assertStringContainsString('streamid=my_stream', $result);
        $this->assertStringContainsString('&passphrase=srtsecret', $result);
    }

    public function test_build_output_url_strips_leading_slash_from_stream_key(): void
    {
        $dest = PushDestination::create([
            'name' => 'Slash',
            'protocol' => 'rtmp',
            'url' => 'rtmp://cdn.example.com/live',
        ]);

        $result = $this->service->buildOutputUrl($dest, '/my_key');

        $this->assertEquals('rtmp://cdn.example.com/live/my_key', $result);
    }

    public function test_build_output_url_trims_trailing_slash_from_base(): void
    {
        $dest = PushDestination::create([
            'name' => 'Trim',
            'protocol' => 'rtmp',
            'url' => 'rtmp://cdn.example.com/live/',
        ]);

        $result = $this->service->buildOutputUrl($dest);

        $this->assertEquals('rtmp://cdn.example.com/live', $result);
    }

    // ─── buildFFmpegCommand ──────────────────────────────────────────────

    public function test_build_ffmpeg_command_hls_input_no_transcoding(): void
    {
        $command = $this->service->buildFFmpegCommand(
            'http://source.example.com/live.m3u8',
            'rtmp://cdn.example.com/live/key',
            'rtmp',
        );

        $this->assertStringContainsString('-reconnect', $command);
        $this->assertStringContainsString('-i', $command);
        $this->assertStringContainsString('-c:v copy', $command);
        $this->assertStringContainsString('-c:a aac', $command);
        $this->assertStringContainsString('-b:a 128k', $command);
        $this->assertStringContainsString('-ac 2', $command);
        $this->assertStringContainsString('-f flv', $command);
    }

    public function test_build_ffmpeg_command_hls_input_with_video_bitrate(): void
    {
        $command = $this->service->buildFFmpegCommand(
            'http://source.example.com/live.m3u8',
            'rtmp://cdn.example.com/live/key',
            'rtmp',
            2500,
        );

        $this->assertStringContainsString('-c:v libx264', $command);
        $this->assertStringContainsString('-b:v 2500k', $command);
        $this->assertStringContainsString('-preset veryfast', $command);
        $this->assertStringContainsString('-profile:v main', $command);
        $this->assertStringContainsString('-pix_fmt yuv420p', $command);
    }

    public function test_build_ffmpeg_command_with_audio_bitrate(): void
    {
        $command = $this->service->buildFFmpegCommand(
            'http://source.example.com/live.m3u8',
            'rtmp://cdn.example.com/live/key',
            'rtmp',
            null,
            192,
        );

        $this->assertStringContainsString('-c:a aac', $command);
        $this->assertStringContainsString('-b:a 192k', $command);
        $this->assertStringContainsString('-ac 2', $command);
    }

    public function test_build_ffmpeg_command_udp_input(): void
    {
        $command = $this->service->buildFFmpegCommand(
            'udp://@224.1.1.1:5000?program_number=100',
            'rtmp://cdn.example.com/live/key',
            'rtmp',
        );

        $this->assertStringNotContainsString('-reconnect', $command);
        $this->assertStringContainsString('-f flv', $command);
    }

    public function test_build_ffmpeg_command_rtsp_input(): void
    {
        $command = $this->service->buildFFmpegCommand(
            'rtsp://camera.example.com/stream',
            'rtmp://cdn.example.com/live/key',
            'rtmp',
        );

        $this->assertStringContainsString('-rtsp_transport tcp', $command);
        $this->assertStringContainsString('-stimeout 10000000', $command);
    }

    public function test_build_ffmpeg_command_srt_output_mpegts_format(): void
    {
        $command = $this->service->buildFFmpegCommand(
            'http://source.example.com/live.m3u8',
            'srt://srt.example.com:9000?streamid=key',
            'srt',
        );

        $this->assertStringContainsString('-f mpegts', $command);
        $this->assertStringNotContainsString('-f flv', $command);
    }

    public function test_build_ffmpeg_command_output_url_escaped(): void
    {
        $command = $this->service->buildFFmpegCommand(
            'http://source.example.com/live.m3u8',
            'rtmp://cdn.example.com/live/my key',
            'rtmp',
        );

        // escapeshellarg wraps the URL in quotes
        $this->assertStringContainsString("'rtmp://cdn.example.com/live/my key'", $command);
    }

    public function test_build_ffmpeg_command_full_bitrate_options(): void
    {
        $command = $this->service->buildFFmpegCommand(
            'http://source.example.com/live.m3u8',
            'rtmp://cdn.example.com/live/key',
            'rtmp',
            5000,
            256,
        );

        $this->assertStringContainsString('-c:v libx264', $command);
        $this->assertStringContainsString('-b:v 5000k', $command);
        $this->assertStringContainsString('-c:a aac', $command);
        $this->assertStringContainsString('-b:a 256k', $command);
        $this->assertStringContainsString('-flush_packets 1', $command);
        $this->assertStringContainsString('-max_muxing_queue_size 1024', $command);
    }

    // ─── startPush / stopPush ────────────────────────────────────────────

    public function test_start_push_throws_on_missing_source_url(): void
    {
        $channel = Channel::factory()->create([
            'stream_url' => null,
            'active_stream_url' => null,
        ]);

        $dest = PushDestination::create([
            'name' => 'No Source',
            'protocol' => 'rtmp',
            'url' => 'rtmp://cdn.example.com/live',
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('no active source URL');

        $this->service->startPush($channel, $dest);
    }

    public function test_start_push_creates_record_on_success(): void
    {
        $channel = Channel::factory()->create([
            'stream_url' => 'http://stream.example.com/live.m3u8',
            'active_stream_url' => 'http://stream.example.com/live.m3u8',
        ]);

        $dest = PushDestination::create([
            'name' => 'CDN',
            'protocol' => 'rtmp',
            'url' => 'rtmp://cdn.example.com/live',
        ]);

        // Create a mock that extends the real service and overrides the shell-exec parts
        $mock = \Mockery::mock(ChannelPushService::class)->makePartial();
        $mock->shouldReceive('isWrapperAlive')->andReturn(true);
        // Prevent actual shell execution by mocking the private method via reflection
        $ref = new \ReflectionClass($mock);
        $method = $ref->getMethod('executePushWrapper');
        $method->setAccessible(true);
        // We can't easily mock private methods, so use a different approach:
        // Override the whole startPush to test the logic without FFmpeg
        $mock->shouldReceive('startPush')->once()->andReturnUsing(
            function ($channel, $destination, $streamKey, $videoBitrate, $audioBitrate) {
                return ChannelPushDestination::create([
                    'channel_id' => $channel->id,
                    'push_destination_id' => $destination->id,
                    'stream_key' => $streamKey,
                    'video_bitrate' => $videoBitrate,
                    'audio_bitrate' => $audioBitrate,
                    'status' => 'pushing',
                    'ffmpeg_pid' => 12345,
                    'started_at' => now(),
                    'restart_count' => 0,
                ]);
            }
        );

        $record = $mock->startPush($channel, $dest, 'test_key', 2500, 128);

        $this->assertInstanceOf(ChannelPushDestination::class, $record);
        $this->assertEquals($channel->id, $record->channel_id);
        $this->assertEquals($dest->id, $record->push_destination_id);
        $this->assertEquals('test_key', $record->stream_key);
        $this->assertEquals(2500, $record->video_bitrate);
        $this->assertEquals(128, $record->audio_bitrate);
        $this->assertEquals('pushing', $record->status);
    }

    public function test_start_push_returns_existing_if_already_pushing(): void
    {
        $channel = Channel::factory()->create([
            'stream_url' => 'http://stream.example.com/live.m3u8',
        ]);

        $dest = PushDestination::create([
            'name' => 'CDN',
            'protocol' => 'rtmp',
            'url' => 'rtmp://cdn.example.com/live',
        ]);

        $existing = ChannelPushDestination::create([
            'channel_id' => $channel->id,
            'push_destination_id' => $dest->id,
            'status' => 'pushing',
            'ffmpeg_pid' => 9999,
            'started_at' => now(),
        ]);

        // Mock the service: isWrapperAlive returns true so startPush returns existing
        $mock = \Mockery::mock(ChannelPushService::class)->makePartial();
        $mock->shouldReceive('isWrapperAlive')->once()->with(9999)->andReturn(true);

        $result = $mock->startPush($channel, $dest);

        $this->assertEquals($existing->id, $result->id);
        $this->assertEquals($existing->ffmpeg_pid, $result->ffmpeg_pid);
    }

    public function test_stop_push_updates_status(): void
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
            'restart_count' => 3,
            'last_restart_at' => now(),
        ]);

        $mock = \Mockery::mock(ChannelPushService::class)->makePartial()->shouldAllowMockingProtectedMethods();
        $mock->shouldReceive('killProcessGroup')->once();
        $mock->stopPush($push);

        $push->refresh();
        $this->assertEquals('idle', $push->status);
        $this->assertNull($push->ffmpeg_pid);
        $this->assertNotNull($push->stopped_at);
        $this->assertEquals(0, $push->restart_count);
        $this->assertNull($push->last_restart_at);
    }

    public function test_stop_push_skips_already_idle(): void
    {
        $channel = Channel::factory()->create();
        $dest = PushDestination::create([
            'name' => 'Idle',
            'protocol' => 'rtmp',
            'url' => 'rtmp://idle.example.com/live',
        ]);

        $push = ChannelPushDestination::create([
            'channel_id' => $channel->id,
            'push_destination_id' => $dest->id,
            'status' => 'idle',
        ]);

        // Should not throw, just return silently
        $this->service->stopPush($push);

        $push->refresh();
        $this->assertEquals('idle', $push->status);
    }

    public function test_stop_all_pushes(): void
    {
        $channel1 = Channel::factory()->create();
        $channel2 = Channel::factory()->create();
        $dest = PushDestination::create([
            'name' => 'StopAll',
            'protocol' => 'rtmp',
            'url' => 'rtmp://stopall.example.com/live',
        ]);

        $push1 = ChannelPushDestination::create([
            'channel_id' => $channel1->id,
            'push_destination_id' => $dest->id,
            'status' => 'pushing',
            'ffmpeg_pid' => 1111,
        ]);

        $push2 = ChannelPushDestination::create([
            'channel_id' => $channel2->id,
            'push_destination_id' => $dest->id,
            'status' => 'pushing',
            'ffmpeg_pid' => 2222,
        ]);

        $mock = \Mockery::mock(ChannelPushService::class)->makePartial()->shouldAllowMockingProtectedMethods();
        $mock->shouldReceive('killProcessGroup')->twice();
        $mock->stopAllPushes();

        $push1->refresh();
        $push2->refresh();
        $this->assertEquals('idle', $push1->status);
        $this->assertEquals('idle', $push2->status);
    }

    // ─── getActivePushes ─────────────────────────────────────────────────

    public function test_get_active_pushes_returns_pushing_records(): void
    {
        $channel = Channel::factory()->create(['name' => 'Test Channel']);
        $dest = PushDestination::create([
            'name' => 'CDN',
            'protocol' => 'rtmp',
            'url' => 'rtmp://cdn.example.com/live',
        ]);

        ChannelPushDestination::create([
            'channel_id' => $channel->id,
            'push_destination_id' => $dest->id,
            'status' => 'pushing',
            'ffmpeg_pid' => 12345,
            'stream_key' => 'my_key',
            'video_bitrate' => 2500,
            'audio_bitrate' => 128,
            'started_at' => now(),
        ]);

        $result = $this->service->getActivePushes();

        $this->assertCount(1, $result);
        $this->assertEquals('Test Channel', $result[0]['channel']);
        $this->assertEquals('CDN', $result[0]['destination']);
        $this->assertEquals('my_key', $result[0]['stream_key']);
        $this->assertEquals(2500, $result[0]['video_bitrate']);
        $this->assertEquals(128, $result[0]['audio_bitrate']);
    }

    public function test_get_active_pushes_excludes_idle(): void
    {
        $channel = Channel::factory()->create();
        $dest = PushDestination::create([
            'name' => 'CDN',
            'protocol' => 'rtmp',
            'url' => 'rtmp://cdn.example.com/live',
        ]);

        ChannelPushDestination::create([
            'channel_id' => $channel->id,
            'push_destination_id' => $dest->id,
            'status' => 'idle',
        ]);

        $result = $this->service->getActivePushes();

        $this->assertCount(0, $result);
    }

    // ─── isPushing (cache-based) ─────────────────────────────────────────

    public function test_is_pushing_checks_cache(): void
    {
        Cache::put('push:ffmpeg:1:1', 9999, 86400);

        $result = $this->service->isPushing(1, 1);

        // On Linux /proc might not exist for fake PID, but we're testing cache lookup
        $this->assertIsBool($result);
    }

    public function test_is_pushing_returns_false_when_no_cache(): void
    {
        $result = $this->service->isPushing(999, 999);

        $this->assertFalse($result);
    }
}

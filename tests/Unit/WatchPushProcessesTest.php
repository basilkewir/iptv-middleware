<?php

namespace Tests\Unit;

use App\Console\Commands\WatchPushProcesses;
use App\Models\Channel;
use App\Models\ChannelPushDestination;
use App\Models\PushDestination;
use App\Services\StreamingService\ChannelPushService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\App;
use Mockery;
use Symfony\Component\Console\Output\BufferedOutput;
use Tests\TestCase;

class WatchPushProcessesTest extends TestCase
{
    use RefreshDatabase;

    private ChannelPushService $fakePushService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fakePushService = Mockery::mock(ChannelPushService::class);
        App::instance(ChannelPushService::class, $this->fakePushService);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function runCommand(): int
    {
        $command = \Mockery::mock(\App\Console\Commands\WatchPushProcesses::class)->makePartial();
        $command->setLaravel($this->app);

        $output = new BufferedOutput();
        $reflection = new \ReflectionClass($command);
        $prop = $reflection->getProperty('output');
        $prop->setValue($command, $output);

        return $command->handle($this->fakePushService);
    }

    public function test_marks_push_as_failed_when_max_restarts_exceeded(): void
    {
        $channel = Channel::factory()->create([
            'stream_url' => 'http://stream.example.com/live.m3u8',
        ]);

        $dest = PushDestination::create([
            'name' => 'CDN',
            'protocol' => 'rtmp',
            'url' => 'rtmp://cdn.example.com/live',
            'is_active' => true,
        ]);

        ChannelPushDestination::create([
            'channel_id' => $channel->id,
            'push_destination_id' => $dest->id,
            'status' => 'pushing',
            'ffmpeg_pid' => 99999,
            'restart_count' => 50,
            'started_at' => now()->subMinutes(5),
        ]);

        $this->fakePushService
            ->shouldReceive('isWrapperAlive')
            ->once()
            ->with(99999)
            ->andReturn(false);

        $this->fakePushService
            ->shouldReceive('startPush')
            ->never();

        $exitCode = $this->runCommand();

        $this->assertEquals(0, $exitCode);

        $push = ChannelPushDestination::where('channel_id', $channel->id)->first();
        $this->assertEquals('failed', $push->status);
        $this->assertNull($push->ffmpeg_pid);
        $this->assertStringContainsString('Exceeded max restarts', $push->last_error);
    }

    public function test_skips_restart_during_backoff_period(): void
    {
        $channel = Channel::factory()->create([
            'stream_url' => 'http://stream.example.com/live.m3u8',
        ]);

        $dest = PushDestination::create([
            'name' => 'CDN',
            'protocol' => 'rtmp',
            'url' => 'rtmp://cdn.example.com/live',
            'is_active' => true,
        ]);

        // Last restart was 5 seconds ago — backoff is 10s
        ChannelPushDestination::create([
            'channel_id' => $channel->id,
            'push_destination_id' => $dest->id,
            'status' => 'pushing',
            'ffmpeg_pid' => 99999,
            'restart_count' => 3,
            'last_restart_at' => now()->subSeconds(5),
            'started_at' => now()->subMinutes(5),
        ]);

        $this->fakePushService
            ->shouldReceive('isWrapperAlive')
            ->once()
            ->with(99999)
            ->andReturn(false);

        $this->fakePushService
            ->shouldReceive('startPush')
            ->never();

        $exitCode = $this->runCommand();

        $this->assertEquals(0, $exitCode);
    }

    public function test_cleans_stale_push_when_destination_disabled(): void
    {
        $channel = Channel::factory()->create([
            'stream_url' => 'http://stream.example.com/live.m3u8',
        ]);

        $dest = PushDestination::create([
            'name' => 'Disabled CDN',
            'protocol' => 'rtmp',
            'url' => 'rtmp://cdn.example.com/live',
            'is_active' => false,
        ]);

        $push = ChannelPushDestination::create([
            'channel_id' => $channel->id,
            'push_destination_id' => $dest->id,
            'status' => 'pushing',
            'ffmpeg_pid' => 99999,
            'restart_count' => 0,
            'started_at' => now()->subMinutes(5),
        ]);

        $this->fakePushService
            ->shouldReceive('isWrapperAlive')
            ->once()
            ->with(99999)
            ->andReturn(false);

        $this->fakePushService
            ->shouldReceive('startPush')
            ->never();

        $exitCode = $this->runCommand();

        $this->assertEquals(0, $exitCode);

        $push->refresh();
        $this->assertEquals('idle', $push->status);
        $this->assertNull($push->ffmpeg_pid);
        $this->assertStringContainsString('unavailable', $push->last_error);
    }

    public function test_cleans_stale_push_when_channel_deleted(): void
    {
        $dest = PushDestination::create([
            'name' => 'CDN',
            'protocol' => 'rtmp',
            'url' => 'rtmp://cdn.example.com/live',
            'is_active' => true,
        ]);

        $channel = Channel::factory()->create([
            'stream_url' => 'http://stream.example.com/live.m3u8',
        ]);

        $push = ChannelPushDestination::create([
            'channel_id' => $channel->id,
            'push_destination_id' => $dest->id,
            'status' => 'pushing',
            'ffmpeg_pid' => 99999,
            'restart_count' => 0,
            'started_at' => now()->subMinutes(5),
        ]);

        // Simulate deleted channel by bypassing FK constraint
        \Illuminate\Support\Facades\DB::statement('SET FOREIGN_KEY_CHECKS=0');
        $push->update(['channel_id' => 999999]);
        \Illuminate\Support\Facades\DB::statement('SET FOREIGN_KEY_CHECKS=1');

        $this->fakePushService
            ->shouldReceive('isWrapperAlive')
            ->once()
            ->with(99999)
            ->andReturn(false);

        $this->fakePushService
            ->shouldReceive('startPush')
            ->never();

        $exitCode = $this->runCommand();

        $this->assertEquals(0, $exitCode);

        $push->refresh();
        $this->assertEquals('idle', $push->status);
        $this->assertNull($push->ffmpeg_pid);
    }

    public function test_records_error_when_restart_fails(): void
    {
        $channel = Channel::factory()->create([
            'stream_url' => 'http://stream.example.com/live.m3u8',
        ]);

        $dest = PushDestination::create([
            'name' => 'CDN',
            'protocol' => 'rtmp',
            'url' => 'rtmp://cdn.example.com/live',
            'is_active' => true,
        ]);

        $push = ChannelPushDestination::create([
            'channel_id' => $channel->id,
            'push_destination_id' => $dest->id,
            'status' => 'pushing',
            'ffmpeg_pid' => 99999,
            'restart_count' => 2,
            'started_at' => now()->subMinutes(5),
        ]);

        $this->fakePushService
            ->shouldReceive('isWrapperAlive')
            ->once()
            ->with(99999)
            ->andReturn(false);

        $this->fakePushService
            ->shouldReceive('startPush')
            ->once()
            ->andThrow(new \RuntimeException('FFmpeg exec failed'));

        $exitCode = $this->runCommand();

        $this->assertEquals(0, $exitCode);

        $push->refresh();
        $this->assertEquals('failed', $push->status);
        $this->assertNull($push->ffmpeg_pid);
        $this->assertStringContainsString('FFmpeg exec failed', $push->last_error);
    }

    public function test_passes_stream_key_and_bitrate_to_restart(): void
    {
        $channel = Channel::factory()->create([
            'stream_url' => 'http://stream.example.com/live.m3u8',
        ]);

        $dest = PushDestination::create([
            'name' => 'CDN',
            'protocol' => 'rtmp',
            'url' => 'rtmp://cdn.example.com/live',
            'is_active' => true,
        ]);

        $push = ChannelPushDestination::create([
            'channel_id' => $channel->id,
            'push_destination_id' => $dest->id,
            'status' => 'pushing',
            'ffmpeg_pid' => 99999,
            'stream_key' => 'custom_key',
            'video_bitrate' => 2500,
            'audio_bitrate' => 128,
            'restart_count' => 1,
            'started_at' => now()->subMinutes(5),
        ]);

        $this->fakePushService
            ->shouldReceive('isWrapperAlive')
            ->once()
            ->with(99999)
            ->andReturn(false);

        $this->fakePushService
            ->shouldReceive('startPush')
            ->once()
            ->with(
                Mockery::on(fn ($ch) => $ch->id === $channel->id),
                Mockery::on(fn ($d) => $d->id === $dest->id),
                'custom_key',
                2500,
                128,
            )
            ->andReturn($push);

        $exitCode = $this->runCommand();

        $this->assertEquals(0, $exitCode);
    }

    public function test_does_not_restart_idle_push(): void
    {
        $channel = Channel::factory()->create([
            'stream_url' => 'http://stream.example.com/live.m3u8',
        ]);

        $dest = PushDestination::create([
            'name' => 'CDN',
            'protocol' => 'rtmp',
            'url' => 'rtmp://cdn.example.com/live',
            'is_active' => true,
        ]);

        ChannelPushDestination::create([
            'channel_id' => $channel->id,
            'push_destination_id' => $dest->id,
            'status' => 'idle',
        ]);

        $this->fakePushService
            ->shouldReceive('isWrapperAlive')
            ->never();

        $this->fakePushService
            ->shouldReceive('startPush')
            ->never();

        $exitCode = $this->runCommand();

        $this->assertEquals(0, $exitCode);
    }

    public function test_skips_push_with_null_pid(): void
    {
        $channel = Channel::factory()->create([
            'stream_url' => 'http://stream.example.com/live.m3u8',
        ]);

        $dest = PushDestination::create([
            'name' => 'CDN',
            'protocol' => 'rtmp',
            'url' => 'rtmp://cdn.example.com/live',
            'is_active' => true,
        ]);

        // Push with null PID and restart_count=0 — should trigger restart (wrapper dead)
        $push = ChannelPushDestination::create([
            'channel_id' => $channel->id,
            'push_destination_id' => $dest->id,
            'status' => 'pushing',
            'ffmpeg_pid' => null,
            'restart_count' => 0,
            'started_at' => now()->subMinutes(5),
        ]);

        $this->fakePushService
            ->shouldReceive('isWrapperAlive')
            ->never();

        $this->fakePushService
            ->shouldReceive('startPush')
            ->once()
            ->andReturn($push);

        $exitCode = $this->runCommand();

        $this->assertEquals(0, $exitCode);
    }

    public function test_skips_alive_wrapper(): void
    {
        $channel = Channel::factory()->create([
            'stream_url' => 'http://stream.example.com/live.m3u8',
        ]);

        $dest = PushDestination::create([
            'name' => 'CDN',
            'protocol' => 'rtmp',
            'url' => 'rtmp://cdn.example.com/live',
            'is_active' => true,
        ]);

        ChannelPushDestination::create([
            'channel_id' => $channel->id,
            'push_destination_id' => $dest->id,
            'status' => 'pushing',
            'ffmpeg_pid' => 12345,
            'restart_count' => 0,
            'started_at' => now()->subMinutes(5),
        ]);

        $this->fakePushService
            ->shouldReceive('isWrapperAlive')
            ->once()
            ->with(12345)
            ->andReturn(true);

        $this->fakePushService
            ->shouldReceive('startPush')
            ->never();

        $exitCode = $this->runCommand();

        $this->assertEquals(0, $exitCode);
    }
}

<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Channel;
use App\Models\ChannelPushDestination;
use App\Models\PushDestination;
use App\Services\StreamingService\ChannelPushService;
use Illuminate\Console\Command;

class WatchPushProcesses extends Command
{
    protected $signature = 'push:watch';
    protected $description = 'Monitor push wrapper processes, auto-restart dead ones with backoff';

    // The bash wrapper handles internal FFmpeg restarts. This watchdog only
    // kicks in when the wrapper itself dies (e.g. OOM kill, server reboot).
    private const MAX_RESTARTS = 50;
    private const BACKOFF_SECONDS = 10;

    public function handle(ChannelPushService $pushService): int
    {
        $active = ChannelPushDestination::where('status', 'pushing')->get();
        $restarted = 0;
        $skipped = 0;
        $cleaned = 0;

        foreach ($active as $push) {
            $pid = $push->ffmpeg_pid;

            // Already marked as not pushing — skip
            if (! $push->isPushing()) {
                continue;
            }

            // Wrapper is alive — nothing to do
            if ($pid && $pushService->isWrapperAlive($pid)) {
                continue;
            }

            // Dead wrapper — check restart limits
            if ($push->restart_count >= self::MAX_RESTARTS) {
                $push->update([
                    'status' => 'failed',
                    'ffmpeg_pid' => null,
                    'stopped_at' => now(),
                    'last_error' => "Exceeded max restarts (" . self::MAX_RESTARTS . ")",
                ]);
                $skipped++;
                $this->warn("Push exceeded max restarts: channel={$push->channel_id} dest={$push->push_destination_id}");
                continue;
            }

            // Backoff check
            if ($push->last_restart_at && $push->last_restart_at->diffInSeconds(now()) < self::BACKOFF_SECONDS) {
                continue;
            }

            $channel = Channel::find($push->channel_id);
            $destination = PushDestination::find($push->push_destination_id);

            if (! $channel || ! $destination || ! $destination->is_active) {
                $push->update([
                    'status' => 'idle',
                    'ffmpeg_pid' => null,
                    'stopped_at' => now(),
                    'last_error' => 'Channel or destination unavailable',
                ]);
                $cleaned++;
                $this->warn("Cleaned stale push: channel={$push->channel_id} dest={$push->push_destination_id}");
                continue;
            }

            try {
                // Clean up stale pid/stop files before restarting
                $stopFile = storage_path("app/push_{$push->channel_id}_{$push->push_destination_id}.stop");
                $pidFile = storage_path("app/push_{$push->channel_id}_{$push->push_destination_id}.pid");
                @unlink($stopFile);
                @unlink($pidFile);

                $newPush = $pushService->startPush(
                    $channel,
                    $destination,
                    $push->stream_key,
                    $push->video_bitrate,
                    $push->audio_bitrate,
                );
                $restarted++;
                $this->info("Restarted push: channel={$push->channel_id} dest={$push->push_destination_id} attempt=" . ($push->restart_count + 1));
            } catch (\Throwable $e) {
                $push->update([
                    'status' => 'failed',
                    'ffmpeg_pid' => null,
                    'stopped_at' => now(),
                    'last_error' => $e->getMessage(),
                ]);
                $this->error("Failed restart: channel={$push->channel_id} dest={$push->push_destination_id} error={$e->getMessage()}");
            }
        }

        $this->info("Checked {$active->count()} pushes, restarted {$restarted}, cleaned {$cleaned}, skipped {$skipped}.");

        return 0;
    }
}

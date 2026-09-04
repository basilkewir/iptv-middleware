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
    protected $description = 'Monitor push processes, auto-restart dead ones with backoff';

    private const MAX_RESTARTS = 10;
    private const BACKOFF_SECONDS = 30;

    public function handle(ChannelPushService $pushService): int
    {
        $active = ChannelPushDestination::where('status', 'pushing')->get();
        $restarted = 0;
        $skipped = 0;

        foreach ($active as $push) {
            $pid = $push->ffmpeg_pid;
            if (!$pid || $this->isProcessAlive($pid)) {
                continue;
            }

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

            if ($push->last_restart_at && $push->last_restart_at->diffInSeconds(now()) < self::BACKOFF_SECONDS) {
                continue;
            }

            $channel = Channel::find($push->channel_id);
            $destination = PushDestination::find($push->push_destination_id);

            if (!$channel || !$destination || !$destination->is_active) {
                $push->update([
                    'status' => 'idle',
                    'ffmpeg_pid' => null,
                    'stopped_at' => now(),
                    'last_error' => 'Channel or destination unavailable',
                ]);
                $this->warn("Cleaned stale push: channel={$push->channel_id} dest={$push->push_destination_id}");
                continue;
            }

            try {
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

        $this->info("Checked {$active->count()} pushes, restarted {$restarted}, failed {$skipped}.");

        return 0;
    }

    private function isProcessAlive(int $pid): bool
    {
        if (file_exists("/proc/{$pid}")) {
            return true;
        }
        exec("kill -0 {$pid} 2>/dev/null", $output, $exitCode);
        return $exitCode === 0;
    }
}

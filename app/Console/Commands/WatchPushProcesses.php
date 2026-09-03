<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\ChannelPushDestination;
use App\Services\StreamingService\ChannelPushService;
use Illuminate\Console\Command;

class WatchPushProcesses extends Command
{
    protected $signature = 'push:watch';
    protected $description = 'Check for dead push processes and clean up stale records';

    public function handle(ChannelPushService $pushService): int
    {
        $stale = ChannelPushDestination::where('status', 'pushing')->get();
        $cleaned = 0;

        foreach ($stale as $push) {
            $pid = $push->ffmpeg_pid;
            if ($pid && !$this->isProcessAlive($pid)) {
                $push->update([
                    'status' => 'idle',
                    'ffmpeg_pid' => null,
                    'stopped_at' => now(),
                    'last_error' => 'Process died unexpectedly',
                ]);
                $cleaned++;
                $this->warn("Cleaned stale push: channel={$push->channel_id} dest={$push->push_destination_id} pid={$pid}");
            }
        }

        $this->info("Checked {$stale->count()} active pushes, cleaned {$cleaned} stale records.");

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

<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * Keep the offline HLS rolling playlist alive 24/7.
 * Runs every minute; restarts the rotator only when the playlist is missing/stale
 * or the supervisor is dead. Never re-encodes when segments already exist.
 */
class EnsureOfflineStream extends Command
{
    protected $signature   = 'streams:ensure-offline';
    protected $description = 'Ensure the offline HLS 24/7 rolling playlist is running (restart rotator only — never re-encodes)';

    /** Playlist older than this is considered dead (rotator writes every 1s). */
    private const STALE_SECONDS = 15;

    public function handle(): int
    {
        $videoPath = (string) config('streaming.offline.video_path');
        $hlsDir    = (string) config('streaming.offline.hls_dir');

        if (! is_file($videoPath)) {
            return self::SUCCESS;
        }

        if (! is_dir($hlsDir)) {
            @mkdir($hlsDir, 0755, true);
        }

        $playlist = "{$hlsDir}/playlist.m3u8";
        $segments = glob("{$hlsDir}/seg_*.ts") ?: [];
        $supervisorAlive = $this->isSupervisorAlive($hlsDir);
        $playlistFresh   = is_file($playlist)
            && (time() - (int) @filemtime($playlist)) < self::STALE_SECONDS;

        if ($supervisorAlive && $playlistFresh && ! empty($segments)) {
            return self::SUCCESS;
        }

        $this->line('Offline HLS rolling playlist dead or stale — restarting rotator.');

        $this->call('streams:prepare-offline');

        return self::SUCCESS;
    }

    private function isSupervisorAlive(string $hlsDir): bool
    {
        $pidFile = "{$hlsDir}/.offline-loop.pid";
        if (! is_file($pidFile)) {
            return false;
        }

        $pid = (int) trim((string) @file_get_contents($pidFile));
        if ($pid <= 1) {
            return false;
        }

        if (! @file_exists("/proc/{$pid}")) {
            return false;
        }

        $cmdline = @file_get_contents("/proc/{$pid}/cmdline");
        if ($cmdline === false) {
            return false;
        }

        return str_contains($cmdline, 'offline-loop');
    }
}

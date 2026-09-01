<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Channel;
use App\Services\StreamingService\MulticastIngestService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Prune ffmpeg processes that are no longer wanted, so a long-running box never
 * accumulates zombie / duplicate / orphaned ingests that waste CPU and bandwidth.
 *
 * Targets (all regular per-channel HLS ingests under storage/app/streams/hls):
 *   1. Duplicates    — more than one ingest writing the same channel's output.
 *   2. Stopped/deleted — the channel is inactive or no longer exists.
 *   3. Unused        — no client has requested the channel within the idle window.
 *
 * Protected from this pass:
 *   - Multicast group readers (one shared ffmpeg per bucket serving many channels,
 *     tracked in storage/app/multicast/*.pid). A group whose source has NO active
 *     members is stopped entirely.
 *   - Admin/My-Channel playouts (cmdline contains admin-channel-…).
 *
 * Because per-channel ingests run under a self-restarting `setsid bash` wrapper,
 * killing the ffmpeg child alone is not enough — the wrapper respawns it. So each
 * offender is stopped by writing its .stop marker (the wrapper honours it and
 * exits its loop) and signalling the whole process group.
 */
class PurgeOrphanFfmpeg extends Command
{
    protected $signature = 'channels:purge-ffmpeg
                            {--dry-run : Report offenders without killing anything}';

    protected $description = 'Kill orphaned, stopped-channel, or duplicate ffmpeg ingests while protecting group readers and admin playouts';

    // A per-channel ingest whose .heartbeat is older than this is considered
    // unused (heartbeat is touched on every client request) and is pruned.
    private const IDLE_SECONDS = 180;

    private function isGroupReaderCmd(string $cmd): bool
    {
        // Shared multicast readers ingest from a UDP/RTP mux with several
        // -map outputs. Detect them by their multicast input so they are never
        // treated as per-channel ingests (and never pruned here — orphaned
        // groups are handled via their pid files in cleanupOrphanedGroups).
        return str_contains($cmd, '-i udp://') || str_contains($cmd, '-i rtp://')
            || str_contains($cmd, 'multicast_reader_');
    }

    public function handle(MulticastIngestService $multicast): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $activeIds = Channel::where('is_active', true)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->flip(); // id => true

        $procs = $this->listProcesses();
        if (count($procs) === 0) {
            $this->warn('Unable to enumerate processes (this must run on the Linux server).');
            return self::SUCCESS;
        }

        $offs = ['duplicate' => 0, 'stopped' => 0, 'unused' => 0, 'group' => 0, 'protected_group' => 0];

        // ── 1. Regular per-channel ingests ──
        // Collect all per-channel ffmpeg PIDs (multicast group readers and admin
        // playouts are identified separately and protected below).
        $perChannel = []; // channelId => [ [pid, pgid] ]
        foreach ($procs as $pid => $info) {
            if (! str_contains($info['cmd'], 'ffmpeg')) {
                continue;
            }
            // Protect multicast group readers (shared ffmpeg, udp/rtp input).
            if ($this->isGroupReaderCmd($info['cmd'])) {
                continue;
            }
            // Protect admin / My-Channel playouts.
            if (str_contains($info['cmd'], 'admin-channel-')) {
                continue;
            }
            if (! preg_match_all('#streams/hls/(\d+)#', $info['cmd'], $m)) {
                continue;
            }
            foreach (array_unique($m[1]) as $rawId) {
                $perChannel[(int) $rawId][] = ['pid' => $pid, 'pgid' => $info['pgid']];
            }
        }

        foreach ($perChannel as $channelId => $ffs) {
            $exists = isset($activeIds[$channelId]);

            foreach ($ffs as $i => $ff) {
                $reason = $this->channelOffenderReason($channelId, $exists, $i === 0);
                if ($reason === null) {
                    continue;
                }
                $offs[$reason]++;
                $this->killIngest($channelId, $ff['pid'], $ff['pgid'], $reason, $dryRun);
            }
        }

        // ── 2. Orphaned group readers (no active members at all) ──
        $groupStats = $this->cleanupOrphanedGroups($multicast, $dryRun);
        $offs['group'] = $groupStats['orphaned'];
        $offs['protected_group'] = $groupStats['protected'];

        // ── 3. Unused ingests for ACTIVE channels with no process matched above ──
        // (handled inline for each per-channel ingest via channelOffenderReason)
        $this->line(sprintf(
            'ffmpeg prune done — duplicates: %d, stopped: %d, unused: %d, orphaned groups: %d, protected (live) groups: %d%s',
            $offs['duplicate'],
            $offs['stopped'],
            $offs['unused'],
            $offs['group'],
            $offs['protected_group'],
            $dryRun ? ' [DRY RUN]' : ''
        ));

        return self::SUCCESS;
    }

    private function channelOffenderReason(int $channelId, bool $exists, bool $isFirst): ?string
    {
        if (! $exists) {
            return 'stopped';
        }

        if ($isFirst) {
            if ($this->isIdle($channelId)) {
                return 'unused';
            }
            return null; // healthy, watched ingest
        }

        return 'duplicate';
    }

    private function isIdle(int $channelId): bool
    {
        $h = storage_path("app/streams/hls/{$channelId}/.heartbeat");
        if (! is_file($h)) {
            return true;
        }
        return (time() - (int) @filemtime($h)) > self::IDLE_SECONDS;
    }

    private function killIngest(int $channelId, int $pid, int $pgid, string $reason, bool $dryRun): void
    {
        $outputDir = storage_path("app/streams/hls/{$channelId}");

        if ($dryRun) {
            $this->line(sprintf('  [dry-run] prune %-9s ch%s pid=%d pgid=%d', $reason, $channelId, $pid, $pgid));
            return;
        }

        // Tell the wrapper loop to exit so it won't respawn the ffmpeg child.
        @file_put_contents($outputDir . '/.stop', '1');

        // Signal the whole process group (sent setsid leader + ffmpeg child).
        // pgid > 1 guards against signalling PID 1 / an unrelated group.
        if ($pgid > 1) {
            @exec('kill -TERM -' . (int) $pgid . ' 2>/dev/null');
            usleep(300000);
            @exec('kill -KILL -' . (int) $pgid . ' 2>/dev/null');
        }
        // Belt-and-braces: kill the ffmpeg pid directly too.
        @exec('kill -KILL ' . (int) $pid . ' 2>/dev/null');

        Log::info('Purged ffmpeg ingest', [
            'channel_id' => $channelId,
            'pid' => $pid,
            'pgid' => $pgid,
            'reason' => $reason,
        ]);

        $this->line(sprintf('  pruned %-9s ch%s pid=%d pgid=%d', $reason, $channelId, $pid, $pgid));
    }

    /**
     * Stop group readers whose multicast source no longer has any active channel.
     * Groups with active members are left entirely untouched.
     *
     * @return array{orphaned: int, protected: int}
     */
    private function cleanupOrphanedGroups(MulticastIngestService $multicast, bool $dryRun): array
    {
        $groups = $multicast->getChannelGroups();
        $orphaned = 0;
        $protected = 0;

        foreach (glob(storage_path('app/multicast/*.pid')) ?: [] as $pidFile) {
            $base = basename($pidFile);
            // MulticastIngestService pids are <groupId>_<bucket>.pid — parse the
            // channel group's source by rebuilding it from the pid file name's md5.
            if (preg_match('/^([0-9a-f]{32})_\d+\.pid$/', $base, $m) !== 1) {
                continue;
            }

            $sourceUrl = $this->findSourceByGroupHash($m[1], $groups);
            if ($sourceUrl !== null) {
                // Source still has active channels → protected.
                $protected++;
                continue;
            }

            $orphaned++;
            $this->line(sprintf('  %s orphaned group reader %s', $dryRun ? '[dry-run]' : 'stopping', $base));

            if (! $dryRun) {
                $this->killGroupPidFile($pidFile);
            }
        }

        if ($orphaned > 0 && ! $dryRun) {
            Log::info('Purged orphaned multicast group readers', ['count' => $orphaned]);
        }

        return ['orphaned' => $orphaned, 'protected' => $protected];
    }

    private function findSourceByGroupHash(string $hash, array $groups): ?string
    {
        foreach ($groups as $sourceUrl => $_channels) {
            if (md5($sourceUrl) === $hash) {
                return $sourceUrl;
            }
        }
        return null;
    }

    private function killGroupPidFile(string $pidFile): void
    {
        $pid = (int) trim((string) @file_get_contents($pidFile));
        if ($pid > 0) {
            @exec('kill -TERM -' . $pid . ' 2>/dev/null');
            usleep(300000);
            @exec('kill -KILL -' . $pid . ' 2>/dev/null');
        }
        @unlink($pidFile);
    }

    /**
     * Snapshot running processes as [pid => ['pgid' => int, 'cmd' => string]].
     * Uses `ps` (Linux). Returns [] if ps is unavailable or produces no rows.
     */
    private function listProcesses(): array
    {
        $out = [];
        exec("ps -eo pid=,pgid=,args= 2>/dev/null", $lines, $exit);
        if ($exit !== 0) {
            return [];
        }

        foreach ($lines as $line) {
            if (! preg_match('/^\s*(\d+)\s+(\d+)\s+(.*)$/', $line, $m)) {
                continue;
            }
            $out[(int) $m[1]] = [
                'pgid' => (int) $m[2],
                'cmd'  => $m[3],
            ];
        }

        return $out;
    }
}

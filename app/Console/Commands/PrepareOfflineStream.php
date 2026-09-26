<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * Build a permanent 24/7 offline "channel is down" HLS loop.
 *
 * Design (near-zero steady-state CPU, seamless infinite play):
 * 1. ONCE  — encode the offline card to keyframe-aligned HLS segments
 *    (independent_segments, forced GOP). Never re-encode while looping.
 * 2. FOREVER — a tiny shell rotator rewrites playlist.m3u8 every second,
 *    cycling the same segment files with a rolling EXT-X-MEDIA-SEQUENCE.
 *    Players keep advancing forever with no ffmpeg running after prep.
 */
class PrepareOfflineStream extends Command
{
    protected $signature   = 'streams:prepare-offline {--force-rebuild : Re-encode segments even if they already exist}';
    protected $description = 'Build a 24/7 looping offline HLS fallback (one-time encode + rolling playlist, no continuous re-encode)';

    /** Segment duration for the one-shot encode (seconds). */
    private const SEGMENT_TIME = 2;

    /** How many segments the rotator advertises in the live window. */
    private const WINDOW = 6;

    public function handle(): int
    {
        $videoPath = (string) config('streaming.offline.video_path');
        $hlsDir    = (string) config('streaming.offline.hls_dir');

        if (! is_file($videoPath)) {
            $this->error("Offline video not found: {$videoPath}");
            $this->line('Upload your video there and re-run this command.');

            return self::FAILURE;
        }

        if (! is_dir($hlsDir) && ! mkdir($hlsDir, 0755, true) && ! is_dir($hlsDir)) {
            $this->error("Unable to create HLS directory: {$hlsDir}");

            return self::FAILURE;
        }

        $this->stopExistingLoop($hlsDir);

        $segments = $this->listSegments($hlsDir);
        $needBuild = $this->option('force-rebuild')
            || empty($segments)
            || ! is_file("{$hlsDir}/.source.sha1")
            || trim((string) @file_get_contents("{$hlsDir}/.source.sha1")) !== $this->sourceHash($videoPath);

        if ($needBuild) {
            $this->line('Encoding offline card to keyframe-aligned segments (one-time)…');
            $ok = $this->encodeOnce($videoPath, $hlsDir);
            if (! $ok) {
                $this->error('One-time encode failed.');

                return self::FAILURE;
            }
            @file_put_contents("{$hlsDir}/.source.sha1", $this->sourceHash($videoPath));
            $segments = $this->listSegments($hlsDir);
        } else {
            $this->line('Segments already up to date — restarting rolling playlist only.');
        }

        if (empty($segments)) {
            $this->error('No segments produced.');

            return self::FAILURE;
        }

        // Drop any stale live playlist left by an old ffmpeg loop.
        @unlink("{$hlsDir}/playlist.m3u8");

        $scriptPath = "{$hlsDir}/offline-loop.sh";
        file_put_contents($scriptPath, $this->buildRotatorScript($hlsDir, $segments));
        @chmod($scriptPath, 0755);

        $this->launchSupervisor($scriptPath, $hlsDir);

        $this->info('Offline HLS rolling playlist started: '.$hlsDir);
        $this->line('  '.count($segments).' segments, window '.self::WINDOW.', seg_time '.self::SEGMENT_TIME.'s');
        $this->line('  Supervised 24/7 by streams:ensure-offline (every minute).');

        return self::SUCCESS;
    }

    private function encodeOnce(string $videoPath, string $hlsDir): bool
    {
        $ffmpeg = (string) config('streaming.transcoding.ffmpeg_path', 'ffmpeg');

        // Remove old segments so numbering is clean.
        array_map('unlink', glob("{$hlsDir}/seg_*.ts") ?: []);
        array_map('unlink', glob("{$hlsDir}/seg*.ts") ?: []);
        @unlink("{$hlsDir}/playlist_vod.m3u8");

        $gop = self::SEGMENT_TIME * 25; // assume 25fps; force_key_frames overrides actual split points

        $cmd = sprintf(
            '%s -y -hide_banner -loglevel error -i %s ' .
            '-c:v libx264 -preset veryfast -crf 23 -g %d -keyint_min %d -sc_threshold 0 ' .
            '-force_key_frames "expr:gte(t,n_forced*%d)" ' .
            '-c:a aac -b:a 128k -ac 2 -ar 48000 ' .
            '-f hls -hls_time %d -hls_list_size 0 ' .
            '-hls_flags independent_segments ' .
            '-hls_segment_filename %s %s',
            escapeshellcmd($ffmpeg),
            escapeshellarg($videoPath),
            $gop,
            $gop,
            self::SEGMENT_TIME,
            self::SEGMENT_TIME,
            escapeshellarg("{$hlsDir}/seg_%03d.ts"),
            escapeshellarg("{$hlsDir}/playlist_vod.m3u8")
        );

        exec($cmd.' 2>&1', $out, $rc);

        return $rc === 0 && ! empty($this->listSegments($hlsDir));
    }

    /**
     * @return list<string> basenames like seg_000.ts
     */
    private function listSegments(string $hlsDir): array
    {
        $files = glob("{$hlsDir}/seg_*.ts") ?: [];
        $base  = array_map('basename', $files);
        natcasesort($base);

        return array_values($base);
    }

    private function sourceHash(string $videoPath): string
    {
        return (string) sha1_file($videoPath);
    }

    /**
     * Rolling live playlist: same segment files, MEDIA-SEQUENCE advances once
     * per segment duration (matches 2s segments — not every wall-clock second).
     * EXT-X-DISCONTINUITY before every segment so ExoPlayer survives PTS
     * reset on loop wrap. No ffmpeg after the one-time encode.
     */
    private function buildRotatorScript(string $hlsDir, array $segments): string
    {
        $segList = implode('\n', array_map(
            static fn (string $s): string => $s,
            $segments
        ));
        // Escape for embedding in single-quoted shell string
        $segListShell = implode('\n', $segments);
        $count        = count($segments);
        $window       = min(self::WINDOW, $count);
        $targetDur    = self::SEGMENT_TIME;

        return <<<BASH
#!/bin/sh
# Offline 24/7 rolling playlist — regenerate via streams:prepare-offline.
set -u
DIR={$this->shQuote($hlsDir)}
PL="\$DIR/playlist.m3u8"
STAMP="\$DIR/.offline-loop.pid"
COUNT={$count}
WINDOW={$window}
TARGET={$targetDur}

# Segment basenames (one per line), natural order.
SEGS='
{$segListShell}
'

echo \$\$ > "\$STAMP"
SEQ=0

# Seed sequence from an existing playlist so restarts do not rewind players.
if [ -f "\$PL" ]; then
    OLD=\$(sed -n 's/^#EXT-X-MEDIA-SEQUENCE://p' "\$PL" | head -1)
    if [ -n "\$OLD" ] && [ "\$OLD" -eq "\$OLD" ] 2>/dev/null; then
        SEQ=\$OLD
    fi
fi

while true; do
    {
        printf '%s\n' '#EXTM3U'
        printf '%s\n' '#EXT-X-VERSION:3'
        printf '#EXT-X-MEDIA-SEQUENCE:%s\n' "\$SEQ"
        printf '%s\n' '#EXT-X-ALLOW-CACHE:NO'
        printf '#EXT-X-TARGETDURATION:%s\n' "\$TARGET"
        printf '%s\n' '#EXT-X-INDEPENDENT-SEGMENTS'
        i=0
        while [ "\$i" -lt "\$WINDOW" ]; do
            idx=\$(( (SEQ + i) % COUNT ))
            # zero-pad to 3 digits to match seg_NNN.ts
            name=\$(printf 'seg_%03d.ts' "\$idx")
            # PTS restarts at 0 on every loop wrap (seg_N → seg_000). ExoPlayer
            # freezes/buffers without a discontinuity tag before that segment.
            if [ "\$i" -eq 0 ] || [ "\$idx" -eq 0 ]; then
                printf '%s\n' '#EXT-X-DISCONTINUITY'
            fi
            printf '#EXTINF:%s.0,\n' "\$TARGET"
            printf '%s\n' "\$name"
            i=\$((i + 1))
        done
    } > "\$PL.tmp" && mv -f "\$PL.tmp" "\$PL"
    SEQ=\$((SEQ + 1))
    sleep {$targetDur}
done
BASH;
    }

    private function launchSupervisor(string $scriptPath, string $hlsDir): void
    {
        $log   = "{$hlsDir}/offline-loop.log";
        $stamp = "{$hlsDir}/.offline-loop.pid";
        @unlink($stamp);

        // nohup + closed stdin/stdout so SSH/artisan never waits on the child.
        // PID is written by the script itself (echo $$ > stamp) — do NOT write
        // $! here: with setsid that PID is a short-lived intermediate and
        // EnsureOfflineStream then thinks the rotator is dead every minute.
        $cmd = sprintf(
            'nohup %s >> %s 2>&1 < /dev/null & echo $!',
            escapeshellarg($scriptPath),
            escapeshellarg($log)
        );

        $output = [];
        @exec($cmd, $output);

        // Wait briefly for the script to write its real PID.
        for ($i = 0; $i < 30; $i++) {
            if (is_file($stamp) && (int) @file_get_contents($stamp) > 1) {
                break;
            }
            usleep(50000);
        }
    }

    private function stopExistingLoop(string $hlsDir): void
    {
        // Kill every rotator/ffmpeg for this dir — parallel ensure/prepare runs
        // previously left 3–4 writers racing on playlist.m3u8.
        $escaped = preg_quote($hlsDir, '/');
        $awk     = sprintf(
            'ps -eo pid=,args= 2>/dev/null | awk \'$0 ~ /%s/ && ($0 ~ /ffmpeg/ || $0 ~ /offline-loop/) {print $1}\'',
            $escaped
        );
        exec($awk, $pids);
        // Also match by script basename alone (covers short-lived shells).
        exec('pgrep -f offline-loop.sh 2>/dev/null', $more);
        $pids = array_unique(array_merge(array_map('intval', $pids), array_map('intval', $more)));
        foreach ($pids as $pid) {
            if ($pid > 1 && $pid !== getmypid()) {
                @exec('kill -TERM ' . $pid . ' 2>/dev/null');
            }
        }
        usleep(300000);
        foreach ($pids as $pid) {
            if ($pid > 1 && $pid !== getmypid()) {
                @exec('kill -KILL ' . $pid . ' 2>/dev/null');
            }
        }
        // Wait until nothing is left so the new rotator is the only writer.
        for ($i = 0; $i < 20; $i++) {
            exec('pgrep -f offline-loop.sh 2>/dev/null', $left);
            $left = array_filter(array_map('intval', $left), static fn ($p) => $p !== getmypid());
            if (empty($left)) {
                break;
            }
            usleep(100000);
        }
    }

    private function shQuote(string $value): string
    {
        return escapeshellarg($value);
    }
}

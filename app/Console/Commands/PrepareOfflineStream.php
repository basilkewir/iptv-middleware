<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class PrepareOfflineStream extends Command
{
    protected $signature   = 'streams:prepare-offline';
    protected $description = 'Convert the offline video into a looping HLS stream served to clients when a channel is offline';

    public function handle(): int
    {
        $videoPath = config('streaming.offline.video_path');
        $hlsDir    = config('streaming.offline.hls_dir');

        if (! is_file($videoPath)) {
            $this->error("Offline video not found: {$videoPath}");
            $this->line('Upload your video there and re-run this command.');
            return self::FAILURE;
        }

        if (! is_dir($hlsDir)) {
            mkdir($hlsDir, 0755, true);
        }

        // Remove old segments so the playlist is always fresh.
        array_map('unlink', glob("{$hlsDir}/*.ts") ?: []);
        @unlink("{$hlsDir}/playlist.m3u8");

        $playlist = escapeshellarg("{$hlsDir}/playlist.m3u8");
        $segment  = escapeshellarg("{$hlsDir}/seg%03d.ts");
        $input    = escapeshellarg($videoPath);

        // -stream_loop -1  → loop the source indefinitely
        // -hls_list_size 0 → keep ALL segments in the playlist (VOD-style)
        //                    so players can always seek back to the start
        // -hls_flags independent_segments → each segment is self-contained
        $cmd = "ffmpeg -y -stream_loop -1 -i {$input} "
             . "-c:v libx264 -preset veryfast -crf 23 "
             . "-c:a aac -b:a 128k "
             . "-f hls -hls_time 6 -hls_list_size 10 "
             . "-hls_flags delete_segments+independent_segments "
             . "-hls_segment_filename {$segment} "
             . "{$playlist} "
             . "> /dev/null 2>&1 &";

        shell_exec($cmd);

        $this->info("Offline HLS stream is being generated in: {$hlsDir}");
        $this->line('It will be served automatically whenever a channel has no active ingest.');

        return self::SUCCESS;
    }
}

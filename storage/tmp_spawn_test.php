<?php

$o = '/home/kotelhms/middleware/storage/app/streams/hls/23';
$s = 'http://37.49.224.214:2052/donzebehd/donzebehd/401.m3u8';

$wrap = sprintf(
    'ODIR=%s; while true; do [ -f "$ODIR/.stop" ] && exit 0; '
    . 'rm -f "$ODIR"/segment_*.ts "$ODIR"/playlist.m3u8; '
    . 'ffmpeg -reconnect 1 -reconnect_streamed 1 -reconnect_delay_max 5 '
    . '-rw_timeout %d -timeout %d -re -i %s -c:v copy -c:a copy -f hls '
    . '-hls_time 6 -hls_list_size 10 -hls_flags delete_segments+temp_file '
    . '-hls_segment_filename "$ODIR"/segment_%%03d.ts '
    . '"$ODIR"/playlist.m3u8 2>/dev/null; sleep 3; done',
    escapeshellarg($o),
    30000000,
    30000000,
    escapeshellarg($s)
);

$cmd = 'setsid bash -c ' . escapeshellarg($wrap) . ' < /dev/null > /dev/null 2>&1 & echo $!';

echo "CMD: [$cmd]\n";
$pid = trim((string) shell_exec($cmd));
echo "PID=[$pid]\n";
file_put_contents($o . '/ingest.pid', $pid);

<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Process driver
    |--------------------------------------------------------------------------
    | systemd  — a template unit (iptv-playout@<slug>) owns the supervisor, so
    |            CPUQuota/MemoryMax/Nice are enforced by cgroups.
    | setsid   — the legacy plain `setsid nohup` launch (Docker / dev boxes).
    | auto     — systemd when /usr/local/sbin/iptv-playout-ctl exists.
    */
    'driver' => env('PLAYOUT_DRIVER', 'auto'),

    'systemd_ctl' => env('PLAYOUT_SYSTEMD_CTL', '/usr/local/sbin/iptv-playout-ctl'),

    /*
    |--------------------------------------------------------------------------
    | HLS output
    |--------------------------------------------------------------------------
    | These were previously hard-coded inside MyChannelHlsService.
    */
    'segment_duration' => (int) env('PLAYOUT_SEGMENT_DURATION', 2),
    'playlist_size' => (int) env('PLAYOUT_PLAYLIST_SIZE', 18),

    /*
    |--------------------------------------------------------------------------
    | CPU / IO budget
    |--------------------------------------------------------------------------
    | threads is capped at nproc/4 so a single channel can never monopolise
    | the box that also runs Flussonic.
    */
    'threads' => (int) env('PLAYOUT_THREADS', 2),
    'nice' => (int) env('PLAYOUT_NICE', 10),

    // Only used when driver=systemd (mirrored into deploy/iptv-playout@.service).
    'cpu_quota' => env('PLAYOUT_CPU_QUOTA', '400%'),
    'memory_max' => env('PLAYOUT_MEMORY_MAX', '2G'),

    // Load average (1 min) above which a *new* broadcast refuses to start.
    'load_gate' => (float) env('PLAYOUT_LOAD_GATE', 40),

    /*
    |--------------------------------------------------------------------------
    | Overlay canvas
    |--------------------------------------------------------------------------
    | The logo + watermark are baked into one full-frame RGBA PNG that Stage 2
    | re-reads, so image / position / size / opacity / enable changes apply
    | with no restart at all.
    |
    |   png    — image2 with `-loop 1`. The demuxer re-opens overlay.png by
    |            path for every packet it produces, so rewriting the file is
    |            picked up live (default; verified against img2dec.c).
    |   static — `-i overlay.png` with no loop: decoded once and held for the
    |            life of Stage 2. Overlay edits then need an encoder restart
    |            (Stage 1 still never restarts).
    */
    'canvas_mode' => env('PLAYOUT_CANVAS_MODE', 'png'),

    // Frames per second at which the canvas input is refreshed (1 = ~1s latency).
    'canvas_fps' => max(1, (int) env('PLAYOUT_CANVAS_FPS', 2)),
];

<?php

namespace Tests\Unit;

use App\Models\AdminChannel\AdminChannel;
use App\Models\AdminChannel\MyChannelContent;
use App\Models\AdminChannel\MyChannelPlaylist;
use App\Models\User;
use App\Jobs\PrepareMyChannelContent;
use App\Jobs\RefreshMyChannelPlayout;
use App\Services\AdminChannel\MyChannelHlsService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use ReflectionMethod;
use Tests\TestCase;

class MyChannelHlsServiceTest extends TestCase
{
    use RefreshDatabase;
    /** @var list<string> files (and dirs) created on the real filesystem by a test */
    private array $tempFiles = [];

    /** @var list<int> child processes spawned by a test */
    private array $tempPids = [];

    protected function tearDown(): void
    {
        foreach ($this->tempPids as $pid) {
            exec('kill ' . $pid . ' 2>/dev/null');
        }
        $this->tempPids = [];

        foreach ($this->tempFiles as $file) {
            if (is_file($file)) {
                @unlink($file);
            }
        }
        $this->tempFiles = [];

        // Second pass: tearDown leftover dirs (order-independent).
        exec('find ' . escapeshellarg(storage_path('app/streams')) . ' -type d -empty -delete 2>/dev/null');

        parent::tearDown();
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function service(): MyChannelHlsService
    {
        return new MyChannelHlsService();
    }

    private function invoke(string $method, ...$args): mixed
    {
        $ref = new ReflectionMethod(MyChannelHlsService::class, $method);
        $ref->setAccessible(true);

        return $ref->invoke($this->service(), ...$args);
    }

    /**
     * @return array{0: string, 1: string} [extra -i lines, filter_complex]
     */
    private function graph(AdminChannel $channel, string $canvasMode = 'png', ?string $overlayEnable = null): array
    {
        return $this->invoke(
            'buildFiltergraph',
            '/tmp/stream-dir',
            $channel,
            1280,
            720,
            25,
            $canvasMode,
            $overlayEnable
        );
    }

    /**
     * @return array{0: string, 1: string} [extra -i lines, filter_complex]
     */
    private function canvas(AdminChannel $channel): array
    {
        return $this->invoke('buildCanvasGraph', $channel, 1280, 720);
    }

    private function plainChannel(array $overrides = []): AdminChannel
    {
        return new AdminChannel(array_merge([
            'channel_slug'         => 'test-channel',
            'enable_overlay_logo'  => false,
            'enable_overlay_clock' => false,
            'enable_watermark'     => false,
            'enable_ticker'        => false,
        ], $overrides));
    }

    private function logoChannel(array $overrides = []): AdminChannel
    {
        return $this->plainChannel(array_merge([
            'enable_overlay_logo'   => true,
            'logo_url'              => $this->tempFile('logo.png'),
            'overlay_logo_position' => 'top-left',
            'overlay_logo_x'        => 10,
            'overlay_logo_y'        => 20,
            'overlay_logo_size'     => 100,
            'overlay_logo_opacity'  => 1,
        ], $overrides));
    }

    private function clockChannel(array $overrides = []): AdminChannel
    {
        return $this->plainChannel(array_merge([
            'enable_overlay_clock'   => true,
            'overlay_clock_position' => 'top-left',
            'overlay_clock_x'        => 50,
            'overlay_clock_y'        => 5,
            'overlay_clock_format'   => 'HH:MM:SS',
        ], $overrides));
    }

    private function tempFile(string $name): string
    {
        $path = sys_get_temp_dir() . '/mchls_' . $name;
        file_put_contents($path, 'stub');
        $this->tempFiles[] = $path;

        return $path;
    }

    // ── Stage 2 graph ────────────────────────────────────────────────────────

    public function test_normalisation_runs_at_the_head_of_the_graph(): void
    {
        [, $vf] = $this->graph($this->plainChannel());

        // Nothing may touch timestamps before the timeline is rebuilt: an
        // upstream PTS jump must never reach the ticker, the clock or x264.
        $this->assertStringStartsWith('[0:v]fps=25,setpts=N/(25*TB)[vnorm]', $vf);
    }

    public function test_canvas_input_is_always_present_for_live_overlay_control(): void
    {
        [$inputs, $vf] = $this->graph($this->plainChannel());

        // Even with every overlay disabled the canvas stays wired in, so
        // enabling a logo does not change Stage 2's filtergraph.
        $this->assertStringContainsString('-f image2 -loop 1 -framerate', $inputs);
        $this->assertStringContainsString('[vnorm][2:v]overlay=x=0:y=0[vcanvas]', $vf);
    }

    public function test_static_canvas_mode_reads_the_canvas_once(): void
    {
        [$inputs] = $this->graph($this->plainChannel(), 'static');

        $this->assertStringContainsString('overlay.png', $inputs);
        $this->assertStringNotContainsString('-loop', $inputs);
    }

    public function test_silence_bed_is_always_input_one(): void
    {
        [, $vf] = $this->graph($this->plainChannel());

        $this->assertStringContainsString('[0:a][1:a]amix=inputs=2', $vf);
    }

    public function test_ticker_and_clock_are_omitted_when_disabled(): void
    {
        [, $vf] = $this->graph($this->plainChannel());

        $this->assertStringNotContainsString('drawtext', $vf);
        $this->assertStringNotContainsString('drawbox', $vf);
        // ...but the canvas overlay is still there.
        $this->assertStringContainsString('overlay=', $vf);
    }

    public function test_clock_position_is_pixel_precise_from_percentages(): void
    {
        [, $vf] = $this->graph($this->clockChannel());

        // x=50% → 640, y=5% → 36
        $this->assertStringContainsString('x=640:y=36', $vf);
    }

    public function test_clock_falls_back_to_preset_corner_when_xy_missing(): void
    {
        [, $vf] = $this->graph($this->clockChannel([
            'overlay_clock_x'        => null,
            'overlay_clock_y'        => null,
            'overlay_clock_position' => 'bottom-right',
        ]));

        // fontsize = max(14, round(720*0.03)) = 22 → elemW=198, elemH=38
        // x = 1280-198-10 = 1072, y = 720-38-10 = 672
        $this->assertStringContainsString('x=1072:y=672', $vf);
    }

    // ── Canvas composition (logo / watermark) ────────────────────────────────

    public function test_logo_and_watermark_live_on_the_canvas_not_in_the_encoder_graph(): void
    {
        [, $encoderVf] = $this->graph($this->logoChannel());
        [, $canvasVf]  = $this->canvas($this->logoChannel());

        // The encoder graph only ever overlays the one full-frame canvas.
        $this->assertStringNotContainsString('colorchannelmixer', $encoderVf);
        $this->assertStringNotContainsString('lanczos', $encoderVf);

        // The canvas itself carries the sprite scaling and opacity.
        $this->assertStringContainsString('scale=192:-1:flags=lanczos', $canvasVf);
        $this->assertStringContainsString('colorchannelmixer=aa=1.00', $canvasVf);
    }

    public function test_logo_position_is_pixel_precise_from_percentages(): void
    {
        [, $vf] = $this->canvas($this->logoChannel());

        // width=1280, size=100 → logoW = round(1280 * 1.0 * 0.15) = 192
        $this->assertStringContainsString('scale=192:-1:flags=lanczos', $vf);
        // x=10% → 128, y=20% → 144
        $this->assertStringContainsString('overlay=128:144', $vf);
    }

    public function test_logo_position_rounds_decimal_percentages_precisely(): void
    {
        [, $vf] = $this->canvas($this->logoChannel([
            'overlay_logo_x' => '12.5',
            'overlay_logo_y' => '33.3',
        ]));

        // 1280 * 0.125 = 160 ; 720 * 0.333 = 239.76 → 240
        $this->assertStringContainsString('overlay=160:240', $vf);
    }

    public function test_logo_falls_back_to_preset_corner_when_xy_missing(): void
    {
        [, $vf] = $this->canvas($this->logoChannel([
            'overlay_logo_x'        => null,
            'overlay_logo_y'        => null,
            'overlay_logo_position' => 'top-right',
        ]));

        // logoW=192, elemH=96 → x = 1280-192-10 = 1078, y = 10
        $this->assertStringContainsString('overlay=1078:10', $vf);
    }

    public function test_logo_bottom_right_preset_stays_inside_frame(): void
    {
        [, $vf] = $this->canvas($this->logoChannel([
            'overlay_logo_x'        => null,
            'overlay_logo_y'        => null,
            'overlay_logo_position' => 'bottom-right',
        ]));

        // x = 1280-192-10 = 1078, y = 720-96-10 = 614
        $this->assertStringContainsString('overlay=1078:614', $vf);
    }

    public function test_canvas_is_a_transparent_full_frame_rgba_layer(): void
    {
        [, $vf] = $this->canvas($this->plainChannel());

        $this->assertStringEndsWith('format=rgba[cout]', $vf);
        $this->assertStringStartsWith('[0:v]format=rgba[cout]', $vf);
    }

    public function test_canvas_base_source_keeps_its_alpha_plane(): void
    {
        // Without format=rgba the colour source negotiates yuv420p, drops the
        // @0.0 opacity and the finished canvas is opaque black — stage 2 then
        // overlays a black rectangle over the whole picture.
        $this->assertStringEndsWith(',format=rgba', $this->invoke('canvasBaseSource', 1280, 720));
    }

    public function test_jingle_schedule_hides_every_overlay_for_its_playlist_interval(): void
    {
        Storage::fake('public');

        $channel = $this->persistChannel('jingle-overlay-gate');
        $program = $this->persistContent($channel, 1, 'Program');
        $jingle = $this->persistContent($channel, 2, 'Jingle');
        $program->update(['duration' => 8]);
        $jingle->update(['duration' => 5]);
        MyChannelPlaylist::where('channel_id', $channel->id)
            ->where('content_id', $jingle->id)
            ->update(['category' => 'jingle']);

        $expression = $this->invoke(
            'overlayEnableExpression',
            $channel,
            [
                $this->preparedFile($channel->channel_slug, $program->id),
                $this->preparedFile($channel->channel_slug, $jingle->id),
            ],
            1234
        );

        $this->assertSame(
            'not(between(mod(time(0)-1234\\,13)\\,8\\,13))',
            $expression
        );

        $channel->enable_ticker = true;
        $channel->enable_overlay_clock = true;
        $channel->ticker_background = '#000000';
        [, $filtergraph] = $this->graph($channel, 'png', $expression);

        $this->assertSame(4, substr_count($filtergraph, ":enable='"));
        $this->assertStringContainsString("overlay=x=0:y=0:enable='{$expression}'", $filtergraph);
    }

    // ── Overlay update contract ──────────────────────────────────────────────

    public function test_ticker_text_change_needs_no_restart(): void
    {
        $this->assertFalse(
            $this->service()->encoderRestartRequired($this->plainChannel(), ['ticker_text' => 'Breaking news'])
        );
    }

    public function test_graph_fixed_fields_need_an_encoder_restart(): void
    {
        foreach (['ticker_color', 'ticker_speed', 'enable_ticker', 'enable_overlay_clock', 'overlay_clock_format'] as $field) {
            $this->assertTrue(
                $this->service()->encoderRestartRequired($this->plainChannel(), [$field => 'x']),
                "Expected {$field} to require an encoder restart"
            );
        }
    }

    public function test_image_change_restarts_stage2_in_the_default_canvas_mode(): void
    {
        config(['playout.canvas_mode' => 'png']);

        $this->assertTrue($this->service()->encoderRestartRequired(
            $this->plainChannel(),
            ['logo_url' => '/new/logo.png', 'overlay_logo_size' => 250, 'enable_watermark' => true]
        ));
    }

    public function test_image_change_restarts_stage2_in_static_canvas_mode(): void
    {
        config(['playout.canvas_mode' => 'static']);

        $this->assertTrue($this->service()->encoderRestartRequired(
            $this->plainChannel(),
            ['logo_url' => '/new/logo.png']
        ));

        // ...but a ticker text edit is still free even in static mode.
        $this->assertFalse($this->service()->encoderRestartRequired(
            $this->plainChannel(),
            ['ticker_text' => 'hello']
        ));
    }

    // ── Prepared-only playout ────────────────────────────────────────────────

    public function test_playout_only_consumes_prepared_intermediates(): void
    {
        $slug     = 'prepared-only-test';
        $channel  = new AdminChannel(['channel_slug' => $slug]);
        $prepared = $this->preparedFile($slug, 11);

        $playlist = new Collection([$this->content(11, $slug, 'has a prepared file')]);
        $files    = $this->invoke('collectFiles', $playlist, $channel);

        $this->assertSame([$prepared], $files);
    }

    public function test_content_without_a_prepared_intermediate_is_excluded(): void
    {
        $slug    = 'prepared-only-test';
        $channel = new AdminChannel(['channel_slug' => $slug]);

        // Source exists on disk, but was never normalised — Stage 1 would
        // `-c copy` it straight into the mux, reintroducing mixed geometry.
        $playlist = new Collection([$this->content(12, $slug, 'never prepared')]);
        $files    = $this->invoke('collectFiles', $playlist, $channel);

        $this->assertSame([], $files);
    }

    public function test_content_with_a_missing_source_is_excluded(): void
    {
        $slug    = 'prepared-only-test';
        $channel = new AdminChannel(['channel_slug' => $slug]);

        $content = new MyChannelContent([
            'title'     => 'vanished',
            'file_path' => 'mychannel/does-not-exist.mp4',
        ]);
        $content->id = 13;

        $files = $this->invoke('collectFiles', new Collection([$content]), $channel);

        $this->assertSame([], $files);
    }

    public function test_prepared_content_is_kept_when_the_original_upload_is_missing(): void
    {
        Storage::fake('public');

        $slug = 'prepared-source-missing';
        $channel = new AdminChannel(['channel_slug' => $slug]);
        $content = $this->content(14, $slug, 'already prepared');
        @unlink(Storage::disk('public')->path($content->file_path));
        $prepared = $this->preparedFile($slug, $content->id);

        $this->assertSame(
            [$prepared],
            $this->invoke('collectFiles', new Collection([$content]), $channel)
        );
    }

    public function test_probe_duration_keeps_positive_subsecond_media_in_the_playlist(): void
    {
        $fakeProbe = sys_get_temp_dir() . '/mchls_ffprobe_' . uniqid();
        file_put_contents($fakeProbe, "#!/bin/sh\nprintf '0.4\\n'\n");
        chmod($fakeProbe, 0755);
        $this->tempFiles[] = $fakeProbe;

        $previousProbe = config('streaming.transcoding.ffprobe_path');
        config(['streaming.transcoding.ffprobe_path' => $fakeProbe]);

        try {
            $this->assertSame(1, $this->invoke('probeDuration', '/unused'));
        } finally {
            config(['streaming.transcoding.ffprobe_path' => $previousProbe]);
        }
    }

    public function test_one_second_video_can_be_prepared_without_an_audio_track(): void
    {
        exec('command -v ffmpeg 2>/dev/null', $ffmpegPath, $ffmpegStatus);
        exec('command -v ffprobe 2>/dev/null', $ffprobePath, $ffprobeStatus);
        if ($ffmpegStatus !== 0 || $ffprobeStatus !== 0) {
            $this->markTestSkipped('requires ffmpeg and ffprobe');
        }

        $source = sys_get_temp_dir() . '/mchls_short_' . uniqid() . '.mp4';
        $this->tempFiles[] = $source;
        exec(sprintf(
            '%s -y -hide_banner -loglevel error -f lavfi -i color=c=black:s=64x64:r=1:d=1 -t 1 -c:v libx264 -pix_fmt yuv420p %s 2>&1',
            escapeshellarg(trim($ffmpegPath[0])),
            escapeshellarg($source)
        ), $out, $rc);
        $this->assertSame(0, $rc, implode("\n", $out));

        $previousFfmpeg = config('streaming.transcoding.ffmpeg_path');
        $previousFfprobe = config('streaming.transcoding.ffprobe_path');
        config([
            'streaming.transcoding.ffmpeg_path' => trim($ffmpegPath[0]),
            'streaming.transcoding.ffprobe_path' => trim($ffprobePath[0]),
        ]);

        $channel = new AdminChannel([
            'channel_slug' => 'one-second-prepare-test',
            'output_resolution' => '1280x720',
            'output_frame_rate' => 25,
            'output_bitrate' => 2200,
            'transcoding_device' => 'cpu',
        ]);
        $prepared = storage_path('app/streams/normalized/one-second-prepare-test/prepared_short.mp4');
        $this->tempFiles[] = $prepared;
        $this->tempFiles[] = $prepared . '.sig.json';

        try {
            $this->assertSame(
                $prepared,
                $this->service()->prepareFile($channel, 'short', $source)
            );
            $this->assertFileExists($prepared);
        } finally {
            config([
                'streaming.transcoding.ffmpeg_path' => $previousFfmpeg,
                'streaming.transcoding.ffprobe_path' => $previousFfprobe,
            ]);
        }
    }

    // ── Generated shell ──────────────────────────────────────────────────────

    public function test_generated_playout_scripts_are_valid_bash(): void
    {
        $dir = sys_get_temp_dir() . '/mchls_playout_' . uniqid();
        mkdir($dir, 0775, true);

        $script = $this->invoke(
            'writePlayoutScript',
            $dir,
            ['/tmp/one.mp4', '/tmp/two.mp4'],
            $this->plainChannel(),
            'png'
        );

        foreach ([$script, "{$dir}/stage2.sh"] as $file) {
            $this->assertFileExists($file);
            exec('bash -n ' . escapeshellarg($file) . ' 2>&1', $out, $rc);
            $this->assertSame(0, $rc, implode("\n", $out));
        }

        @unlink($script);
        @unlink("{$dir}/stage2.sh");
        @unlink("{$dir}/concat.txt");
        @rmdir($dir);
    }

    public function test_concat_list_is_written_once_not_repeated_hundreds_of_times(): void
    {
        $dir = sys_get_temp_dir() . '/mchls_playout_' . uniqid();
        mkdir($dir, 0775, true);

        $this->invoke(
            'writePlayoutScript',
            $dir,
            ['/tmp/one.mp4', '/tmp/two.mp4'],
            $this->plainChannel(),
            'png'
        );

        $concat = (string) file_get_contents("{$dir}/concat.txt");

        $this->assertSame(2, substr_count($concat, 'file '));
        $this->assertStringContainsString("file '/tmp/one.mp4'", $concat);

        @unlink("{$dir}/playout.sh");
        @unlink("{$dir}/stage2.sh");
        @unlink("{$dir}/concat.txt");
        @rmdir($dir);
    }

    public function test_playlist_rotation_keeps_every_item_after_the_selected_item(): void
    {
        $playlist = new Collection(array_map(
            fn ($id) => tap(new MyChannelContent(), fn ($content) => $content->id = $id),
            [1, 2, 3, 4, 5]
        ));

        $rotated = $this->invoke('rotateToContent', $playlist, 3);

        $this->assertSame([3, 4, 5, 1, 2], $rotated->map(fn ($content) => $content->id)->all());
    }

    // ── Live playlist refresh ────────────────────────────────────────────────

    public function test_playout_script_wires_the_stage1_reload_signal(): void
    {
        $dir = sys_get_temp_dir() . '/mchls_reload_' . uniqid();
        mkdir($dir, 0775, true);

        $script = $this->invoke(
            'writePlayoutScript', $dir, ['/tmp/one.mp4'], $this->plainChannel(), 'png'
        );

        $bash = (string) file_get_contents($script);

        // The concat demuxer only reads its list at open, so a playlist edit
        // reaching a live channel depends on this flag being watched and on
        // the watched PID being the ffmpeg itself (not the supervision loop).
        $this->assertStringContainsString('.reload-stage1', $bash);
        $this->assertStringContainsString('STAGE1 reload signal', $bash);
        $this->assertStringContainsString('stage1.ffmpeg.pid', $bash);
        $this->assertStringContainsString('.reload-stage2', $bash);
        $this->assertStringContainsString('.stage2-ready', $bash);
        $this->assertStringContainsString('STAGE2 reload signal', $bash);

        @unlink($script);
        @unlink("{$dir}/stage2.sh");
        @unlink("{$dir}/concat.txt");
        @rmdir($dir);
    }

    public function test_refreshPlaylist_is_a_noop_when_the_channel_is_not_running(): void
    {
        $channel = new AdminChannel([
            'channel_slug'  => 'refresh-idle-test',
            'is_my_channel' => true,
        ]);
        $channel->id = 999_001;

        // Nothing alive, no stream dir: the edit is picked up by the next
        // start(), and no process may be signalled here.
        $this->assertSame(
            ['changed' => false, 'files' => 0, 'excluded' => [], 'pending' => 0],
            $this->service()->refreshPlaylist($channel)
        );
    }

    public function test_refreshPlaylist_keeps_the_current_item_at_the_head_of_the_live_order(): void
    {
        Storage::fake('public');

        $channel = $this->persistChannel('refresh-current-item');
        $first = $this->persistContent($channel, 1, 'first');
        $current = $this->persistContent($channel, 2, 'current');
        $last = $this->persistContent($channel, 3, 'last');
        $firstPath = $this->preparedFile($channel->channel_slug, $first->id);
        $currentPath = $this->preparedFile($channel->channel_slug, $current->id);
        $lastPath = $this->preparedFile($channel->channel_slug, $last->id);

        $streamDir = storage_path("app/streams/hls/admin-channel-{$channel->channel_slug}");
        mkdir($streamDir, 0775, true);
        $concatPath = "{$streamDir}/concat.txt";
        file_put_contents($concatPath, implode("\n", array_map(
            fn ($path) => 'file ' . escapeshellarg($path),
            [$firstPath, $currentPath, $lastPath]
        )) . "\n");
        $this->tempFiles[] = $concatPath;
        $this->tempFiles[] = "{$streamDir}/.reload-stage1";
        $this->tempFiles[] = "{$streamDir}/stage2.sh";
        $this->tempFiles[] = "{$streamDir}/.overlay-schedule.sig";

        $hls = new class extends MyChannelHlsService {
            public int $currentContentId = 0;

            public function isRunning(AdminChannel $channel): bool
            {
                return true;
            }

            public function nowPlaying(AdminChannel $channel): ?array
            {
                return [
                    'content_id' => $this->currentContentId,
                    'index' => 1,
                    'item_elapsed' => 0,
                    'item_duration' => 60,
                ];
            }
        };
        $hls->currentContentId = $current->id;

        $result = $hls->refreshPlaylist($channel);

        $this->assertTrue($result['changed']);
        $this->assertSame(
            [$currentPath, $lastPath, $firstPath],
            array_map(
                fn ($line) => trim(substr($line, strlen('file ')), "'"),
                file($concatPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES)
            )
        );
    }

    public function test_failed_preparation_is_visible_and_does_not_block_later_items(): void
    {
        Storage::fake('public');
        Queue::fake();

        $channel = $this->persistChannel('prepare-failure-continues');
        $failed = $this->persistContent($channel, 1, 'unreadable');
        $next = $this->persistContent($channel, 2, 'playable next');

        (new PrepareMyChannelContent($failed->id))->failed(new \RuntimeException('decode error'));

        $hls = $this->service();
        $this->assertSame('decode error', $hls->preparationFailure($channel, $failed->id));
        Queue::assertPushed(RefreshMyChannelPlayout::class, fn ($job) => $job->channelId === $channel->id);

        $this->invoke('queueMissingPreparation', $channel);

        Queue::assertPushed(PrepareMyChannelContent::class, fn ($job) => $job->contentId === $next->id);
    }

    public function test_playlistDrifted_is_false_when_nothing_is_running(): void
    {
        $channel = new AdminChannel([
            'channel_slug'  => 'drift-idle-test',
            'is_my_channel' => true,
        ]);

        $this->assertFalse($this->service()->playlistDrifted($channel));
    }

    public function test_playlistDrifted_detects_a_stale_concat_list(): void
    {
        Storage::fake('public');

        $channel = $this->persistChannel('drift-stale');
        $one  = $this->persistContent($channel, 1, 'one');
        $two  = $this->persistContent($channel, 2, 'two');

        $slug = $channel->channel_slug;
        $p1 = $this->preparedFile($slug, $one->id);
        $p2 = $this->preparedFile($slug, $two->id);

        if (! $this->fakeRunning($channel, $slug)) {
            $this->markTestSkipped('needs Linux /proc to fake a live playout process');
        }

        $dir = storage_path("app/streams/hls/admin-channel-{$slug}");
        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        file_put_contents("{$dir}/concat.txt", "file " . escapeshellarg($p1) . "\n");
        $this->tempFiles[] = "{$dir}/concat.txt";

        // The playlist now resolves to two files but Stage 1 was launched
        // with one — exactly the "stops after the first file" state.
        $this->assertTrue($this->service()->playlistDrifted($channel->fresh()));

        // Written by the same code that starts Stage 1, the list must compare
        // byte-for-byte equal, or the watchdog would thrash every channel.
        file_put_contents(
            "{$dir}/concat.txt",
            "file " . escapeshellarg($p1) . "\nfile " . escapeshellarg($p2) . "\n"
        );
        $this->assertFalse($this->service()->playlistDrifted($channel->fresh()));

        @rmdir($dir);
    }

    // ── Fixtures ─────────────────────────────────────────────────────────────

    private function persistChannel(string $slug): AdminChannel
    {
        return AdminChannel::create([
            'channel_name'     => "Ch {$slug}",
            'channel_slug'     => $slug,
            'is_my_channel'    => true,
            'broadcast_status' => 'live',
            'output_resolution' => '1280x720',
            'output_frame_rate' => 25,
            'created_by'       => User::factory()->create()->id,
        ]);
    }

    private function persistContent(AdminChannel $channel, int $n, string $title): MyChannelContent
    {
        $user = User::factory()->create();

        $content = MyChannelContent::create([
            'channel_id'  => $channel->id,
            'title'       => $title,
            'file_path'   => "mychannel/{$channel->channel_slug}/{$n}.mp4",
            'uploaded_by' => $user->id,
        ]);

        MyChannelPlaylist::create([
            'channel_id'  => $channel->id,
            'content_id'  => $content->id,
            'order_index' => $n,
        ]);

        // The source must exist on disk or collectFiles excludes the item.
        $abs = Storage::disk('public')->path($content->file_path);
        if (! is_dir(dirname($abs))) {
            mkdir(dirname($abs), 0775, true);
        }
        file_put_contents($abs, 'stub');
        $this->tempFiles[] = $abs;

        return $content;
    }

    /**
     * Make isRunning() see a live process for this channel: a real sleeping
     * child whose cmdline carries the channel slug, registered through both
     * discovery paths (cache pid and the on-disk playout.pid). Works on the
     * Linux container where /proc exists; on macOS isRunning() can never see
     * it, so the drift assertion is skipped there rather than passing vacuously.
     */
    private function fakeRunning(AdminChannel $channel, string $slug): bool
    {
        if (! is_dir('/proc')) {
            return false;
        }

        $dir = storage_path("app/streams/hls/admin-channel-{$slug}");
        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        // The PID of a backgrounded child cannot be captured through exec()'s
        // stdout reliably, so the spawner writes it to a file instead.
        $pidFile = sys_get_temp_dir() . '/mchls_fake_pid_' . $slug;
        @unlink($pidFile);
        exec('bash -c "exec -a admin-channel-' . escapeshellarg($slug) . ' sleep 60 >/dev/null 2>&1 & echo \$! > ' . escapeshellarg($pidFile) . '"');
        usleep(200000);
        $pid = (int) trim((string) @file_get_contents($pidFile));
        @unlink($pidFile);

        if ($pid <= 0 || ! @file_exists("/proc/{$pid}")) {
            return false;
        }

        file_put_contents("{$dir}/playout.pid", (string) $pid);
        cache()->put($this->invoke('cacheKey', $channel), $pid, 60);

        $this->tempPids[] = $pid;

        return true;
    }

    private function content(int $id, string $slug, string $title): MyChannelContent
    {
        $source = "mychannel/{$slug}/{$id}.mp4";
        $abs    = Storage::disk('public')->path($source);

        if (! is_dir(dirname($abs))) {
            mkdir(dirname($abs), 0775, true);
        }
        file_put_contents($abs, 'stub');
        $this->tempFiles[] = $abs;

        $content = new MyChannelContent([
            'title'     => $title,
            'file_path' => $source,
        ]);
        $content->id = $id;

        return $content;
    }

    private function preparedFile(string $slug, int $id): string
    {
        $path = storage_path("app/streams/normalized/{$slug}/prepared_{$id}.mp4");

        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0775, true);
        }
        file_put_contents($path, 'stub');
        $this->tempFiles[] = $path;

        return $path;
    }

    // ── Now-playing schedule resolution ──────────────────────────────────────

    /** @return array<int, array{content_id:int, duration:int}> */
    private function fiveEvenSchedule(): array
    {
        return [
            ['content_id' => 1, 'duration' => 8],
            ['content_id' => 2, 'duration' => 8],
            ['content_id' => 3, 'duration' => 8],
            ['content_id' => 4, 'duration' => 8],
            ['content_id' => 5, 'duration' => 8],
        ]; // total = 40s
    }

    public function test_now_playing_starts_on_first_item(): void
    {
        $np = $this->invoke('resolveNowPlaying', $this->fiveEvenSchedule(), 0, true);
        $this->assertSame(1, $np['content_id']);
        $this->assertSame(0, $np['index']);
        $this->assertSame(0, $np['item_elapsed']);
    }

    public function test_now_playing_advances_at_item_boundary(): void
    {
        $np = $this->invoke('resolveNowPlaying', $this->fiveEvenSchedule(), 8, true);
        $this->assertSame(2, $np['content_id']);
        $this->assertSame(1, $np['index']);
        $this->assertSame(0, $np['item_elapsed']);

        $np = $this->invoke('resolveNowPlaying', $this->fiveEvenSchedule(), 19, true);
        $this->assertSame(3, $np['content_id']);
        $this->assertSame(3, $np['item_elapsed']); // 19 - 16
    }

    public function test_now_playing_wraps_around_when_looping(): void
    {
        $np = $this->invoke('resolveNowPlaying', $this->fiveEvenSchedule(), 42, true);
        $this->assertSame(1, $np['content_id']); // 42 % 40 == 2 -> still item 1
        $this->assertSame(2, $np['item_elapsed']);

        $np = $this->invoke('resolveNowPlaying', $this->fiveEvenSchedule(), 39, true);
        $this->assertSame(5, $np['content_id']);
        $this->assertSame(4, $np['index']);
        $this->assertSame(7, $np['item_elapsed']);
    }

    public function test_now_playing_clamps_at_end_without_loop(): void
    {
        $np = $this->invoke('resolveNowPlaying', $this->fiveEvenSchedule(), 45, false);
        $this->assertSame(5, $np['content_id']); // clamped to final segment
        $this->assertSame(4, $np['index']);

        $np = $this->invoke('resolveNowPlaying', $this->fiveEvenSchedule(), 100000, false);
        $this->assertSame(5, $np['content_id']);
    }

    public function test_now_playing_handles_mixed_durations(): void
    {
        $schedule = [
            ['content_id' => 10, 'duration' => 5],
            ['content_id' => 20, 'duration' => 30],
            ['content_id' => 30, 'duration' => 15],
        ]; // total 50
        $np = $this->invoke('resolveNowPlaying', $schedule, 6, true);
        $this->assertSame(20, $np['content_id']); // 5..35 window
        $this->assertSame(1, $np['item_elapsed']);

        $np = $this->invoke('resolveNowPlaying', $schedule, 40, true);
        $this->assertSame(30, $np['content_id']); // 35..50 window
        $this->assertSame(5, $np['item_elapsed']);
    }

    public function test_now_playing_includes_a_one_second_playlist_item(): void
    {
        $schedule = [
            ['content_id' => 1, 'duration' => 1],
            ['content_id' => 2, 'duration' => 8],
        ];

        $this->assertSame(1, $this->invoke('resolveNowPlaying', $schedule, 0, true)['content_id']);
        $this->assertSame(2, $this->invoke('resolveNowPlaying', $schedule, 1, true)['content_id']);
        $this->assertSame(1, $this->invoke('resolveNowPlaying', $schedule, 9, true)['content_id']);
    }

    public function test_now_playing_returns_null_for_empty_or_zero_schedule(): void
    {
        $this->assertNull($this->invoke('resolveNowPlaying', [], 10, true));
        $this->assertNull($this->invoke('resolveNowPlaying', [['content_id' => 1, 'duration' => 0]], 10, true));
    }
}

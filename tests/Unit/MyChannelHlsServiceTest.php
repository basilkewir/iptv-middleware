<?php

namespace Tests\Unit;

use App\Models\AdminChannel\AdminChannel;
use App\Models\AdminChannel\MyChannelContent;
use App\Models\AdminChannel\MyChannelPlaylist;
use App\Models\User;
use App\Services\AdminChannel\MyChannelHlsService;
use Illuminate\Support\Collection;
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
    private function graph(AdminChannel $channel, string $canvasMode = 'png'): array
    {
        return $this->invoke('buildFiltergraph', '/tmp/stream-dir', $channel, 1280, 720, 25, $canvasMode);
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

    public function test_canvas_input_is_always_present_so_image_edits_need_no_restart(): void
    {
        [$inputs, $vf] = $this->graph($this->plainChannel());

        // Even with every overlay disabled the canvas stays wired in, so
        // enabling a logo later is a file rewrite rather than a graph change.
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

    public function test_image_change_is_live_in_the_default_canvas_mode(): void
    {
        config(['playout.canvas_mode' => 'png']);

        $this->assertFalse($this->service()->encoderRestartRequired(
            $this->plainChannel(),
            ['logo_url' => '/new/logo.png', 'overlay_logo_size' => 250, 'enable_watermark' => true]
        ));
    }

    public function test_image_change_needs_a_restart_when_the_canvas_is_static(): void
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
            ['changed' => false, 'files' => 0, 'excluded' => []],
            $this->service()->refreshPlaylist($channel)
        );
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
}

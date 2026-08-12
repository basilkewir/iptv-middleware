<?php

namespace Tests\Unit;

use App\Models\AdminChannel\AdminChannel;
use App\Services\AdminChannel\MyChannelHlsService;
use ReflectionMethod;
use Tests\TestCase;

class MyChannelHlsServiceTest extends TestCase
{
    private function buildFiltergraph(AdminChannel $channel, int $width = 1280, int $height = 720): array
    {
        $service = new MyChannelHlsService();

        $method = new ReflectionMethod(MyChannelHlsService::class, 'buildOverlayFiltergraph');
        $method->setAccessible(true);

        return $method->invoke($service, $channel, $width, $height);
    }

    private function makeLogoFile(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'logo_');
        file_put_contents($path, 'fake-png');

        return $path;
    }

    private function logoChannel(array $overrides = []): AdminChannel
    {
        $logo = $this->makeLogoFile();

        return new AdminChannel(array_merge([
            'enable_overlay_logo'   => true,
            'logo_url'              => $logo,
            'overlay_logo_position' => 'top-left',
            'overlay_logo_x'        => 10,
            'overlay_logo_y'        => 20,
            'overlay_logo_size'     => 100,
            'overlay_logo_opacity'  => 1,
            'enable_overlay_clock'  => false,
            'enable_watermark'      => false,
            'enable_ticker'         => false,
        ], $overrides));
    }

    private function clockChannel(array $overrides = []): AdminChannel
    {
        return new AdminChannel(array_merge([
            'enable_overlay_logo'    => false,
            'enable_overlay_clock'   => true,
            'overlay_clock_position' => 'top-left',
            'overlay_clock_x'        => 50,
            'overlay_clock_y'        => 5,
            'overlay_clock_format'   => 'HH:MM:SS',
            'enable_watermark'       => false,
            'enable_ticker'          => false,
        ], $overrides));
    }

    public function test_logo_position_is_pixel_precise_from_percentages(): void
    {
        [, $vf] = $this->buildFiltergraph($this->logoChannel());

        // width=1280, size=100 → logoW = round(1280 * 100/1000) = 128
        $this->assertStringContainsString('scale=128:-1:flags=lanczos[logo_scaled]', $vf);
        // x=10% → 128, y=20% → 144 (exact integer pixels, top-left anchored)
        $this->assertStringContainsString('overlay=128:144[vout]', $vf);
    }

    public function test_logo_position_rounds_decimal_percentages_precisely(): void
    {
        [, $vf] = $this->buildFiltergraph($this->logoChannel([
            'overlay_logo_x' => '12.5',
            'overlay_logo_y' => '33.3',
        ]));

        // 1280 * 0.125 = 160 ; 720 * 0.333 = 239.76 → 240
        $this->assertStringContainsString('overlay=160:240[vout]', $vf);
    }

    public function test_logo_falls_back_to_preset_corner_when_xy_missing(): void
    {
        [, $vf] = $this->buildFiltergraph($this->logoChannel([
            'overlay_logo_x'        => null,
            'overlay_logo_y'        => null,
            'overlay_logo_position' => 'top-right',
        ]));

        // logoW=128, elemH=64 → x = 1280-128-10 = 1142, y = 10
        $this->assertStringContainsString('overlay=1142:10[vout]', $vf);
    }

    public function test_logo_bottom_right_preset_stays_inside_frame(): void
    {
        [, $vf] = $this->buildFiltergraph($this->logoChannel([
            'overlay_logo_x'        => null,
            'overlay_logo_y'        => null,
            'overlay_logo_position' => 'bottom-right',
        ]));

        // x = 1280-128-10 = 1142, y = 720-64-10 = 646
        $this->assertStringContainsString('overlay=1142:646[vout]', $vf);
    }

    public function test_clock_position_is_pixel_precise_from_percentages(): void
    {
        [, $vf] = $this->buildFiltergraph($this->clockChannel());

        // x=50% → 640, y=5% → 36 (last filter, label stripped)
        $this->assertStringContainsString('x=640:y=36', $vf);
    }

    public function test_clock_falls_back_to_preset_corner_when_xy_missing(): void
    {
        [, $vf] = $this->buildFiltergraph($this->clockChannel([
            'overlay_clock_x'        => null,
            'overlay_clock_y'        => null,
            'overlay_clock_position' => 'bottom-right',
        ]));

        // fontsize = max(14, round(720*0.03)) = 22 → elemW=198, elemH=38
        // x = 1280-198-10 = 1072, y = 720-38-10 = 672
        $this->assertStringContainsString('x=1072:y=672', $vf);
    }

    public function test_disabled_overlays_are_not_burned_in(): void
    {
        [$inputs, $vf, $hasExtra] = $this->buildFiltergraph($this->clockChannel([
            'enable_overlay_clock' => false,
        ]));

        $this->assertFalse($hasExtra);
        $this->assertSame('', $inputs);
        $this->assertStringNotContainsString('drawtext', $vf);
        $this->assertStringNotContainsString('overlay=', $vf);
    }
}

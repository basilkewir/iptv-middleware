<?php

namespace Tests\Unit;

use App\Http\Controllers\XtreamController;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class MulticastIngestCommandTest extends TestCase
{
    private function wrapperCommand(string $outputDir, string $sourceUrl, ?int $programNumber = null, ?string $localAddress = null): string
    {
        $method = new ReflectionMethod(XtreamController::class, 'ingestWrapperCommand');
        $method->setAccessible(true);

        return (string) $method->invoke(new XtreamController(), $outputDir, $sourceUrl, $programNumber, $localAddress);
    }

    public function test_wrapper_maps_single_program_for_multicast_channel(): void
    {
        $cmd = $this->wrapperCommand(
            '/var/www/storage/app/streams/hls/42',
            'udp://@239.0.0.1:32768',
            3,
            '192.168.1.50'
        );

        $this->assertStringContainsString('udp://@239.0.0.1:32768?localaddr=192.168.1.50', $cmd);
        $this->assertStringContainsString(' -map p:3 -map_chapters -1 -ignore_unknown', $cmd);
        $this->assertStringContainsString('+genpts+discardcorrupt+nobuffer', $cmd);
        $this->assertStringContainsString('-flags low_delay', $cmd);
        $this->assertStringContainsString('-probesize 1M -analyzeduration 500000', $cmd);
        $this->assertStringContainsString('-rw_timeout 5000000 -timeout 5000000', $cmd);
        $this->assertStringContainsString('-hls_time 2 -hls_list_size 3', $cmd);
        $this->assertStringContainsString('-muxdelay 0 -muxpreload 0', $cmd);
        $this->assertStringNotContainsString('-reconnect', $cmd);
        $this->assertStringNotContainsString(' -re -i', $cmd);
    }

    public function test_wrapper_without_program_stays_unchanged(): void
    {
        $cmd = $this->wrapperCommand(
            '/var/www/storage/app/streams/hls/7',
            'http://example.com/live.m3u8'
        );

        $this->assertStringNotContainsString('-map p:', $cmd);
        $this->assertStringNotContainsString('localaddr=', $cmd);
        $this->assertStringContainsString('http://example.com/live.m3u8', $cmd);
        $this->assertStringContainsString('-reconnect 1 -reconnect_streamed 1 -reconnect_on_http_error 404,403 -reconnect_delay_max 5', $cmd);
    }

    public function test_wrapper_ignores_zero_or_null_program(): void
    {
        $cmd = $this->wrapperCommand(
            '/var/www/storage/app/streams/hls/7',
            'udp://@239.0.0.1:32768',
            0,
            null
        );

        $this->assertStringNotContainsString('-map p:', $cmd);
        $this->assertStringNotContainsString('localaddr=', $cmd);
    }
}

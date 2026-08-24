<?php

namespace Tests\Unit;

use App\Services\StreamingService\MulticastScanner;
use PHPUnit\Framework\TestCase;

class FakeMulticastScanner extends MulticastScanner
{
    public function __construct(private readonly string $payload)
    {
        parent::__construct('/usr/bin/ffprobe');
    }

    protected function probe(string $command): ?string
    {
        return $this->payload;
    }
}

class MulticastScannerTest extends TestCase
{
    public function test_build_input_url_appends_local_addr_for_udp(): void
    {
        $scanner = new MulticastScanner('/usr/bin/ffprobe');

        $this->assertSame(
            'udp://@239.0.0.1:32768?localaddr=192.168.1.50',
            $scanner->buildInputUrl('udp://@239.0.0.1:32768', '192.168.1.50')
        );
    }

    public function test_build_input_url_leaves_non_multicast_untouched(): void
    {
        $scanner = new MulticastScanner('/usr/bin/ffprobe');

        $this->assertSame(
            'udp://@239.0.0.1:32768',
            $scanner->buildInputUrl('udp://@239.0.0.1:32768', null)
        );
    }

    public function test_scan_returns_empty_for_non_udp_url(): void
    {
        $scanner = new FakeMulticastScanner('{}');

        $this->assertSame([], $scanner->scan('http://example.com/live.m3u8'));
    }

    public function test_scan_parses_programs_with_stream_details(): void
    {
        $payload = json_encode([
            'programs' => [
                [
                    'program_id' => 1,
                    'program_num' => 1,
                    'nb_streams' => 2,
                    'pmt_pid' => 4096,
                    'pcr_pid' => 256,
                    'tags' => [
                        'service_name' => 'ESPN',
                        'service_provider' => 'Provider',
                    ],
                ],
                [
                    'program_id' => 3,
                    'program_num' => 3,
                    'nb_streams' => 2,
                    'pmt_pid' => 4098,
                    'pcr_pid' => 512,
                    'tags' => [
                        'service_name' => 'CNN',
                    ],
                ],
            ],
            'streams' => [
                [
                    'codec_type' => 'video',
                    'codec_name' => 'h264',
                    'width' => 1920,
                    'height' => 1080,
                    'program_id' => 1,
                ],
                [
                    'codec_type' => 'audio',
                    'codec_name' => 'aac',
                    'program_id' => 1,
                ],
                [
                    'codec_type' => 'video',
                    'codec_name' => 'h264',
                    'width' => 1280,
                    'height' => 720,
                    'program_id' => 3,
                ],
                [
                    'codec_type' => 'audio',
                    'codec_name' => 'ac3',
                    'program_id' => 3,
                ],
            ],
        ]);

        $scanner = new FakeMulticastScanner((string) $payload);

        $programs = $scanner->scan('udp://@239.0.0.1:32768');

        $this->assertCount(2, $programs);
        $this->assertSame(1, $programs[0]['program_id']);
        $this->assertSame('ESPN', $programs[0]['tags']['service_name']);
        $this->assertSame('h264', $programs[0]['video']['codec']);
        $this->assertSame(1080, $programs[0]['video']['height']);
        $this->assertSame('aac', $programs[0]['audio']);
        $this->assertSame(3, $programs[1]['program_id']);
    }

    public function test_scan_returns_empty_when_probe_yields_no_programs(): void
    {
        $scanner = new FakeMulticastScanner('{"programs":[],"streams":[]}');

        $this->assertSame([], $scanner->scan('udp://@239.0.0.1:32768'));
    }

    public function test_scan_returns_empty_when_probe_output_is_invalid(): void
    {
        $scanner = new FakeMulticastScanner('not json');

        $this->assertSame([], $scanner->scan('udp://@239.0.0.1:32768'));
    }

    public function test_probe_command_uses_small_probesize_for_fast_tag_detection(): void
    {
        $scanner = new class ('ffprobe') extends MulticastScanner
        {
            public function __construct(string $ffprobe)
            {
                parent::__construct($ffprobe);
            }

            public function buildProbeCommandPublic(string $url, ?string $localAddress, int $timeout): string
            {
                return $this->buildProbeCommand($url, $localAddress, $timeout);
            }
        };

        $cmd = $scanner->buildProbeCommandPublic('udp://@239.0.0.1:32768', null, 15);

        $this->assertStringContainsString('-probesize 1M -analyzeduration 1M', $cmd);
        $this->assertStringNotContainsString('-analyzeduration 10M', $cmd);
        $this->assertStringContainsString('udp://@239.0.0.1:32768', $cmd);
    }
}

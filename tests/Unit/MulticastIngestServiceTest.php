<?php

namespace Tests\Unit;

use App\Models\Channel;
use App\Services\StreamingService\MulticastIngestService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MulticastIngestServiceTest extends TestCase
{
    use RefreshDatabase;

    private MulticastIngestService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new MulticastIngestService();
    }

    // ─── buildSourceUrl ──────────────────────────────────────────────────

    public function test_build_source_url_udp_adds_buffer_size(): void
    {
        $channel = Channel::factory()->create([
            'stream_url' => 'udp://@224.1.1.1:5000',
            'active_stream_url' => 'udp://@224.1.1.1:5000',
            'local_address' => null,
        ]);

        $result = $this->service->buildSourceUrl($channel);

        $this->assertStringContainsString('buffer_size=33554432', $result);
        $this->assertStringStartsWith('udp://@224.1.1.1:5000', $result);
    }

    public function test_build_source_url_udp_adds_localaddr(): void
    {
        $channel = Channel::factory()->create([
            'stream_url' => 'udp://@224.1.1.1:5000',
            'active_stream_url' => 'udp://@224.1.1.1:5000',
            'local_address' => '192.168.1.100',
        ]);

        $result = $this->service->buildSourceUrl($channel);

        $this->assertStringContainsString('localaddr=192.168.1.100', $result);
        $this->assertStringContainsString('buffer_size=33554432', $result);
    }

    public function test_build_source_url_udp_does_not_duplicate_buffer_size(): void
    {
        $channel = Channel::factory()->create([
            'stream_url' => 'udp://@224.1.1.1:5000?buffer_size=16777216',
            'active_stream_url' => 'udp://@224.1.1.1:5000?buffer_size=16777216',
            'local_address' => null,
        ]);

        $result = $this->service->buildSourceUrl($channel);

        $this->assertStringNotContainsString('buffer_size=33554432', $result);
        $this->assertStringContainsString('buffer_size=16777216', $result);
    }

    public function test_build_source_url_udp_does_not_duplicate_localaddr(): void
    {
        $channel = Channel::factory()->create([
            'stream_url' => 'udp://@224.1.1.1:5000?localaddr=10.0.0.1',
            'active_stream_url' => 'udp://@224.1.1.1:5000?localaddr=10.0.0.1',
            'local_address' => '10.0.0.1',
        ]);

        $result = $this->service->buildSourceUrl($channel);

        preg_match_all('/localaddr=/', $result, $matches);
        $this->assertCount(1, $matches[0], 'localaddr should appear exactly once');
    }

    public function test_build_source_url_rtp_adds_buffer_size(): void
    {
        $channel = Channel::factory()->create([
            'stream_url' => 'rtp://@224.2.2.2:6000',
            'active_stream_url' => 'rtp://@224.2.2.2:6000',
            'local_address' => null,
        ]);

        $result = $this->service->buildSourceUrl($channel);

        $this->assertStringContainsString('buffer_size=33554432', $result);
    }

    public function test_build_source_url_hls_returns_unchanged(): void
    {
        $channel = Channel::factory()->create([
            'stream_url' => 'http://stream.example.com/live.m3u8',
            'active_stream_url' => 'http://stream.example.com/live.m3u8',
            'local_address' => '192.168.1.1',
        ]);

        $result = $this->service->buildSourceUrl($channel);

        $this->assertEquals('http://stream.example.com/live.m3u8', $result);
        $this->assertStringNotContainsString('buffer_size', $result);
        $this->assertStringNotContainsString('localaddr', $result);
    }

    public function test_build_source_url_falls_back_to_stream_url(): void
    {
        $channel = Channel::factory()->create([
            'stream_url' => 'udp://@224.1.1.1:5000',
            'active_stream_url' => null,
        ]);

        $result = $this->service->buildSourceUrl($channel);

        $this->assertStringStartsWith('udp://@224.1.1.1:5000', $result);
    }

    // ─── getChannelGroups ────────────────────────────────────────────────

    public function test_get_channel_groups_groups_by_source_url(): void
    {
        Channel::factory()->create([
            'is_active' => true,
            'stream_url' => 'udp://@224.1.1.1:5000',
            'active_stream_url' => 'udp://@224.1.1.1:5000',
            'local_address' => null,
        ]);

        Channel::factory()->create([
            'is_active' => true,
            'stream_url' => 'udp://@224.1.1.1:5000',
            'active_stream_url' => 'udp://@224.1.1.1:5000',
            'local_address' => null,
        ]);

        $groups = $this->service->getChannelGroups();

        $this->assertCount(1, $groups);
        $this->assertCount(2, reset($groups));
    }

    public function test_get_channel_groups_separates_different_sources(): void
    {
        Channel::factory()->create([
            'is_active' => true,
            'stream_url' => 'udp://@224.1.1.1:5000',
            'active_stream_url' => 'udp://@224.1.1.1:5000',
            'local_address' => null,
        ]);

        Channel::factory()->create([
            'is_active' => true,
            'stream_url' => 'udp://@224.2.2.2:6000',
            'active_stream_url' => 'udp://@224.2.2.2:6000',
            'local_address' => null,
        ]);

        $groups = $this->service->getChannelGroups();

        $this->assertCount(2, $groups);
    }

    public function test_get_channel_groups_excludes_inactive_channels(): void
    {
        Channel::factory()->create([
            'is_active' => false,
            'stream_url' => 'udp://@224.1.1.1:5000',
            'active_stream_url' => 'udp://@224.1.1.1:5000',
        ]);

        $groups = $this->service->getChannelGroups();

        $this->assertCount(0, $groups);
    }

    public function test_get_channel_groups_excludes_hls_backup_channels(): void
    {
        // Channel has failed over to HLS — should not be in multicast group
        Channel::factory()->create([
            'is_active' => true,
            'stream_url' => 'udp://@224.1.1.1:5000',
            'active_stream_url' => 'http://backup.example.com/stream.m3u8',
            'local_address' => null,
        ]);

        $groups = $this->service->getChannelGroups();

        $this->assertCount(0, $groups);
    }

    public function test_get_channel_groups_includes_rtp_sources(): void
    {
        Channel::factory()->create([
            'is_active' => true,
            'stream_url' => 'rtp://@224.1.1.1:5000',
            'active_stream_url' => 'rtp://@224.1.1.1:5000',
        ]);

        $groups = $this->service->getChannelGroups();

        $this->assertCount(1, $groups);
    }

    public function test_get_channel_groups_excludes_hls_only_channels(): void
    {
        Channel::factory()->create([
            'is_active' => true,
            'stream_url' => 'http://cdn.example.com/live.m3u8',
            'active_stream_url' => 'http://cdn.example.com/live.m3u8',
        ]);

        $groups = $this->service->getChannelGroups();

        $this->assertCount(0, $groups);
    }

    // ─── source URL building with localaddr and buffer_size together ─────

    public function test_build_source_url_udp_with_localaddr_and_program_number(): void
    {
        $channel = Channel::factory()->create([
            'stream_url' => 'udp://@224.1.1.1:5000?program_number=100',
            'active_stream_url' => 'udp://@224.1.1.1:5000?program_number=100',
            'local_address' => '10.0.0.5',
        ]);

        $result = $this->service->buildSourceUrl($channel);

        $this->assertStringContainsString('program_number=100', $result);
        $this->assertStringContainsString('localaddr=10.0.0.5', $result);
        $this->assertStringContainsString('buffer_size=33554432', $result);
    }

    public function test_build_source_url_udp_with_existing_query_params(): void
    {
        $channel = Channel::factory()->create([
            'stream_url' => 'udp://@224.1.1.1:5000?pkt_size=1316',
            'active_stream_url' => 'udp://@224.1.1.1:5000?pkt_size=1316',
            'local_address' => '10.0.0.5',
        ]);

        $result = $this->service->buildSourceUrl($channel);

        $this->assertStringContainsString('pkt_size=1316', $result);
        $this->assertStringContainsString('&localaddr=10.0.0.5', $result);
        $this->assertStringContainsString('&buffer_size=33554432', $result);
    }
}

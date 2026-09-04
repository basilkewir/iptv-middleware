<?php

namespace Tests\Unit;

use App\Models\ChannelPushDestination;
use App\Models\PushDestination;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PushDestinationTest extends TestCase
{
    use RefreshDatabase;

    // ─── full_url attribute ──────────────────────────────────────────────

    public function test_full_url_without_stream_key(): void
    {
        $dest = PushDestination::create([
            'name' => 'No Key',
            'protocol' => 'rtmp',
            'url' => 'rtmp://cdn.example.com/live',
        ]);

        $this->assertEquals('rtmp://cdn.example.com/live', $dest->full_url);
    }

    public function test_full_url_with_stream_key(): void
    {
        $dest = PushDestination::create([
            'name' => 'With Key',
            'protocol' => 'rtmp',
            'url' => 'rtmp://cdn.example.com/live',
            'stream_key' => 'my_stream_key',
        ]);

        $this->assertEquals('rtmp://cdn.example.com/live/my_stream_key', $dest->full_url);
    }

    public function test_full_url_strips_leading_slash_from_stream_key(): void
    {
        $dest = PushDestination::create([
            'name' => 'Leading Slash',
            'protocol' => 'rtmp',
            'url' => 'rtmp://cdn.example.com/live',
            'stream_key' => '/my_key',
        ]);

        $this->assertEquals('rtmp://cdn.example.com/live/my_key', $dest->full_url);
    }

    public function test_full_url_trims_trailing_slash(): void
    {
        $dest = PushDestination::create([
            'name' => 'Trailing Slash',
            'protocol' => 'rtmp',
            'url' => 'rtmp://cdn.example.com/live/',
        ]);

        $this->assertEquals('rtmp://cdn.example.com/live', $dest->full_url);
    }

    // ─── authenticated_url attribute ─────────────────────────────────────

    public function test_authenticated_url_no_credentials(): void
    {
        $dest = PushDestination::create([
            'name' => 'No Auth',
            'protocol' => 'rtmp',
            'url' => 'rtmp://cdn.example.com/live',
            'stream_key' => 'mykey',
        ]);

        $this->assertEquals('rtmp://cdn.example.com/live/mykey', $dest->authenticated_url);
    }

    public function test_authenticated_url_rtmp_with_username_and_password(): void
    {
        $dest = PushDestination::create([
            'name' => 'RTMP Auth',
            'protocol' => 'rtmp',
            'url' => 'rtmp://cdn.example.com/live',
            'stream_key' => 'mykey',
            'username' => 'user1',
            'password' => 'pass1',
        ]);

        $url = $dest->authenticated_url;

        $this->assertStringContainsString('user1:pass1@', $url);
        $this->assertStringStartsWith('rtmp://', $url);
        $this->assertStringContainsString('/live/mykey', $url);
    }

    public function test_authenticated_url_rtmp_special_chars_encoded(): void
    {
        $dest = PushDestination::create([
            'name' => 'Special Chars',
            'protocol' => 'rtmp',
            'url' => 'rtmp://cdn.example.com/live',
            'stream_key' => 'mykey',
            'username' => 'user@domain',
            'password' => 'p@ss:word',
        ]);

        $url = $dest->authenticated_url;

        // rawurlencode encodes: @ → %40, : → %3A
        $this->assertStringContainsString('user%40domain', $url);
        $this->assertStringContainsString('%3A', $url);
        $this->assertStringContainsString('@cdn.example.com', $url);
        $this->assertStringContainsString('/live/mykey', $url);
    }

    public function test_authenticated_url_srt_with_password(): void
    {
        $dest = PushDestination::create([
            'name' => 'SRT Auth',
            'protocol' => 'srt',
            'url' => 'srt://srt.example.com:9000',
            'password' => 'srt_secret',
        ]);

        $url = $dest->authenticated_url;

        $this->assertStringContainsString('passphrase=srt_secret', $url);
    }

    public function test_authenticated_url_srt_existing_query(): void
    {
        $dest = PushDestination::create([
            'name' => 'SRT Existing Query',
            'protocol' => 'srt',
            'url' => 'srt://srt.example.com:9000?streamid=key',
            'password' => 'srt_secret',
        ]);

        $url = $dest->authenticated_url;

        $this->assertStringContainsString('streamid=key', $url);
        $this->assertStringContainsString('&passphrase=srt_secret', $url);
    }

    public function test_authenticated_url_non_rtmp_srt_returns_plain_url(): void
    {
        $dest = PushDestination::create([
            'name' => 'HLS',
            'protocol' => 'hls',
            'url' => 'http://cdn.example.com/live.m3u8',
            'username' => 'user',
            'password' => 'pass',
        ]);

        $this->assertEquals('http://cdn.example.com/live.m3u8', $dest->authenticated_url);
    }

    // ─── password hidden ─────────────────────────────────────────────────

    public function test_password_is_hidden_in_array(): void
    {
        $dest = PushDestination::create([
            'name' => 'Hidden',
            'protocol' => 'rtmp',
            'url' => 'rtmp://cdn.example.com/live',
            'password' => 'secret',
        ]);

        $array = $dest->toArray();

        $this->assertArrayNotHasKey('password', $array);
    }

    // ─── relationships ───────────────────────────────────────────────────

    public function test_has_many_channel_push_destinations(): void
    {
        $dest = PushDestination::create([
            'name' => 'CDN',
            'protocol' => 'rtmp',
            'url' => 'rtmp://cdn.example.com/live',
        ]);

        $channel = \App\Models\Channel::factory()->create();

        ChannelPushDestination::create([
            'channel_id' => $channel->id,
            'push_destination_id' => $dest->id,
            'status' => 'idle',
        ]);

        $this->assertCount(1, $dest->channelPushDestinations);
    }
}

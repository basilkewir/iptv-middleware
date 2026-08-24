<?php

namespace Tests\Feature;

use App\Models\Channel;
use App\Services\StreamingService\MulticastScanner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MulticastScanCommandTest extends TestCase
{
    use RefreshDatabase;

    private function fakeScanner(array $programs): MulticastScanner
    {
        $payload = json_encode([
            'programs' => $programs,
            'streams' => [],
        ]);

        return new class ($payload) extends MulticastScanner
        {
            public function __construct(private readonly string $payload)
            {
                parent::__construct('/usr/bin/ffprobe');
            }

            protected function probe(string $command): ?string
            {
                return $this->payload;
            }
        };
    }

    private function programs(): array
    {
        return [
            [
                'program_id' => 1,
                'nb_streams' => 2,
                'tags' => ['service_name' => 'ESPN', 'service_provider' => 'Provider'],
            ],
            [
                'program_id' => 3,
                'nb_streams' => 2,
                'tags' => ['service_name' => 'CNN', 'service_provider' => 'Provider'],
            ],
        ];
    }

    public function test_command_lists_programs_without_importing(): void
    {
        $this->app->instance(MulticastScanner::class, $this->fakeScanner($this->programs()));

        $this->artisan('channels:scan-multicast', ['url' => 'udp://@239.0.0.1:32768'])
            ->assertExitCode(0);

        $this->assertDatabaseCount('channels', 0);
    }

    public function test_command_imports_programs_as_channels(): void
    {
        $this->app->instance(MulticastScanner::class, $this->fakeScanner($this->programs()));

        $this->artisan('channels:scan-multicast', [
            'url' => 'udp://@239.0.0.1:32768',
            '--local-addr' => '192.168.1.50',
            '--import' => true,
        ])->assertExitCode(0);

        $this->assertDatabaseCount('channels', 2);

        $this->assertDatabaseHas('channels', [
            'name' => 'ESPN',
            'stream_url' => 'udp://@239.0.0.1:32768',
            'stream_type' => 'udp',
            'program_number' => 1,
            'local_address' => '192.168.1.50',
        ]);

        $this->assertDatabaseHas('channels', [
            'name' => 'CNN',
            'program_number' => 3,
        ]);
    }

    public function test_command_imports_only_selected_programs(): void
    {
        $this->app->instance(MulticastScanner::class, $this->fakeScanner($this->programs()));

        $this->artisan('channels:scan-multicast', [
            'url' => 'udp://@239.0.0.1:32768',
            '--import' => true,
            '--select' => '3',
        ])->assertExitCode(0);

        // Only the program with program_id 3 (CNN) should be imported.
        $this->assertDatabaseCount('channels', 1);
        $this->assertDatabaseHas('channels', [
            'name' => 'CNN',
            'program_number' => 3,
        ]);
        $this->assertDatabaseMissing('channels', ['name' => 'ESPN']);
    }

    public function test_select_rejects_non_integer_values(): void
    {
        $this->app->instance(MulticastScanner::class, $this->fakeScanner($this->programs()));

        $this->artisan('channels:scan-multicast', [
            'url' => 'udp://@239.0.0.1:32768',
            '--import' => true,
            '--select' => '3,bogus',
        ])->assertExitCode(1);

        $this->assertDatabaseCount('channels', 0);
    }

    public function test_command_skips_channels_that_already_exist(): void
    {
        Channel::factory()->create([
            'name' => 'ESPN',
            'stream_url' => 'udp://@239.0.0.1:32768',
            'stream_type' => 'udp',
            'program_number' => 1,
        ]);

        $this->app->instance(MulticastScanner::class, $this->fakeScanner($this->programs()));

        $this->artisan('channels:scan-multicast', [
            'url' => 'udp://@239.0.0.1:32768',
            '--import' => true,
        ])->assertExitCode(0);

        $this->assertDatabaseCount('channels', 2);
        $this->assertDatabaseHas('channels', ['name' => 'CNN']);
    }

    public function test_command_rejects_non_udp_urls(): void
    {
        $this->app->instance(MulticastScanner::class, $this->fakeScanner([]));

        $this->artisan('channels:scan-multicast', ['url' => 'http://example.com/live.m3u8'])
            ->assertExitCode(1);

        $this->assertDatabaseCount('channels', 0);
    }

    public function test_channel_model_persists_multicast_fields(): void
    {
        $channel = Channel::factory()->create([
            'stream_url' => 'udp://@239.0.0.1:32768',
            'stream_type' => 'udp',
            'program_number' => 7,
            'local_address' => '192.168.1.50',
        ]);

        $fresh = $channel->fresh();

        $this->assertSame(7, $fresh->program_number);
        $this->assertSame('192.168.1.50', $fresh->local_address);
    }
}

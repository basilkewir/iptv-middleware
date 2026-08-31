<?php

namespace Tests\Feature;

use App\Models\Channel;
use App\Models\ContentCategory;
use App\Models\User;
use App\Services\StreamingService\MulticastScanner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MulticastScanControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'admin', 'is_admin' => true]);
    }

    private function fakeScanner(array $programs): MulticastScanner
    {
        $payload = json_encode(['programs' => $programs, 'streams' => []]);

        return new class ($payload) extends MulticastScanner {
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
            ['program_id' => 1, 'nb_streams' => 2, 'tags' => ['service_name' => 'ESPN']],
            ['program_id' => 3, 'nb_streams' => 2, 'tags' => ['service_name' => 'CNN']],
        ];
    }

    public function test_scan_returns_programs(): void
    {
        $this->app->instance(MulticastScanner::class, $this->fakeScanner($this->programs()));

        $response = $this->actingAs($this->admin)
            ->withoutMiddleware()
            ->postJson('/admin/channels/multicast-scan/probe', ['url' => 'udp://@239.0.0.1:32768']);

        $response->assertOk()
            ->assertJsonCount(2, 'programs')
            ->assertJsonPath('programs.0.program_id', 1)
            ->assertJsonPath('programs.0.name', 'ESPN')
            ->assertJsonPath('programs.1.name', 'CNN');
    }

    public function test_scan_rejects_non_udp_url(): void
    {
        $this->app->instance(MulticastScanner::class, $this->fakeScanner([]));

        $this->actingAs($this->admin)
            ->withoutMiddleware()
            ->postJson('/admin/channels/multicast-scan/probe', ['url' => 'http://example.com/live.m3u8'])
            ->assertStatus(422)
            ->assertJsonPath('error', 'Only udp:// or rtp:// URLs are supported.');
    }

    public function test_scan_flags_already_existing_programs(): void
    {
        Channel::factory()->create([
            'stream_url'     => 'udp://@239.0.0.1:32768',
            'program_number' => 1,
        ]);

        $this->app->instance(MulticastScanner::class, $this->fakeScanner($this->programs()));

        $response = $this->actingAs($this->admin)
            ->withoutMiddleware()
            ->postJson('/admin/channels/multicast-scan/probe', ['url' => 'udp://@239.0.0.1:32768']);

        $response->assertOk()
            ->assertJsonPath('programs.0.already_exists', true)
            ->assertJsonPath('programs.1.already_exists', false);
    }

    public function test_import_creates_channels(): void
    {
        $response = $this->actingAs($this->admin)
            ->withoutMiddleware()
            ->postJson('/admin/channels/multicast-scan/import', [
                'url'      => 'udp://@239.0.0.1:32768',
                'programs' => [
                    ['program_id' => 1, 'name' => 'ESPN'],
                    ['program_id' => 3, 'name' => 'CNN'],
                ],
            ]);

        $response->assertOk()->assertJsonPath('imported', 2)->assertJsonPath('skipped', 0);

        $this->assertDatabaseHas('channels', [
            'name'           => 'ESPN',
            'stream_url'     => 'udp://@239.0.0.1:32768',
            'stream_type'    => 'udp',
            'program_number' => 1,
        ]);
        $this->assertDatabaseHas('channels', ['name' => 'CNN', 'program_number' => 3]);
    }

    public function test_import_skips_duplicates(): void
    {
        Channel::factory()->create([
            'stream_url'     => 'udp://@239.0.0.1:32768',
            'program_number' => 1,
        ]);

        $response = $this->actingAs($this->admin)
            ->withoutMiddleware()
            ->postJson('/admin/channels/multicast-scan/import', [
                'url'      => 'udp://@239.0.0.1:32768',
                'programs' => [
                    ['program_id' => 1, 'name' => 'ESPN'],
                    ['program_id' => 3, 'name' => 'CNN'],
                ],
            ]);

        $response->assertOk()->assertJsonPath('imported', 1)->assertJsonPath('skipped', 1);
        $this->assertDatabaseCount('channels', 2);
    }

    public function test_import_assigns_category(): void
    {
        $cat = ContentCategory::factory()->create();

        $this->actingAs($this->admin)
            ->withoutMiddleware()
            ->postJson('/admin/channels/multicast-scan/import', [
                'url'         => 'udp://@239.0.0.1:32768',
                'category_id' => $cat->id,
                'programs'    => [['program_id' => 5, 'name' => 'Fox Sports']],
            ])
            ->assertOk()
            ->assertJsonPath('imported', 1);

        $channel = Channel::where('program_number', 5)->first();
        $this->assertNotNull($channel);
        $this->assertTrue($channel->categories->contains($cat->id));
    }

    public function test_import_uses_custom_name(): void
    {
        $this->actingAs($this->admin)
            ->withoutMiddleware()
            ->postJson('/admin/channels/multicast-scan/import', [
                'url'      => 'udp://@239.0.0.1:32768',
                'programs' => [['program_id' => 7, 'name' => 'My Custom Name']],
            ])
            ->assertOk();

        $this->assertDatabaseHas('channels', ['name' => 'My Custom Name', 'program_number' => 7]);
    }

    public function test_import_stores_local_address(): void
    {
        $this->actingAs($this->admin)
            ->withoutMiddleware()
            ->postJson('/admin/channels/multicast-scan/import', [
                'url'        => 'udp://@239.0.0.1:32768',
                'local_addr' => '192.168.1.50',
                'programs'   => [['program_id' => 2, 'name' => 'Test']],
            ])
            ->assertOk();

        $this->assertDatabaseHas('channels', ['program_number' => 2, 'local_address' => '192.168.1.50']);
    }
}

<?php

namespace Tests\Feature;

use App\Models\AdminChannel\AdminChannel;
use App\Models\AdminChannel\MyChannelContent;
use App\Models\AdminChannel\MyChannelPlaylist;
use App\Models\License;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class MonitoringTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        License::forceCreate([
            'license_key'  => 'test-license-' . uniqid(),
            'hotel_name'   => 'Test Hotel',
            'device_type'  => License::DEVICE_TYPE_ADMIN_PANEL,
            'status'       => License::STATUS_ACTIVE,
            'license_type' => License::LICENSE_TYPE_PREMIUM,
            'max_devices'  => 10,
        ]);

        $this->admin = User::factory()->create(['is_admin' => true]);

        $this->withoutMiddleware(\App\Http\Middleware\VerifyCsrfToken::class);
    }

    private function makeChannel(string $name, bool $isMyChannel = true): AdminChannel
    {
        return AdminChannel::create([
            'channel_name'  => $name,
            'channel_slug'  => Str::slug($name) . '-' . uniqid(),
            'channel_type'  => 'admin',
            'is_my_channel' => $isMyChannel,
            'stream_type'   => 'hls',
            'created_by'    => $this->admin->id,
        ]);
    }

    private function makePlaylistItem(AdminChannel $channel, int $order, bool $active = true): MyChannelPlaylist
    {
        $content = MyChannelContent::create([
            'channel_id'  => $channel->id,
            'title'       => "Clip {$order}",
            'file_name'   => "clip-{$order}.mp4",
            'file_path'   => "clips/clip-{$order}.mp4",
            'duration'    => 60,
            'uploaded_by' => $this->admin->id,
            'is_active'   => true,
        ]);

        return MyChannelPlaylist::create([
            'channel_id'  => $channel->id,
            'content_id'  => $content->id,
            'order_index' => $order,
            'is_active'   => $active,
        ]);
    }

    public function test_metrics_endpoint_returns_system_and_channel_payload(): void
    {
        $response = $this->actingAs($this->admin)
            ->getJson('/admin/monitoring/metrics')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'system' => ['hostname', 'cpu_usage', 'memory_usage', 'disk_usage', 'load', 'cores', 'uptime', 'disks', 'nics', 'history'],
                    'channels',
                    'channels_summary' => ['total', 'running', 'stalled', 'stopped', 'playlist_items'],
                ],
            ]);

        $system = $response->json('data.system');

        $this->assertIsArray($system['disks']);
        $this->assertIsArray($system['nics']);
        $this->assertIsArray($system['history']);
        $this->assertGreaterThan(0, (int) $system['cores']);
    }

    public function test_channels_are_reported_with_playlist_counts_and_state(): void
    {
        $channel = $this->makeChannel('Lobby TV');
        $this->makePlaylistItem($channel, 1, true);
        $this->makePlaylistItem($channel, 2, false);

        $this->makeChannel('Pool TV');

        // Non My-Channel rows must stay out of the monitoring list.
        $this->makeChannel('External Feed', false);

        $channels = $this->actingAs($this->admin)
            ->getJson('/admin/monitoring/metrics')
            ->assertOk()
            ->json('data.channels');

        $this->assertCount(2, $channels);

        $lobby = collect($channels)->firstWhere('name', 'Lobby TV');

        $this->assertNotNull($lobby);
        $this->assertSame(2, $lobby['playlist_items']);
        $this->assertSame(1, $lobby['active_playlist_items']);
        $this->assertArrayHasKey('state', $lobby);
        $this->assertContains($lobby['state'], ['running', 'stopped', 'stalled']);
        // Nothing is playing in the test environment.
        $this->assertSame('stopped', $lobby['state']);
    }

    public function test_channels_summary_counts_playlist_items(): void
    {
        $channel = $this->makeChannel('Lobby TV');
        $this->makePlaylistItem($channel, 1, true);

        $summary = $this->actingAs($this->admin)
            ->getJson('/admin/monitoring/metrics')
            ->assertOk()
            ->json('data.channels_summary');

        $this->assertSame(1, $summary['total']);
        $this->assertSame(1, $summary['playlist_items']);
        $this->assertSame(1, $summary['stopped']);
        $this->assertSame(0, $summary['running']);
    }

    public function test_monitoring_page_renders_for_admin(): void
    {
        $this->actingAs($this->admin)
            ->get('/admin/monitoring')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Admin/Monitoring/Index'));
    }

    public function test_moderator_with_my_channels_can_open_monitoring(): void
    {
        $role = Role::create([
            'name'        => 'moderator',
            'label'       => 'Moderator',
            'permissions' => ['my_channels'],
        ]);

        $moderator = User::factory()->create(['role' => 'moderator']);
        $moderator->roles()->attach($role->id);
        $moderator->updateFlagsFromRoles();

        $this->actingAs($moderator)
            ->get('/admin/monitoring')
            ->assertOk();
    }

    public function test_each_poll_appends_a_sample_to_the_history_window(): void
    {
        $this->actingAs($this->admin)->getJson('/admin/monitoring/metrics')->assertOk();
        $afterFirst = $this->actingAs($this->admin)->getJson('/admin/monitoring/metrics')->json('data.system.history');

        $this->assertNotEmpty($afterFirst);
        $this->assertArrayHasKey('cpu', $afterFirst[array_key_last($afterFirst)]);
        $this->assertArrayHasKey('nics', $afterFirst[array_key_last($afterFirst)]);
    }
}

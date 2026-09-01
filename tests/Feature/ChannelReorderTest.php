<?php

namespace Tests\Feature;

use App\Models\AdminChannel\AdminChannel;
use App\Models\Channel;
use App\Models\License;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChannelReorderTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        License::create([
            'license_key'    => 'test-license-' . uniqid(),
            'status'         => License::STATUS_ACTIVE,
            'license_type'   => 'standard',
            'max_devices'    => 10,
        ]);

        $this->admin = User::factory()->create([
            'is_admin' => true,
        ]);

        $this->withoutMiddleware(\App\Http\Middleware\VerifyCsrfToken::class);
    }

    public function test_reorder_assigns_sequential_channel_numbers_top_to_bottom(): void
    {
        $a = Channel::factory()->create(['channel_number' => 1, 'is_active' => true]);
        $b = Channel::factory()->create(['channel_number' => 2, 'is_active' => true]);
        $c = Channel::factory()->create(['channel_number' => 3, 'is_active' => true]);

        $this->actingAs($this->admin)
            ->putJson('/admin/channels/reorder', [
                'items' => [
                    ['id' => $c->id, 'type' => 'channel'],
                    ['id' => $a->id, 'type' => 'channel'],
                    ['id' => $b->id, 'type' => 'channel'],
                ],
            ])
            ->assertOk()
            ->assertJson(['message' => 'Channel order updated successfully.']);

        $this->assertDatabaseHas('channels', ['id' => $c->id, 'channel_number' => 1]);
        $this->assertDatabaseHas('channels', ['id' => $a->id, 'channel_number' => 2]);
        $this->assertDatabaseHas('channels', ['id' => $b->id, 'channel_number' => 3]);
    }

    public function test_reorder_handles_mixed_channel_and_admin_channel_types(): void
    {
        $plain = Channel::factory()->create(['channel_number' => 1, 'is_active' => true]);
        $admin = AdminChannel::create([
            'channel_name'   => 'Admin Ch',
            'channel_number' => '2',
            'created_by'     => $this->admin->id,
        ]);

        $this->actingAs($this->admin)
            ->putJson('/admin/channels/reorder', [
                'items' => [
                    ['id' => $admin->id, 'type' => 'admin_channel'],
                    ['id' => $plain->id, 'type' => 'channel'],
                ],
            ])
            ->assertOk();

        $this->assertDatabaseHas('admin_channels', ['id' => $admin->id, 'channel_number' => '1']);
        $this->assertDatabaseHas('channels', ['id' => $plain->id, 'channel_number' => 2]);
    }

    public function test_all_channels_endpoint_returns_channels_sorted_by_channel_number(): void
    {
        Channel::factory()->create(['channel_number' => 3, 'name' => 'C Ch', 'is_active' => true]);
        Channel::factory()->create(['channel_number' => 1, 'name' => 'A Ch', 'is_active' => true]);
        Channel::factory()->create(['channel_number' => 2, 'name' => 'B Ch', 'is_active' => true]);

        $response = $this->actingAs($this->admin)->getJson('/admin/channels/all/list');

        $response->assertOk();
        $names = collect($response->json())->pluck('name')->all();
        $expected = ['A Ch', 'B Ch', 'C Ch'];
        $this->assertSame($expected, array_values(array_intersect($expected, $names)));
    }
}

<?php

namespace Tests\Feature;

use App\Models\AdminChannel\AdminChannel;
use App\Models\License;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminModuleAccessTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $moderator;

    protected function setUp(): void
    {
        parent::setUp();

        License::create([
            'license_key'  => 'test-license-' . uniqid(),
            'status'       => License::STATUS_ACTIVE,
            'license_type' => 'standard',
            'max_devices'  => 10,
        ]);

        $this->admin = User::factory()->create(['is_admin' => true]);

        $moderatorRole = Role::create([
            'name'        => 'moderator',
            'label'       => 'Moderator',
            'permissions' => ['my_channels', 'content_management'],
        ]);

        $this->moderator = User::factory()->create(['role' => 'moderator']);
        $this->moderator->roles()->attach($moderatorRole->id);
        $this->moderator->updateFlagsFromRoles();

        $this->withoutMiddleware(\App\Http\Middleware\VerifyCsrfToken::class);
    }

    private function makeChannel(string $name): AdminChannel
    {
        return AdminChannel::create([
            'channel_name'  => $name,
            'channel_slug'  => Str::slug($name) . '-' . uniqid(),
            'channel_type'  => 'admin',
            'is_my_channel' => true,
            'stream_type'   => 'hls',
            'created_by'    => $this->admin->id,
        ]);
    }

    public function test_admin_can_access_all_admin_modules(): void
    {
        $this->actingAs($this->admin)
            ->get('/admin/dashboard')
            ->assertOk();

        $this->actingAs($this->admin)
            ->get('/admin/users')
            ->assertOk();

        $this->actingAs($this->admin)
            ->get('/admin/channels/admin')
            ->assertOk();
    }

    public function test_moderator_can_access_my_channels_module(): void
    {
        $assigned = $this->makeChannel('Mod Access');
        $this->moderator->managedChannels()->sync([$assigned->id]);

        $this->actingAs($this->moderator)
            ->get('/admin/channels/admin')
            ->assertOk();
    }

    public function test_moderator_is_blocked_from_other_admin_modules(): void
    {
        $forbidden = [
            '/admin/dashboard',
            '/admin/users',
            '/admin/clients',
            '/admin/channels',
            '/admin/channels/order',
            '/admin/channels/import',
            '/admin/roles',
            '/admin/settings',
        ];

        foreach ($forbidden as $path) {
            $this->actingAs($this->moderator)
                ->get($path)
                ->assertForbidden();
        }
    }

    public function test_moderator_can_open_an_assigned_channel_but_not_other_admin_channels(): void
    {
        $assigned = $this->makeChannel('Mod Assigned');
        $other    = $this->makeChannel('Mod Other');
        $this->moderator->managedChannels()->sync([$assigned->id]);

        $this->actingAs($this->moderator)
            ->get("/admin/channels/admin/{$assigned->channel_slug}")
            ->assertOk();

        $this->actingAs($this->moderator)
            ->get("/admin/channels/admin/{$other->channel_slug}")
            ->assertNotFound();
    }
}
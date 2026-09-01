<?php

namespace Tests\Feature;

use App\Models\AdminChannel\AdminChannel;
use App\Models\License;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class RoleBasedAccessTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        License::create([
            'license_key'  => 'test-license-' . uniqid(),
            'status'       => License::STATUS_ACTIVE,
            'license_type' => 'standard',
            'max_devices'  => 10,
        ]);

        $this->admin = User::factory()->create([
            'is_admin' => true,
        ]);

        $this->withoutMiddleware(\App\Http\Middleware\VerifyCsrfToken::class);
    }

    private function makeRole(string $name, array $permissions = []): Role
    {
        return Role::create([
            'name'        => $name,
            'label'       => ucfirst(str_replace('_', ' ', $name)),
            'permissions' => $permissions,
        ]);
    }

    private function makeChannel(string $name, bool $isMyChannel = true): AdminChannel
    {
        return AdminChannel::create([
            'channel_name'   => $name,
            'channel_slug'   => Str::slug($name) . '-' . uniqid(),
            'channel_type'   => 'admin',
            'is_my_channel'  => $isMyChannel,
            'stream_type'    => 'hls',
            'created_by'     => $this->admin->id,
        ]);
    }

    private function channelUrl(AdminChannel $channel): string
    {
        return "/admin/channels/admin/{$channel->channel_slug}";
    }

    public function test_admin_can_create_update_and_delete_roles(): void
    {
        $this->actingAs($this->admin)
            ->postJson('/admin/roles', [
                'name'        => 'channel_manager',
                'label'       => 'Channel Manager',
                'description' => 'Manages assigned channels',
                'permissions' => ['my_channels'],
            ])
            ->assertRedirect(route('admin.roles.index'));

        $role = Role::where('name', 'channel_manager')->first();
        $this->assertNotNull($role);
        $this->assertSame(['my_channels'], $role->permissions);

        $this->actingAs($this->admin)
            ->putJson("/admin/roles/{$role->id}", [
                'name'        => 'channel_manager',
                'label'       => 'Channel Manager',
                'permissions' => ['my_channels', 'view_only'],
            ])
            ->assertRedirect(route('admin.roles.index'));

        $this->assertEqualsCanonicalizing(['my_channels', 'view_only'], $role->fresh()->permissions);

        $member = User::factory()->create();
        $member->roles()->attach($role->id);

        $this->actingAs($this->admin)
            ->deleteJson("/admin/roles/{$role->id}")
            ->assertRedirect(route('admin.roles.index'));

        $this->assertDatabaseMissing('roles', ['id' => $role->id]);
        $this->assertDatabaseMissing('role_user', ['role_id' => $role->id, 'user_id' => $member->id]);
    }

    public function test_assigning_roles_via_user_update_syncs_admin_flags(): void
    {
        $fullRole = $this->makeRole('super_admin', ['full_access']);
        $client = User::factory()->create(['role' => 'client']);

        $this->actingAs($this->admin)
            ->putJson("/admin/users/{$client->id}", [
                'role_ids' => [$fullRole->id],
            ])
            ->assertRedirect(route('admin.users.index'));

        $client->refresh();

        $this->assertTrue($client->roles()->where('roles.id', $fullRole->id)->exists());
        $this->assertTrue($client->is_admin);
        $this->assertTrue($client->canManageAllMyChannels());
    }

    public function test_non_full_access_user_sees_all_admin_my_channels(): void
    {
        $role = $this->makeRole('channel_manager', ['my_channels']);
        $manager = User::factory()->create(['role' => 'client']);
        $manager->roles()->attach($role->id);
        $manager->updateFlagsFromRoles();

        $this->assertFalse($manager->canManageAllMyChannels());

        $a = $this->makeChannel('My Channel A');
        $b = $this->makeChannel('My Channel B');

        $response = $this->actingAs($manager)
            ->getJson('/admin/channels/admin')
            ->assertOk();

        $names = collect($response->json('data.data') ?? $response->json('data') ?? [])
            ->pluck('channel_name')
            ->all();

        $this->assertContains('My Channel A', $names);
        $this->assertContains('My Channel B', $names);
    }

    public function test_moderator_is_not_an_admin_but_manages_all_my_channels(): void
    {
        $role = $this->makeRole('moderator', ['my_channels', 'content_management']);
        $moderator = User::factory()->create(['role' => 'moderator']);
        $moderator->roles()->attach($role->id);
        $moderator->updateFlagsFromRoles();

        $this->assertFalse($moderator->is_admin);
        $this->assertFalse($moderator->canManageAllMyChannels());
        $this->assertTrue($moderator->hasAdminPanelAccess());

        $this->makeChannel('Mod Channel One');
        $this->makeChannel('Mod Channel Two');

        $response = $this->actingAs($moderator)
            ->getJson('/admin/channels/admin')
            ->assertOk();

        $names = collect($response->json('data.data') ?? $response->json('data') ?? [])
            ->pluck('channel_name')
            ->all();

        $this->assertContains('Mod Channel One', $names);
        $this->assertContains('Mod Channel Two', $names);
    }

    public function test_route_binding_allows_any_my_channel_but_blocks_other_channel_types(): void
    {
        $role = $this->makeRole('channel_manager', ['my_channels']);
        $manager = User::factory()->create(['role' => 'client']);
        $manager->roles()->attach($role->id);
        $manager->updateFlagsFromRoles();

        $one = $this->makeChannel('My Channel One');
        $two = $this->makeChannel('My Channel Two');
        $regular = $this->makeChannel('Regular Channel', false);

        $this->actingAs($manager)
            ->getJson($this->channelUrl($one))
            ->assertOk();

        $this->actingAs($manager)
            ->getJson($this->channelUrl($two))
            ->assertOk();

        $this->actingAs($manager)
            ->getJson($this->channelUrl($regular))
            ->assertNotFound();
    }

    public function test_admin_sees_all_channels_regardless_of_assignment(): void
    {
        $channel = $this->makeChannel('Any Channel');

        $this->actingAs($this->admin)
            ->getJson($this->channelUrl($channel))
            ->assertOk();
    }

    public function test_admin_can_assign_channels_to_user_and_update_access(): void
    {
        $client = User::factory()->create(['role' => 'client']);
        $a = $this->makeChannel('Ch A');
        $b = $this->makeChannel('Ch B');

        $this->actingAs($this->admin)
            ->putJson(route('admin.users.channels.update', $client->id), [
                'channel_ids' => [$a->id, $b->id],
            ])
            ->assertRedirect(route('admin.users.channels', $client->id));

        $this->assertCount(2, $client->managedChannels()->get());

        $this->actingAs($this->admin)
            ->putJson(route('admin.users.channels.update', $client->id), [
                'channel_ids' => [$a->id],
            ])
            ->assertRedirect(route('admin.users.channels', $client->id));

        $this->assertTrue($client->managedChannels()->whereKey($a->id)->exists());
        $this->assertFalse($client->managedChannels()->whereKey($b->id)->exists());
    }

    public function test_view_only_user_can_open_my_channels_but_not_regular_channels(): void
    {
        $role = $this->makeRole('viewer', ['view_only']);
        $viewer = User::factory()->create(['role' => 'client']);
        $viewer->roles()->attach($role->id);
        $viewer->updateFlagsFromRoles();

        $channel  = $this->makeChannel('Vip Channel');
        $regular  = $this->makeChannel('Regular Channel', false);

        $this->assertFalse($viewer->canManageAllMyChannels());

        $this->actingAs($viewer)
            ->getJson($this->channelUrl($channel))
            ->assertOk();

        $this->actingAs($viewer)
            ->getJson($this->channelUrl($regular))
            ->assertNotFound();
    }
}
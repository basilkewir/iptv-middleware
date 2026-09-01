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

    private function makeChannel(string $name): AdminChannel
    {
        return AdminChannel::create([
            'channel_name'   => $name,
            'channel_slug'   => Str::slug($name) . '-' . uniqid(),
            'channel_type'   => 'admin',
            'is_my_channel'  => true,
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

    public function test_non_full_access_user_only_sees_assigned_channels(): void
    {
        $role = $this->makeRole('channel_manager', ['my_channels']);
        $manager = User::factory()->create(['role' => 'client']);
        $manager->roles()->attach($role->id);
        $manager->updateFlagsFromRoles();

        $assigned = $this->makeChannel('Assigned One');
        $other    = $this->makeChannel('Not Assigned');
        $manager->managedChannels()->sync([$assigned->id]);

        $response = $this->actingAs($manager)
            ->getJson('/admin/channels/admin')
            ->assertOk();

        $names = collect($response->json('data.data') ?? $response->json('data') ?? [])
            ->pluck('channel_name')
            ->all();

        $this->assertContains('Assigned One', $names);
        $this->assertNotContains('Not Assigned', $names);
    }

    public function test_route_binding_blocks_unassigned_channel_for_non_full_access_user(): void
    {
        $role = $this->makeRole('channel_manager', ['my_channels']);
        $manager = User::factory()->create(['role' => 'client']);
        $manager->roles()->attach($role->id);
        $manager->updateFlagsFromRoles();

        $assigned = $this->makeChannel('Assigned One');
        $other    = $this->makeChannel('Not Assigned');
        $manager->managedChannels()->sync([$assigned->id]);

        $this->actingAs($manager)
            ->getJson($this->channelUrl($assigned))
            ->assertOk();

        $this->actingAs($manager)
            ->getJson($this->channelUrl($other))
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

    public function test_role_permission_gates_managed_channel_access(): void
    {
        $role = $this->makeRole('viewer', ['view_only']);
        $viewer = User::factory()->create(['role' => 'client']);
        $viewer->roles()->attach($role->id);
        $viewer->updateFlagsFromRoles();

        $channel = $this->makeChannel('Vip Channel');
        $viewer->managedChannels()->sync([$channel->id]);

        $this->assertFalse($viewer->canManageAllMyChannels());

        $this->actingAs($viewer)
            ->getJson($this->channelUrl($channel))
            ->assertOk();

        $this->actingAs($viewer)
            ->getJson($this->channelUrl($this->makeChannel('Other')))
            ->assertNotFound();
    }
}
<?php

namespace Tests\Feature;

use App\Models\AdminChannel\AdminChannel;
use App\Models\License;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * The `channel_vod_manager` role is the operator-facing role that may only
 * touch My Channels and VOD — nothing else in the admin panel.
 */
class ChannelVodManagerRoleTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();

        // `device_type` is NOT NULL in the schema but missing from
        // License::$fillable, so mass-assignment would drop it.
        License::forceCreate([
            'license_key'  => 'test-license-' . uniqid(),
            'hotel_name'   => 'Test Hotel',
            'device_type'  => License::DEVICE_TYPE_ADMIN_PANEL,
            'status'       => License::STATUS_ACTIVE,
            'license_type' => License::LICENSE_TYPE_PREMIUM,
            'max_devices'  => 10,
        ]);

        $this->admin = User::factory()->create(['is_admin' => true]);

        $this->seed(RoleSeeder::class);

        $role = Role::where('name', 'channel_vod_manager')->firstOrFail();

        $this->manager = User::factory()->create(['role' => 'client']);
        $this->manager->roles()->attach($role->id);
        $this->manager->updateFlagsFromRoles();

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

    public function test_role_is_seeded_with_exactly_my_channels_and_vod_permissions(): void
    {
        $role = Role::where('name', 'channel_vod_manager')->first();

        $this->assertNotNull($role);
        $this->assertEqualsCanonicalizing(['my_channels', 'vod_management'], $role->permissions);
    }

    public function test_role_migration_creates_the_role_when_missing(): void
    {
        Role::where('name', 'channel_vod_manager')->delete();

        $migration = require database_path('migrations/2026_09_25_000001_add_channel_vod_manager_role.php');
        $migration->up();

        $role = Role::where('name', 'channel_vod_manager')->first();

        $this->assertNotNull($role);
        $this->assertEqualsCanonicalizing(['my_channels', 'vod_management'], $role->permissions);
    }

    public function test_role_migration_is_idempotent_and_does_not_downgrade_permissions(): void
    {
        $role = Role::where('name', 'channel_vod_manager')->firstOrFail();
        $role->update(['permissions' => ['my_channels', 'vod_management', 'view_only']]);

        $migration = require database_path('migrations/2026_09_25_000001_add_channel_vod_manager_role.php');
        $migration->up();
        $migration->up();

        $this->assertEqualsCanonicalizing(
            ['my_channels', 'vod_management', 'view_only'],
            $role->fresh()->permissions
        );
    }

    public function test_manager_is_not_an_admin_and_has_no_full_access(): void
    {
        $this->assertFalse($this->manager->is_admin);
        $this->assertFalse($this->manager->is_reseller);
        $this->assertFalse($this->manager->canManageAllMyChannels());
        $this->assertTrue($this->manager->hasAdminPanelAccess());
        $this->assertTrue($this->manager->hasPermission('my_channels'));
        $this->assertTrue($this->manager->hasPermission('vod_management'));
        $this->assertFalse($this->manager->hasPermission('user_management'));
        $this->assertFalse($this->manager->hasPermission('role_management'));
    }

    public function test_manager_can_open_my_channels_vod_and_dashboard(): void
    {
        $this->actingAs($this->manager)->get('/admin/channels/admin')->assertOk();
        $this->actingAs($this->manager)->get('/admin/vod')->assertOk();
        $this->actingAs($this->manager)->get('/admin/dashboard')->assertOk();
    }

    public function test_manager_is_redirected_away_from_every_other_admin_module(): void
    {
        $blocked = [
            '/admin/users',
            '/admin/users/create',
            '/admin/clients',
            '/admin/roles',
            '/admin/channels',
            '/admin/channels/order',
            '/admin/channels/import',
            '/admin/settings',
            '/admin/bouquets',
            '/admin/categories',
            '/admin/servers',
            '/admin/invoices',
            '/admin/subscriptions/packages',
            '/admin/reports',
            '/admin/transcoding',
        ];

        foreach ($blocked as $path) {
            $this->actingAs($this->manager)
                ->get($path)
                ->assertRedirect('/admin/channels/admin');
        }
    }

    public function test_manager_is_forbidden_with_json_from_every_other_admin_module(): void
    {
        foreach (['/admin/users', '/admin/roles', '/admin/settings', '/admin/channels'] as $path) {
            $this->actingAs($this->manager)
                ->getJson($path)
                ->assertForbidden();
        }
    }

    public function test_manager_can_see_my_channels_but_not_regular_channels(): void
    {
        $mine    = $this->makeChannel('My Channel');
        $regular = $this->makeChannel('Regular Channel', false);

        $this->actingAs($this->manager)
            ->get('/admin/channels/admin/' . $mine->channel_slug)
            ->assertOk();

        $this->actingAs($this->manager)
            ->get('/admin/channels/admin/' . $regular->channel_slug)
            ->assertNotFound();
    }

    public function test_manager_lands_on_my_channels_after_login(): void
    {
        $this->from('/admin/dashboard')
            ->post('/login', [
                'username' => $this->manager->username,
                'password' => 'password',
            ])
            ->assertRedirect('/admin/channels/admin')
            ->assertSessionHasNoErrors();

        $this->assertAuthenticatedAs($this->manager);
    }

    public function test_manager_permissions_list_drives_the_sidebar_menu(): void
    {
        // AdminLayout.vue gates items on `permissions` + `can_manage_all`:
        // only Dashboard, My Channels and VOD are rendered for this role.
        $permissions = $this->manager->permissionsList();

        $this->assertEqualsCanonicalizing(['my_channels', 'vod_management'], $permissions);

        $menu = $this->manager->permissionsList();

        foreach (['my_channels', 'vod_management'] as $granted) {
            $this->assertContains($granted, $menu);
        }

        foreach (['user_management', 'role_management', 'full_access'] as $denied) {
            $this->assertNotContains($denied, $menu);
        }
    }

    /**
     * Users → Create User → Admin User tab. The role select posts both
     * `role` (legacy string column) and `role_ids` (pivot); both used to be
     * rejected because `channel_vod_manager` was missing from the allow-list.
     */
    public function test_admin_can_create_a_user_with_this_role_from_the_user_form(): void
    {
        $role = Role::where('name', 'channel_vod_manager')->firstOrFail();

        $response = $this->actingAs($this->admin)->post('/admin/users', [
            'username'              => 'golden.manager',
            'email'                 => 'golden.manager@example.test',
            'password'              => 'SecretPass123',
            'password_confirmation' => 'SecretPass123',
            'first_name'            => 'Golden',
            'last_name'             => 'Manager',
            'role'                  => 'channel_vod_manager',
            'role_ids'              => [$role->id],
            'is_active'             => true,
        ]);

        $response->assertRedirect(route('admin.users.index'))
            ->assertSessionHas('success');

        $user = User::where('username', 'golden.manager')->firstOrFail();

        $this->assertFalse($user->is_admin);
        $this->assertFalse($user->is_reseller);
        $this->assertTrue($user->hasAdminPanelAccess());
        $this->assertTrue($user->hasPermission('my_channels'));
        $this->assertTrue($user->hasPermission('vod_management'));
        $this->assertFalse($user->hasPermission('user_management'));
        $this->assertFalse($user->canManageAllMyChannels());
        $this->assertEqualsCanonicalizing(['my_channels', 'vod_management'], $user->permissionsList());
    }

    public function test_the_role_name_is_accepted_when_updating_a_user(): void
    {
        $role = Role::where('name', 'channel_vod_manager')->firstOrFail();
        $user = User::factory()->create(['role' => 'client']);

        $this->actingAs($this->admin)
            ->put("/admin/users/{$user->id}", [
                'role'     => 'channel_vod_manager',
                'role_ids' => [$role->id],
            ])
            ->assertRedirect(route('admin.users.index'))
            ->assertSessionHasNoErrors();

        $this->assertTrue($user->fresh()->hasPermission('my_channels'));
    }
}

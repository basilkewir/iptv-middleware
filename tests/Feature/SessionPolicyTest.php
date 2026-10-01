<?php

namespace Tests\Feature;

use App\Http\Middleware\EnforceSessionPolicy;
use App\Models\License;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SessionPolicyTest extends TestCase
{
    use RefreshDatabase;

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

        $this->withoutMiddleware(\App\Http\Middleware\VerifyCsrfToken::class);
    }

    protected function admin(): User
    {
        return User::factory()->create(['is_admin' => true]);
    }

    protected function withPolicyCookie(int $lastRequestAt, int $rememberedAt = 0): static
    {
        // Note: withCookie() encrypts values automatically (prefix + APP_KEY).
        return $this->withCookie(
            EnforceSessionPolicy::POLICY_COOKIE,
            $lastRequestAt . '|' . $rememberedAt
        );
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/admin/dashboard')->assertRedirect(route('login'));
    }

    public function test_active_session_passes_policy_check(): void
    {
        $this->actingAs($this->admin())
            ->withSession([
                'session_started_at' => now()->timestamp,
                'last_request_at' => now()->timestamp,
            ])
            ->get('/dashboard')
            ->assertRedirect(route('admin.dashboard'));
    }

    public function test_idle_timeout_expires_session_and_blocks_dashboard(): void
    {
        SystemSetting::set('session_idle_timeout', '30', 'users');

        $this->actingAs($this->admin())
            ->withSession([
                'session_started_at' => now()->subHour()->timestamp,
                'last_request_at' => now()->subMinutes(31)->timestamp,
            ])
            ->get('/admin/dashboard')
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('general');

        $this->assertGuest('web');
    }

    public function test_idle_timeout_allows_recent_activity(): void
    {
        SystemSetting::set('session_idle_timeout', '30', 'users');

        $this->actingAs($this->admin())
            ->withSession([
                'session_started_at' => now()->subHour()->timestamp,
                'last_request_at' => now()->subMinutes(29)->timestamp,
            ])
            ->get('/dashboard')
            ->assertRedirect(route('admin.dashboard'));
    }

    public function test_idle_timeout_zero_disables_idle_check(): void
    {
        SystemSetting::set('session_idle_timeout', '0', 'users');

        $this->actingAs($this->admin())
            ->withSession([
                'session_started_at' => now()->subHour()->timestamp,
                'last_request_at' => now()->subHours(5)->timestamp,
            ])
            ->get('/dashboard')
            ->assertRedirect(route('admin.dashboard'));
    }

    public function test_session_lifetime_expires_even_when_recently_active(): void
    {
        SystemSetting::set('session_idle_timeout', '0', 'users');
        SystemSetting::set('session_lifetime', '60', 'users');

        $this->actingAs($this->admin())
            ->withSession([
                'session_started_at' => now()->subMinutes(61)->timestamp,
                'last_request_at' => now()->timestamp,
            ])
            ->get('/admin/dashboard')
            ->assertRedirect(route('login'));

        $this->assertGuest('web');
    }

    public function test_expired_session_returns_401_json(): void
    {
        SystemSetting::set('session_idle_timeout', '30', 'users');

        $this->actingAs($this->admin())
            ->withSession([
                'session_started_at' => now()->subHour()->timestamp,
                'last_request_at' => now()->subMinutes(31)->timestamp,
            ])
            ->getJson('/admin/dashboard')
            ->assertStatus(401)
            ->assertJson(['message' => 'Session expired.']);
    }

    public function test_remember_me_duration_expires_ancient_remembered_login(): void
    {
        SystemSetting::set('remember_me_duration', '43200', 'users');

        $this->withPolicyCookie(
            now()->timestamp,
            now()->subDays(31)->timestamp
        )->actingAs($this->admin())
            ->get('/admin/dashboard')
            ->assertRedirect(route('login'));

        $this->assertGuest('web');
    }

    public function test_remembered_login_within_duration_is_allowed(): void
    {
        SystemSetting::set('remember_me_duration', '43200', 'users');

        $this->withPolicyCookie(
            now()->timestamp,
            now()->subDays(7)->timestamp
        )->actingAs($this->admin())
            ->get('/dashboard')
            ->assertRedirect(route('admin.dashboard'));
    }

    public function test_remembered_login_without_policy_record_is_forced_to_relogin(): void
    {
        // A recaller cookie resurrected a session with no policy cookie to
        // verify its age — must not silently grant access.
        $admin = User::factory()->create([
            'is_admin' => true,
            'remember_token' => 'remember-token-value',
        ]);

        $recallerName = $this->app['auth']->guard('web')->getRecallerName();

        $this->withCookie($recallerName, $admin->id . '|remember-token-value|' . $admin->password)
            ->get('/admin/dashboard')
            ->assertRedirect(route('login'));
    }
}

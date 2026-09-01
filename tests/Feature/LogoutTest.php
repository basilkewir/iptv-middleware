<?php

namespace Tests\Feature;

use App\Models\License;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class LogoutTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        License::create([
            'license_key'  => 'test-license-' . uniqid(),
            'status'       => License::STATUS_ACTIVE,
            'license_type' => 'standard',
            'max_devices'  => 10,
        ]);

        $this->withoutMiddleware(\App\Http\Middleware\VerifyCsrfToken::class);
    }

    public function test_web_logout_logs_out_session_and_blocks_panel_access(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)
            ->post('/logout')
            ->assertRedirect('/');

        $this->assertGuest('web');

        $this->get('/admin/dashboard')->assertRedirect(route('login'));
    }

    public function test_api_logout_deletes_token_and_returns_json(): void
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user);

        $token = $user->currentAccessToken();
        $this->assertNotNull($token);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/auth/logout')
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $token->id]);
    }

    public function test_admin_layout_logout_route_accepts_inertia_post(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)
            ->post('/logout', [], ['X-Inertia' => 'true', 'Accept' => 'text/html, application/xhtml+xml'])
            ->assertRedirect('/');

        $this->assertGuest('web');
    }
}
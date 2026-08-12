<?php

namespace Tests\Feature;

use App\Models\PaymentMethod;
use App\Models\Subscription;
use App\Models\SubscriptionHistory;
use App\Models\SubscriptionPackage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubscriptionTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create([
            'is_active' => true,
        ]);
    }

    public function test_user_can_list_available_packages(): void
    {
        SubscriptionPackage::factory()->count(3)->create(['is_active' => true]);

        $response = $this->actingAs($this->user)->getJson('/api/v1/subscription/plans');

        $response->assertOk();
        $response->assertJsonCount(3, 'data');
    }

    public function test_user_can_subscribe_to_a_package(): void
    {
        $package = SubscriptionPackage::factory()->create([
            'is_active' => true,
            'price' => 19.99,
            'duration_days' => 30,
        ]);

        $paymentMethod = PaymentMethod::factory()->create(['is_active' => true]);

        $response = $this->actingAs($this->user)->postJson('/api/v1/subscription/subscribe', [
            'package_id' => $package->id,
            'payment_method_id' => $paymentMethod->id,
        ]);

        $response->assertCreated();
        $response->assertJson([
            'success' => true,
            'message' => 'Subscription created successfully.',
        ]);

        $this->assertDatabaseHas('subscriptions', [
            'user_id' => $this->user->id,
            'subscription_package_id' => $package->id,
            'status' => 'active',
        ]);
    }

    public function test_user_with_active_subscription_cannot_subscribe_again(): void
    {
        $package = SubscriptionPackage::factory()->create(['is_active' => true]);
        $paymentMethod = PaymentMethod::factory()->create(['is_active' => true]);

        Subscription::factory()->create([
            'user_id' => $this->user->id,
            'subscription_package_id' => $package->id,
            'status' => 'active',
            'start_date' => now(),
            'end_date' => now()->addDays(30),
        ]);

        $response = $this->actingAs($this->user)->postJson('/api/v1/subscription/subscribe', [
            'package_id' => $package->id,
            'payment_method_id' => $paymentMethod->id,
        ]);

        $response->assertStatus(409);
        $response->assertJson([
            'success' => false,
            'message' => 'You already have an active subscription.',
        ]);
    }

    public function test_user_can_view_current_subscription(): void
    {
        $package = SubscriptionPackage::factory()->create(['is_active' => true]);

        Subscription::factory()->create([
            'user_id' => $this->user->id,
            'subscription_package_id' => $package->id,
            'status' => 'active',
            'start_date' => now(),
            'end_date' => now()->addDays(30),
        ]);

        $response = $this->actingAs($this->user)->getJson('/api/v1/subscription');

        $response->assertOk();
        $response->assertJsonPath('data.subscription.status', 'active');
    }

    public function test_user_without_subscription_gets_null(): void
    {
        $response = $this->actingAs($this->user)->getJson('/api/v1/subscription');

        $response->assertOk();
        $response->assertJsonPath('data.subscription', null);
    }

    public function test_user_can_renew_subscription(): void
    {
        $package = SubscriptionPackage::factory()->create([
            'is_active' => true,
            'duration_days' => 30,
        ]);

        $paymentMethod = PaymentMethod::factory()->create(['is_active' => true]);

        $subscription = Subscription::factory()->create([
            'user_id' => $this->user->id,
            'subscription_package_id' => $package->id,
            'status' => 'active',
            'start_date' => now(),
            'end_date' => now()->addDays(30),
        ]);

        $response = $this->actingAs($this->user)->postJson('/api/v1/subscription/renew', [
            'subscription_id' => $subscription->id,
            'payment_method_id' => $paymentMethod->id,
        ]);

        $response->assertOk();
        $response->assertJson([
            'success' => true,
            'message' => 'Subscription renewed successfully.',
        ]);
    }

    public function test_user_can_view_subscription_history(): void
    {
        $package = SubscriptionPackage::factory()->create(['is_active' => true]);

        $subscription = Subscription::factory()->create([
            'user_id' => $this->user->id,
            'subscription_package_id' => $package->id,
        ]);

        SubscriptionHistory::factory()->create([
            'subscription_id' => $subscription->id,
            'action' => 'created',
        ]);

        $response = $this->actingAs($this->user)->getJson('/api/v1/subscription/history');

        $response->assertOk();
    }

    public function test_subscribe_validates_required_fields(): void
    {
        $response = $this->actingAs($this->user)->postJson('/api/v1/subscription/subscribe', []);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['package_id', 'payment_method_id']);
    }

    public function test_subscription_creates_history_record(): void
    {
        $package = SubscriptionPackage::factory()->create(['is_active' => true]);
        $paymentMethod = PaymentMethod::factory()->create(['is_active' => true]);

        $this->actingAs($this->user)->postJson('/api/v1/subscription/subscribe', [
            'package_id' => $package->id,
            'payment_method_id' => $paymentMethod->id,
        ]);

        $subscription = Subscription::where('user_id', $this->user->id)->first();

        $this->assertDatabaseHas('subscription_histories', [
            'subscription_id' => $subscription->id,
            'action' => 'created',
            'new_status' => 'active',
        ]);
    }

    public function test_subscription_updates_user_max_connections(): void
    {
        $package = SubscriptionPackage::factory()->create([
            'is_active' => true,
            'max_connections' => 5,
        ]);

        $paymentMethod = PaymentMethod::factory()->create(['is_active' => true]);

        $this->actingAs($this->user)->postJson('/api/v1/subscription/subscribe', [
            'package_id' => $package->id,
            'payment_method_id' => $paymentMethod->id,
        ]);

        $this->user->refresh();
        $this->assertEquals(5, $this->user->max_connections);
    }
}

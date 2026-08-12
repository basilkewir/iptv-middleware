<?php

namespace Tests\Unit;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\PaymentMethod;
use App\Models\Subscription;
use App\Models\SubscriptionHistory;
use App\Models\SubscriptionPackage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentTest extends TestCase
{
    use RefreshDatabase;

    public function test_invoice_belongs_to_user(): void
    {
        $user = User::factory()->create();
        $invoice = Invoice::factory()->create(['user_id' => $user->id]);

        $this->assertInstanceOf(User::class, $invoice->user);
        $this->assertEquals($user->id, $invoice->user->id);
    }

    public function test_invoice_belongs_to_payment_method(): void
    {
        $paymentMethod = PaymentMethod::factory()->create();
        $invoice = Invoice::factory()->create(['payment_method_id' => $paymentMethod->id]);

        $this->assertInstanceOf(PaymentMethod::class, $invoice->paymentMethod);
    }

    public function test_invoice_has_items(): void
    {
        $invoice = Invoice::factory()->create();
        InvoiceItem::factory()->count(2)->create(['invoice_id' => $invoice->id]);

        $this->assertCount(2, $invoice->items);
    }

    public function test_invoice_totals_are_correct(): void
    {
        $invoice = Invoice::factory()->create([
            'subtotal' => 19.99,
            'tax' => 2.00,
            'total' => 21.99,
        ]);

        $this->assertEquals('19.99', $invoice->subtotal);
        $this->assertEquals('2.00', $invoice->tax);
        $this->assertEquals('21.99', $invoice->total);
    }

    public function test_invoice_number_is_unique(): void
    {
        $invoice1 = Invoice::factory()->create(['invoice_number' => 'INV-001']);
        $invoice2 = Invoice::factory()->create(['invoice_number' => 'INV-002']);

        $this->assertNotEquals($invoice1->invoice_number, $invoice2->invoice_number);
    }

    public function test_invoice_status_can_be_pending(): void
    {
        $invoice = Invoice::factory()->create(['status' => 'pending']);

        $this->assertEquals('pending', $invoice->status);
    }

    public function test_invoice_status_can_be_paid(): void
    {
        $invoice = Invoice::factory()->create([
            'status' => 'paid',
            'paid_at' => now(),
        ]);

        $this->assertEquals('paid', $invoice->status);
        $this->assertNotNull($invoice->paid_at);
    }

    public function test_invoice_status_can_be_cancelled(): void
    {
        $invoice = Invoice::factory()->create(['status' => 'cancelled']);

        $this->assertEquals('cancelled', $invoice->status);
    }

    public function test_invoice_item_has_subscription_package(): void
    {
        $package = SubscriptionPackage::factory()->create();
        $invoice = Invoice::factory()->create();
        $item = InvoiceItem::factory()->create([
            'invoice_id' => $invoice->id,
            'subscription_package_id' => $package->id,
        ]);

        $this->assertEquals($package->id, $item->subscription_package_id);
    }

    public function test_payment_method_config_is_hidden(): void
    {
        $method = PaymentMethod::factory()->create([
            'config' => ['secret_key' => 'sk_test_123'],
        ]);

        $array = $method->toArray();
        $this->assertArrayNotHasKey('config', $array);
    }

    public function test_payment_method_is_active(): void
    {
        $method = PaymentMethod::factory()->create(['is_active' => true]);

        $this->assertTrue($method->is_active);
    }

    public function test_subscription_has_payment_reference(): void
    {
        $subscription = Subscription::factory()->create([
            'payment_reference' => 'INV-12345',
        ]);

        $this->assertEquals('INV-12345', $subscription->payment_reference);
    }

    public function test_subscription_history_records_actions(): void
    {
        $subscription = Subscription::factory()->create();

        SubscriptionHistory::factory()->create([
            'subscription_id' => $subscription->id,
            'action' => 'created',
            'old_status' => null,
            'new_status' => 'active',
        ]);

        SubscriptionHistory::factory()->create([
            'subscription_id' => $subscription->id,
            'action' => 'renewed',
            'old_status' => 'active',
            'new_status' => 'active',
        ]);

        $this->assertCount(2, $subscription->history);
    }

    public function test_subscription_package_features_are_array(): void
    {
        $package = SubscriptionPackage::factory()->create([
            'features' => ['all_channels', 'hd_quality', 'epg'],
        ]);

        $this->assertIsArray($package->features);
        $this->assertContains('all_channels', $package->features);
    }

    public function test_subscription_duration_is_in_days(): void
    {
        $package = SubscriptionPackage::factory()->create([
            'duration_days' => 30,
        ]);

        $this->assertEquals(30, $package->duration_days);
    }

    public function test_invoice_created_timestamp(): void
    {
        $invoice = Invoice::factory()->create();

        $this->assertNotNull($invoice->created_at);
    }
}

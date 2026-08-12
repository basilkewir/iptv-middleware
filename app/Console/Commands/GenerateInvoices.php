<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Invoice;
use App\Models\Subscription;
use App\Models\User;
use App\Services\PaymentService\PaymentManager;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class GenerateInvoices extends Command
{
    protected $signature = 'invoices:generate
                            {--user= : Generate invoice for specific user}
                            {--dry-run : Simulate without creating invoices}';

    protected $description = 'Generate invoices for active subscriptions';

    private PaymentManager $paymentManager;

    public function __construct(PaymentManager $paymentManager)
    {
        parent::__construct();
        $this->paymentManager = $paymentManager;
    }

    public function handle(): int
    {
        $userId = $this->option('user');
        $dryRun = $this->option('dry-run');

        $this->info('Starting invoice generation...');

        $subscriptions = $this->getSubscriptions($userId);

        if ($subscriptions->isEmpty()) {
            $this->warn('No active subscriptions found.');
            return Command::SUCCESS;
        }

        $this->info("Found {$subscriptions->count()} subscriptions to process.");

        $generated = 0;
        $failed = 0;

        foreach ($subscriptions as $subscription) {
            try {
                if ($dryRun) {
                    $this->line("  [DRY RUN] Would generate invoice for user #{$subscription->user_id} - {$subscription->subscriptionPackage->name}");
                    $generated++;
                    continue;
                }

                $invoice = $this->generateInvoice($subscription);

                if ($invoice) {
                    $this->info("  Invoice #{$invoice->invoice_number} generated for user #{$subscription->user_id}");
                    $generated++;
                }
            } catch (\Exception $e) {
                $this->error("  Failed to generate invoice for user #{$subscription->user_id}: {$e->getMessage()}");
                $failed++;

                Log::error('Invoice generation failed', [
                    'user_id' => $subscription->user_id,
                    'subscription_id' => $subscription->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->info("Invoice generation completed. Generated: {$generated}, Failed: {$failed}");

        return $failed > 0 ? Command::FAILURE : Command::SUCCESS;
    }

    private function getSubscriptions(?string $userId): \Illuminate\Database\Eloquent\Collection
    {
        $query = Subscription::where('status', 'active')
            ->with(['user', 'subscriptionPackage']);

        if ($userId) {
            $query->where('user_id', $userId);
        }

        return $query->get();
    }

    private function generateInvoice(Subscription $subscription): ?Invoice
    {
        $existingInvoice = Invoice::where('user_id', $subscription->user_id)
            ->where('subscription_id', $subscription->id)
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->first();

        if ($existingInvoice) {
            $this->line("  Invoice already exists for user #{$subscription->user_id}");
            return null;
        }

        $items = [
            [
                'description' => "{$subscription->subscriptionPackage->name} - " . now()->format('F Y'),
                'amount' => $subscription->subscriptionPackage->price,
                'quantity' => 1,
            ],
        ];

        $invoice = $this->paymentManager->generateInvoice(
            $subscription->user,
            $items,
            [
                'currency' => $subscription->subscriptionPackage->currency ?? 'USD',
                'due_date' => now()->addDays(7),
                'metadata' => [
                    'subscription_id' => $subscription->id,
                    'subscription_package_id' => $subscription->subscription_package_id,
                ],
            ]
        );

        $invoice->update(['subscription_id' => $subscription->id]);

        return $invoice;
    }
}

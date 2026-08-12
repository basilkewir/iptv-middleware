<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\PaymentMethod;
use App\Models\Subscription;
use App\Models\SubscriptionHistory;
use App\Models\SubscriptionPackage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function methods(): JsonResponse
    {
        try {
            $methods = PaymentMethod::where('is_active', true)
                ->orderBy('sort_order', 'asc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => [
                    'methods' => $methods,
                ],
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while fetching payment methods.',
            ], 500);
        }
    }

    public function createInvoice(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'package_id' => 'required|exists:subscription_packages,id',
                'payment_method_id' => 'required|exists:payment_methods,id',
            ]);

            $package = SubscriptionPackage::findOrFail($validated['package_id']);
            $user = $request->user();

            $taxRate = 0.10;
            $subtotal = $package->price;
            $tax = round($subtotal * $taxRate, 2);
            $total = round($subtotal + $tax, 2);

            $invoiceNumber = 'INV-' . strtoupper(uniqid());

            $invoice = Invoice::create([
                'user_id' => $user->id,
                'payment_method_id' => $validated['payment_method_id'],
                'invoice_number' => $invoiceNumber,
                'subtotal' => $subtotal,
                'tax' => $tax,
                'total' => $total,
                'status' => 'pending',
            ]);

            InvoiceItem::create([
                'invoice_id' => $invoice->id,
                'subscription_package_id' => $package->id,
                'description' => "{$package->name} - {$package->billing_cycle}",
                'quantity' => 1,
                'unit_price' => $package->price,
                'total_price' => $package->price,
            ]);

            $invoice->load(['items.subscriptionPackage', 'paymentMethod']);

            return response()->json([
                'success' => true,
                'message' => 'Invoice created successfully.',
                'data' => [
                    'invoice' => $invoice,
                ],
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while creating invoice.',
            ], 500);
        }
    }

    public function payInvoice(Request $request, string $id): JsonResponse
    {
        try {
            $validated = $request->validate([
                'payment_reference' => 'nullable|string|max:255',
            ]);

            $invoice = Invoice::where('id', $id)
                ->where('user_id', $request->user()->id)
                ->firstOrFail();

            if ($invoice->status === 'paid') {
                return response()->json([
                    'success' => false,
                    'message' => 'This invoice has already been paid.',
                ], 400);
            }

            if ($invoice->status === 'cancelled') {
                return response()->json([
                    'success' => false,
                    'message' => 'This invoice has been cancelled.',
                ], 400);
            }

            $invoice->update([
                'status' => 'paid',
                'paid_at' => now(),
                'payment_reference' => $validated['payment_reference'] ?? 'manual-' . uniqid(),
            ]);

            $item = $invoice->items()->first();

            if ($item && $item->subscription_package_id) {
                $package = SubscriptionPackage::find($item->subscription_package_id);
                $user = $request->user();

                $subscription = Subscription::create([
                    'user_id' => $user->id,
                    'subscription_package_id' => $package->id,
                    'status' => 'active',
                    'start_date' => now(),
                    'end_date' => now()->addDays($package->duration_days),
                    'payment_reference' => $invoice->invoice_number,
                ]);

                SubscriptionHistory::create([
                    'subscription_id' => $subscription->id,
                    'action' => 'created',
                    'old_status' => null,
                    'new_status' => 'active',
                    'notes' => "Subscribed via invoice {$invoice->invoice_number}.",
                ]);

                $user->update(['max_connections' => $package->max_connections]);
            }

            $invoice->load(['items.subscriptionPackage', 'paymentMethod']);

            return response()->json([
                'success' => true,
                'message' => 'Payment processed successfully.',
                'data' => [
                    'invoice' => $invoice,
                ],
            ], 200);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Invoice not found.',
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while processing payment.',
            ], 500);
        }
    }

    public function invoices(Request $request): JsonResponse
    {
        try {
            $invoices = Invoice::with(['items.subscriptionPackage', 'paymentMethod'])
                ->where('user_id', $request->user()->id)
                ->orderBy('created_at', 'desc')
                ->paginate($request->get('per_page', 20));

            return response()->json([
                'success' => true,
                'data' => $invoices,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while fetching invoices.',
            ], 500);
        }
    }
}

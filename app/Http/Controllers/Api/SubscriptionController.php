<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Subscription;
use App\Models\SubscriptionHistory;
use App\Models\SubscriptionPackage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SubscriptionController extends Controller
{
    public function packages(): JsonResponse
    {
        try {
            $packages = SubscriptionPackage::where('is_active', true)
                ->orderBy('sort_order', 'asc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $packages,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while fetching packages.',
            ], 500);
        }
    }

    public function current(Request $request): JsonResponse
    {
        try {
            $subscription = Subscription::with('subscriptionPackage')
                ->where('user_id', $request->user()->id)
                ->where('status', 'active')
                ->where('end_date', '>=', now())
                ->first();

            if (! $subscription) {
                return response()->json([
                    'success' => true,
                    'data' => [
                        'subscription' => null,
                    ],
                ], 200);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'subscription' => $subscription,
                ],
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while fetching subscription.',
            ], 500);
        }
    }

    public function subscribe(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'package_id' => 'required|exists:subscription_packages,id',
                'payment_method_id' => 'required|exists:payment_methods,id',
                'auto_renew' => 'boolean',
            ]);

            $package = SubscriptionPackage::findOrFail($validated['package_id']);
            $user = $request->user();

            $existingActive = Subscription::where('user_id', $user->id)
                ->where('status', 'active')
                ->where('end_date', '>=', now())
                ->first();

            if ($existingActive) {
                return response()->json([
                    'success' => false,
                    'message' => 'You already have an active subscription.',
                ], 409);
            }

            $startDate = now();
            $endDate = now()->addDays($package->duration_days);

            $subscription = Subscription::create([
                'user_id' => $user->id,
                'subscription_package_id' => $package->id,
                'status' => 'active',
                'start_date' => $startDate,
                'end_date' => $endDate,
                'auto_renew' => $validated['auto_renew'] ?? false,
            ]);

            SubscriptionHistory::create([
                'subscription_id' => $subscription->id,
                'action' => 'created',
                'old_status' => null,
                'new_status' => 'active',
                'notes' => "Subscribed to {$package->name} package.",
            ]);

            $user->update(['max_connections' => $package->max_connections]);

            return response()->json([
                'success' => true,
                'message' => 'Subscription created successfully.',
                'data' => [
                    'subscription' => $subscription->load('subscriptionPackage'),
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
                'message' => 'An error occurred while creating subscription.',
            ], 500);
        }
    }

    public function renew(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'subscription_id' => 'required|exists:subscriptions,id',
                'payment_method_id' => 'required|exists:payment_methods,id',
            ]);

            $subscription = Subscription::where('id', $validated['subscription_id'])
                ->where('user_id', $request->user()->id)
                ->firstOrFail();

            if ($subscription->status !== 'active' && $subscription->status !== 'expired') {
                return response()->json([
                    'success' => false,
                    'message' => 'This subscription cannot be renewed.',
                ], 400);
            }

            $package = $subscription->subscriptionPackage;
            $oldStatus = $subscription->status;

            $subscription->update([
                'start_date' => $subscription->end_date->isFuture() ? $subscription->end_date : now(),
                'end_date' => ($subscription->end_date->isFuture() ? $subscription->end_date : now())->addDays($package->duration_days),
                'status' => 'active',
            ]);

            SubscriptionHistory::create([
                'subscription_id' => $subscription->id,
                'action' => 'renewed',
                'old_status' => $oldStatus,
                'new_status' => 'active',
                'notes' => "Renewed {$package->name} package.",
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Subscription renewed successfully.',
                'data' => [
                    'subscription' => $subscription->load('subscriptionPackage'),
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
                'message' => 'Subscription not found.',
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while renewing subscription.',
            ], 500);
        }
    }

    public function history(Request $request): JsonResponse
    {
        try {
            $history = SubscriptionHistory::with('subscription.subscriptionPackage')
                ->whereHas('subscription', function ($q) use ($request) {
                    $q->where('user_id', $request->user()->id);
                })
                ->orderBy('created_at', 'desc')
                ->paginate($request->get('per_page', 20));

            return response()->json([
                'success' => true,
                'data' => $history,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'An error occurred while fetching subscription history.',
            ], 500);
        }
    }
}

<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SubscriptionPackage;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class ResellerController extends Controller
{
    public function index(Request $request): InertiaResponse|JsonResponse
    {
        $query = User::where('is_reseller', true)
            ->orWhere('role', 'reseller');

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('username', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('company_name', 'like', "%{$search}%");
            });
        }

        if ($request->has('status')) {
            $query->where('is_active', $request->boolean('status'));
        }

        $resellers = $query->latest()->paginate($request->input('per_page', 15));

        $resellers->getCollection()->transform(function ($reseller) {
            $reseller->total_clients = User::where('reseller_id', $reseller->id)->count();
            $reseller->active_clients = User::where('reseller_id', $reseller->id)
                ->where('is_active', true)
                ->count();
            $reseller->total_credits_used = DB::table('subscriptions')
                ->join('users', 'users.id', '=', 'subscriptions.user_id')
                ->where('users.reseller_id', $reseller->id)
                ->count();
            $reseller->commission_earned = $this->calculateCommission($reseller);

            return $reseller;
        });

        $stats = [
            'total_resellers' => User::where('is_reseller', true)->orWhere('role', 'reseller')->count(),
            'active_resellers' => User::where('is_reseller', true)->where('is_active', true)
                ->orWhere(function ($q) {
                    $q->where('role', 'reseller')->where('is_active', true);
                })->count(),
            'total_credits_issued' => (clone $query)->sum('credits'),
            'total_commission' => (clone $query)->sum('credits'),
        ];

        if ($request->expectsJson()) {
            return response()->json(['data' => $resellers, 'stats' => $stats]);
        }

        return Inertia::render('Admin/Resellers/Index', [
            'resellers' => $resellers,
            'stats' => $stats,
            'filters' => $request->only(['search', 'status']),
        ]);
    }

    public function create(): InertiaResponse
    {
        return Inertia::render('Admin/Resellers/Create', [
            'packages' => SubscriptionPackage::where('is_active', true)->get(),
        ]);
    }

    public function store(Request $request): JsonResponse|RedirectResponse
    {
        try {
            $validated = $request->validate([
                'first_name' => 'nullable|string|max:255',
                'last_name' => 'nullable|string|max:255',
                'username' => 'required|string|max:255|unique:users,username',
                'email' => 'required|email|unique:users,email',
                'password' => ['required', 'confirmed', Password::min(8)],
                'phone' => 'nullable|string|max:50',
                'company_name' => 'nullable|string|max:255',
                'website' => 'nullable|max:255',
                'credits' => 'required|numeric|min:0',
                'credit_limit' => 'nullable|numeric|min:0',
                'commission_rate' => 'nullable|numeric|min:0|max:100',
                'allow_sub_resellers' => 'nullable|boolean',
                'white_label' => 'nullable|boolean',
                'max_connections' => 'nullable|integer|min:1',
                'reseller_id' => 'nullable|exists:users,id',
                'package_id' => 'nullable|exists:subscription_packages,id',
                'expiry_date' => 'nullable|date',
            ]);

            $validated['password'] = Hash::make($validated['password']);
            $validated['is_reseller'] = true;
            $validated['role'] = 'reseller';
            $validated['is_active'] = true;
            $validated['max_connections'] = $validated['max_connections'] ?? 2;
            $validated['allow_sub_resellers'] = $validated['allow_sub_resellers'] ?? false;
            $validated['white_label'] = $validated['white_label'] ?? false;

            $packageId = $validated['package_id'] ?? null;
            $expiryDate = $validated['expiry_date'] ?? null;
            unset($validated['package_id'], $validated['expiry_date']);

            $reseller = User::create($validated);

            if ($packageId && $expiryDate) {
                $reseller->subscriptions()->create([
                    'subscription_package_id' => $packageId,
                    'status' => 'active',
                    'start_date' => now(),
                    'end_date' => $expiryDate,
                    'auto_renew' => false,
                ]);
            }

            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Reseller created successfully.',
                    'data' => $reseller,
                ], 201);
            }

            return redirect()->route('admin.resellers.index')
                ->with('success', 'Reseller created successfully.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Validation failed.', 'errors' => $e->errors()], 422);
            }
            throw $e;
        } catch (\Exception $e) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Failed to create reseller.', 'error' => $e->getMessage()], 500);
            }
            return back()->withInput()->with('error', 'Failed to create reseller.');
        }
    }

    public function show(Request $request, User $reseller): InertiaResponse|JsonResponse
    {
        $reseller->load(['subscriptions.subscriptionPackage', 'bouquets']);

        $subClients = User::where('reseller_id', $reseller->id)
            ->latest()
            ->take(50)
            ->get();

        $activityLogs = $reseller->activityLogs()
            ->latest()
            ->take(50)
            ->get();

        $totalCreditsUsed = $reseller->subscriptions()
            ->where('status', 'active')
            ->count();

        $commissionEarned = $this->calculateCommission($reseller);

        $data = [
            'reseller' => $reseller,
            'subClients' => $subClients,
            'activityLogs' => $activityLogs,
            'total_credits_used' => $totalCreditsUsed,
            'commission_earned' => $commissionEarned,
            'packages' => SubscriptionPackage::where('is_active', true)->get(),
        ];

        if ($request->expectsJson()) {
            return response()->json(['data' => $data]);
        }

        return Inertia::render('Admin/Resellers/Show', $data);
    }

    public function edit(User $reseller): InertiaResponse
    {
        return Inertia::render('Admin/Resellers/Edit', [
            'reseller' => $reseller,
            'packages' => SubscriptionPackage::where('is_active', true)->get(),
        ]);
    }

    public function update(Request $request, User $reseller): JsonResponse|RedirectResponse
    {
        try {
            $validated = $request->validate([
                'first_name' => 'sometimes|nullable|string|max:255',
                'last_name' => 'sometimes|nullable|string|max:255',
                'username' => "sometimes|string|max:255|unique:users,username,{$reseller->id}",
                'email' => "sometimes|email|unique:users,email,{$reseller->id}",
                'password' => ['sometimes', 'nullable', 'confirmed', Password::min(8)],
                'phone' => 'sometimes|nullable|string|max:50',
                'company_name' => 'sometimes|nullable|string|max:255',
                'website' => 'sometimes|nullable|max:255',
                'credits' => 'sometimes|numeric|min:0',
                'credit_limit' => 'sometimes|nullable|numeric|min:0',
                'commission_rate' => 'sometimes|nullable|numeric|min:0|max:100',
                'allow_sub_resellers' => 'sometimes|boolean',
                'white_label' => 'sometimes|boolean',
                'max_connections' => 'sometimes|integer|min:1',
                'is_active' => 'sometimes|boolean',
            ]);

            if (empty($validated['password'])) {
                unset($validated['password']);
            } else {
                $validated['password'] = Hash::make($validated['password']);
            }

            $reseller->update($validated);

            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Reseller updated successfully.',
                    'data' => $reseller,
                ]);
            }

            return redirect()->route('admin.resellers.index')
                ->with('success', 'Reseller updated successfully.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Validation failed.', 'errors' => $e->errors()], 422);
            }
            throw $e;
        } catch (\Exception $e) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Failed to update reseller.', 'error' => $e->getMessage()], 500);
            }
            return back()->withInput()->with('error', 'Failed to update reseller.');
        }
    }

    public function destroy(Request $request, User $reseller): JsonResponse|RedirectResponse
    {
        try {
            User::where('reseller_id', $reseller->id)->update(['reseller_id' => null]);

            $reseller->subscriptions()->delete();
            $reseller->bouquets()->detach();
            $reseller->delete();

            if ($request->expectsJson()) {
                return response()->json(['message' => 'Reseller deleted successfully.']);
            }

            return redirect()->route('admin.resellers.index')
                ->with('success', 'Reseller deleted successfully.');
        } catch (\Exception $e) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Failed to delete reseller.', 'error' => $e->getMessage()], 500);
            }
            return back()->with('error', 'Failed to delete reseller.');
        }
    }

    public function toggle(Request $request, User $reseller): JsonResponse|RedirectResponse
    {
        $reseller->is_active = !$reseller->is_active;
        $reseller->save();

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Reseller status updated successfully.',
                'data' => ['is_active' => $reseller->is_active],
            ]);
        }

        return back()->with('success', 'Reseller status updated.');
    }

    public function assignSubscription(Request $request, User $reseller): JsonResponse|RedirectResponse
    {
        try {
            $validated = $request->validate([
                'package_id' => 'required|exists:subscription_packages,id',
                'expiry_date' => 'required|date|after:now',
                'credits' => 'required|numeric|min:1',
            ]);

            DB::beginTransaction();

            if ($reseller->credits < $validated['credits']) {
                throw new \Exception('Insufficient credits. Available: ' . $reseller->credits);
            }

            $reseller->decrement('credits', $validated['credits']);

            $reseller->subscriptions()->create([
                'subscription_package_id' => $validated['package_id'],
                'status' => 'active',
                'start_date' => now(),
                'end_date' => $validated['expiry_date'],
                'auto_renew' => false,
            ]);

            DB::commit();

            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Subscription assigned successfully.',
                    'data' => ['credits' => $reseller->credits],
                ]);
            }

            return back()->with('success', 'Subscription assigned successfully. Remaining credits: ' . $reseller->credits);
        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Validation failed.', 'errors' => $e->errors()], 422);
            }
            throw $e;
        } catch (\Exception $e) {
            DB::rollBack();
            if ($request->expectsJson()) {
                return response()->json(['message' => $e->getMessage()], 400);
            }
            return back()->with('error', $e->getMessage());
        }
    }

    private function calculateCommission(User $reseller): float
    {
        $clientCount = User::where('reseller_id', $reseller->id)->count();

        return $clientCount * ($reseller->commission_rate ?? 0);
    }
}
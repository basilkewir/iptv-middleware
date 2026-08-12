<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Response;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class UserController extends Controller
{
    public function index(Request $request): InertiaResponse|JsonResponse
    {
        $query = User::query();

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('username', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($request->has('status')) {
            $query->where('is_active', $request->boolean('status'));
        }

        if ($role = $request->input('role')) {
            $query->where('role', $role);
        }

        $users = $query->latest()->paginate($request->input('per_page', 15));

        if ($request->expectsJson()) {
            return response()->json(['data' => $users]);
        }

        return Inertia::render('Admin/Users/Index', ['users' => $users]);
    }

    public function show(Request $request, User $user): InertiaResponse|JsonResponse
    {
        $user->load(['subscriptions.subscriptionPackage', 'watchHistory', 'bouquets']);

        if ($request->expectsJson()) {
            return response()->json(['data' => $user]);
        }

        return Inertia::render('Admin/Users/Edit', [
            'user' => $user,
            'packages' => \App\Models\SubscriptionPackage::where('is_active', true)->get(),
            'resellers' => User::where('is_reseller', true)->where('is_active', true)->get(),
            'bouquets' => \App\Models\Bouquet::where('is_active', true)->get(),
            'activityLog' => $user->activityLogs()->latest()->take(50)->get(),
            'watchHistory' => $user->watchHistory()->latest()->take(50)->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'first_name' => 'nullable|string|max:255',
            'last_name' => 'nullable|string|max:255',
            'username' => 'required|string|max:255|unique:users,username',
            'email' => 'required|email|unique:users,email',
            'password' => ['required', 'confirmed', Password::min(8)],
            'phone' => 'nullable|string|max:50',
            'is_active' => 'nullable|boolean',
            'is_admin' => 'nullable|boolean',
            'is_reseller' => 'nullable|boolean',
            'role' => 'nullable|string|in:super_admin,admin,reseller,moderator,support,client',
            'max_connections' => 'nullable|integer|min:1',
            'credits' => 'nullable|numeric|min:0',
            'credit_limit' => 'nullable|numeric|min:0',
            'commission_rate' => 'nullable|numeric|min:0|max:100',
            'company_name' => 'nullable|string|max:255',
            'website' => 'nullable|max:255',
            'mac_address' => 'nullable|string|max:17',
            'country' => 'nullable|string|max:100',
            'allow_sub_resellers' => 'nullable|boolean',
            'white_label' => 'nullable|boolean',
            'permissions' => 'nullable|array',
            'reseller_id' => 'nullable|exists:users,id',
            'package_id' => 'nullable|exists:subscription_packages,id',
            'expiry_date' => 'nullable|date',
            'bouquet_ids' => 'nullable|array',
        ]);

        $validated['password'] = Hash::make($validated['password']);
        $validated['is_active'] = $validated['is_active'] ?? true;
        $validated['is_admin'] = $validated['is_admin'] ?? false;
        $validated['is_reseller'] = $validated['is_reseller'] ?? false;
        $validated['role'] = $validated['role'] ?? 'client';

        $bouquetIds = $validated['bouquet_ids'] ?? [];
        $packageId = $validated['package_id'] ?? null;
        $expiryDate = $validated['expiry_date'] ?? null;
        unset($validated['bouquet_ids'], $validated['package_id'], $validated['expiry_date']);

        $user = User::create($validated);

        if (!empty($bouquetIds)) {
            $user->bouquets()->sync($bouquetIds);
        }

        if ($packageId && $expiryDate) {
            $user->subscriptions()->create([
                'subscription_package_id' => $packageId,
                'status' => 'active',
                'start_date' => now(),
                'end_date' => $expiryDate,
                'auto_renew' => false,
            ]);
        }

        return redirect()->route('admin.users.index')
            ->with('success', 'User created successfully.');
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'first_name' => 'sometimes|nullable|string|max:255',
            'last_name' => 'sometimes|nullable|string|max:255',
            'username' => "sometimes|string|max:255|unique:users,username,{$user->id}",
            'email' => "sometimes|email|unique:users,email,{$user->id}",
            'password' => ['sometimes', 'nullable', 'confirmed', Password::min(8)],
            'phone' => 'sometimes|nullable|string|max:50',
            'is_active' => 'sometimes|boolean',
            'is_admin' => 'sometimes|boolean',
            'is_reseller' => 'sometimes|boolean',
            'role' => 'sometimes|string|in:super_admin,admin,reseller,moderator,support,client',
            'max_connections' => 'sometimes|integer|min:1',
            'credits' => 'sometimes|numeric|min:0',
            'mac_address' => 'sometimes|nullable|string|max:17',
            'country' => 'sometimes|nullable|string|max:100',
            'reseller_id' => 'sometimes|nullable|exists:users,id',
            'bouquet_ids' => 'sometimes|array',
        ]);

        if (empty($validated['password'])) {
            unset($validated['password']);
        } else {
            $validated['password'] = Hash::make($validated['password']);
        }

        $bouquetIds = $validated['bouquet_ids'] ?? null;
        unset($validated['bouquet_ids']);

        $user->update($validated);

        if ($bouquetIds !== null) {
            $user->bouquets()->sync($bouquetIds);
        }

        return redirect()->route('admin.users.index')
            ->with('success', 'User updated successfully.');
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        $user->bouquets()->detach();
        $user->delete();

        return redirect()->route('admin.users.index')
            ->with('success', 'User deleted successfully.');
    }

    public function toggleStatus(Request $request, User $user): JsonResponse|RedirectResponse
    {
        $user->is_active = !$user->is_active;
        $user->save();

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'User status updated successfully.',
                'data' => ['is_active' => $user->is_active],
            ]);
        }

        return back()->with('success', 'User status updated.');
    }

    public function resetPassword(Request $request, User $user): JsonResponse|RedirectResponse
    {
        $validated = $request->validate([
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        $user->update(['password' => Hash::make($validated['password'])]);

        if ($request->expectsJson()) {
            return response()->json(['message' => 'Password reset successfully.']);
        }

        return back()->with('success', 'Password reset successfully.');
    }

    public function bulkStore(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'file' => 'required|file|mimes:csv|max:10240',
            'default_package' => 'nullable|exists:subscription_packages,id',
            'default_expiry' => 'nullable|date',
            'default_max_connections' => 'nullable|integer|min:1',
            'send_credentials' => 'nullable|boolean',
            'skip_duplicates' => 'nullable|boolean',
        ]);

        $file = $request->file('file');
        $rows = array_map('str_getcsv', file($file->getRealPath()));
        $headers = array_map('strtolower', array_shift($rows));

        $imported = 0;
        $skipped = 0;
        $errors = [];

        foreach ($rows as $row) {
            if (count($row) < 1 || empty($row[0])) continue;

            $data = array_combine($headers, $row);
            $username = $data['username'] ?? '';

            if ($validated['skip_duplicates'] && User::where('username', $username)->exists()) {
                $skipped++;
                continue;
            }

            try {
                $user = User::create([
                    'username' => $username,
                    'email' => $data['email'] ?? ($username . '@import.local'),
                    'password' => Hash::make($data['password'] ?? 'password123'),
                    'is_active' => true,
                    'role' => 'client',
                    'max_connections' => $validated['default_max_connections'] ?? 2,
                ]);

                $packageId = $validated['default_package'] ?? $data['package'] ?? null;
                $expiry = $validated['default_expiry'] ?? $data['expiry_date'] ?? null;

                if ($packageId && $expiry) {
                    $user->subscriptions()->create([
                        'subscription_package_id' => $packageId,
                        'status' => 'active',
                        'start_date' => now(),
                        'end_date' => $expiry,
                    ]);
                }

                $imported++;
            } catch (\Exception $e) {
                $errors[] = ['username' => $username, 'error' => $e->getMessage()];
            }
        }

        return redirect()->route('admin.users.index')
            ->with('success', "Import completed. {$imported} users created, {$skipped} skipped.");
    }

    public function bulkTemplate(): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $csv = "username,password,email,package,max_connections,expiry_date\n";
        $csv .= "user1,pass123,user1@example.com,basic,2,2025-12-31\n";
        $csv .= "user2,pass456,user2@example.com,premium,3,2025-12-31\n";

        return Response::make($csv, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="users_template.csv"',
        ]);
    }

    public function bulkActivate(Request $request): RedirectResponse
    {
        $request->validate(['ids' => 'required|array']);
        User::whereIn('id', $request->ids)->update(['is_active' => true]);
        return back()->with('success', 'Users activated.');
    }

    public function bulkSuspend(Request $request): RedirectResponse
    {
        $request->validate(['ids' => 'required|array']);
        User::whereIn('id', $request->ids)->update(['is_active' => false]);
        return back()->with('success', 'Users suspended.');
    }

    public function bulkDelete(Request $request): RedirectResponse
    {
        $request->validate(['ids' => 'required|array']);
        User::whereIn('id', $request->ids)->delete();
        return back()->with('success', 'Users deleted.');
    }
}

<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SubscriptionPackage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PackageController extends Controller
{
    public function index(Request $request): Response|JsonResponse
    {
        $packages = SubscriptionPackage::latest()->get();

        if ($request->expectsJson()) {
            return response()->json(['data' => $packages]);
        }

        return Inertia::render('Admin/Subscriptions/Packages', ['packages' => $packages]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:subscription_packages,name',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'duration_days' => 'required|integer|min:1',
            'max_connections' => 'required|integer|min:1',
            'features' => 'nullable|array',
            'is_active' => 'nullable|boolean',
        ]);

        $validated['is_active'] = $validated['is_active'] ?? true;
        $validated['slug'] = \Str::slug($validated['name']);

        SubscriptionPackage::create($validated);

        return redirect()->route('admin.subscriptions.packages')
            ->with('success', 'Package created successfully.');
    }

    public function update(Request $request, SubscriptionPackage $package): RedirectResponse
    {
        $validated = $request->validate([
            'name' => "sometimes|string|max:255|unique:subscription_packages,name,{$package->id}",
            'description' => 'nullable|string',
            'price' => 'sometimes|numeric|min:0',
            'duration_days' => 'sometimes|integer|min:1',
            'max_connections' => 'sometimes|integer|min:1',
            'features' => 'nullable|array',
            'is_active' => 'sometimes|boolean',
        ]);

        $package->update($validated);

        return redirect()->route('admin.subscriptions.packages')
            ->with('success', 'Package updated successfully.');
    }

    public function destroy(Request $request, SubscriptionPackage $package): JsonResponse|RedirectResponse
    {
        if ($package->subscriptions()->where('status', 'active')->exists()) {
            return back()->withErrors(['message' => 'Cannot delete package with active subscriptions.']);
        }

        $package->delete();

        return redirect()->route('admin.subscriptions.packages')
            ->with('success', 'Package deleted successfully.');
    }

    public function toggleActive(Request $request, SubscriptionPackage $package): JsonResponse
    {
        $package->is_active = !$package->is_active;
        $package->save();

        return response()->json([
            'message' => 'Package status updated successfully.',
            'data' => ['is_active' => $package->is_active],
        ]);
    }
}

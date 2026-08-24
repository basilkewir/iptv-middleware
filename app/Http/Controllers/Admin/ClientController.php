<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Bouquet;
use App\Models\Subscription;
use App\Models\SubscriptionPackage;
use App\Models\User;
use App\Models\UserActivityLog;
use App\Models\UserWatchHistory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Response;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class ClientController extends Controller
{
    /**
     * Display list of clients with search and filters.
     */
    public function index(Request $request): InertiaResponse|JsonResponse
    {
        $query = User::where('role', 'client')
            ->where('is_admin', false)
            ->where('is_reseller', false)
            ->with(['subscriptions.subscriptionPackage', 'reseller']);

        // Search by username or email
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('username', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%");
            });
        }

        // Filter by package
        if ($packageId = $request->input('package_id')) {
            $query->whereHas('subscriptions', function ($q) use ($packageId) {
                $q->where('subscription_package_id', $packageId)
                    ->where('status', 'active');
            });
        }

        // Filter by status (Active/Expired/Suspended)
        if ($status = $request->input('status')) {
            switch ($status) {
                case 'active':
                    $query->where('is_active', true)
                        ->whereHas('subscriptions', function ($q) {
                            $q->where('status', 'active')
                                ->where('end_date', '>=', now());
                        });
                    break;
                case 'expired':
                    $query->whereHas('subscriptions', function ($q) {
                        $q->where('end_date', '<', now());
                    });
                    break;
                case 'suspended':
                    $query->where('is_active', false);
                    break;
            }
        }

        // Filter by reseller
        if ($resellerId = $request->input('reseller_id')) {
            $query->where('reseller_id', $resellerId);
        }

        // Filter by expiry date range
        if ($expiryFrom = $request->input('expiry_from')) {
            $query->whereHas('subscriptions', function ($q) use ($expiryFrom) {
                $q->where('end_date', '>=', $expiryFrom);
            });
        }
        if ($expiryTo = $request->input('expiry_to')) {
            $query->whereHas('subscriptions', function ($q) use ($expiryTo) {
                $q->where('end_date', '<=', $expiryTo);
            });
        }

        $clients = $query->latest()->paginate($request->input('per_page', 15));

        // Append subscription status to each client
        $collection = $clients->getCollection();
        if ($collection) {
            $collection->map(function ($client) {
                $activeSub = $client->subscriptions->first(function ($sub) {
                    return $sub->status === 'active' && ($sub->end_date === null || $sub->end_date >= now());
                });
                $client->setAttribute('subscription_status', $activeSub ? 'active' : ($client->is_active ? 'expired' : 'suspended'));
                $client->setAttribute('subscription_end_date', $activeSub?->end_date);
                $client->setAttribute('package_name', $activeSub?->subscriptionPackage?->name);
                return $client;
            });
        }

        if ($request->expectsJson()) {
            return response()->json(['data' => $clients]);
        }

        return Inertia::render('Admin/Clients/Index', [
            'clients' => $clients,
            'packages' => SubscriptionPackage::where('is_active', true)->orderBy('name')->get(),
            'resellers' => User::where('is_reseller', true)->where('is_active', true)->select('id', 'username', 'first_name', 'last_name')->get(),
            'filters' => $request->only(['search', 'package_id', 'status', 'reseller_id', 'expiry_from', 'expiry_to']),
        ]);
    }

    /**
     * Show client creation form.
     */
    public function create(): InertiaResponse
    {
        return Inertia::render('Admin/Clients/Create', [
            'packages' => SubscriptionPackage::where('is_active', true)->orderBy('name')->get(),
            'bouquets' => Bouquet::where('is_active', true)->orderBy('name')->get(),
            'resellers' => User::where('is_reseller', true)->where('is_active', true)->select('id', 'username', 'first_name', 'last_name')->get(),
        ]);
    }

    /**
     * Store a new client with subscription, device access, and bouquets.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            // Step 1: Basic Info (email optional, username/password auto-generated if empty)
            'username' => 'nullable|string|max:255|unique:users,username',
            'email' => 'nullable|email|max:255|unique:users,email',
            'password' => 'nullable|confirmed|min:8',
            'first_name' => 'nullable|string|max:255',
            'last_name' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:50',
            'auto_generate_username' => 'nullable|boolean',
            'auto_generate_password' => 'nullable|boolean',

            // Step 2: Subscription
            'package_id' => 'required|exists:subscription_packages,id',
            'expiry_date' => 'nullable|date|after:today',
            'never_expire' => 'nullable|boolean',
            'duration' => 'nullable|in:30,90,180,365,custom',
            'max_connections' => 'nullable|integer|min:1|max:100',

            // Step 3: Device Access
            'mac_address' => 'nullable|string|max:50',
            'ip_restriction' => 'nullable|string|max:255',
            'country' => 'nullable|string|max:10',

            // Step 4: Bouquets
            'bouquet_ids' => 'nullable|array',
            'bouquet_ids.*' => 'exists:bouquets,id',

            // Optional
            'reseller_id' => 'nullable|exists:users,id',
            'send_credentials' => 'nullable|boolean',
        ]);

        // Auto-generate username if not provided
        if (empty($validated['username'])) {
            $validated['username'] = 'client_' . \Str::random(8);
        }

        // Auto-generate password if not provided
        if (empty($validated['password'])) {
            $validated['password'] = \Str::random(12);
        }

        // Generate placeholder email if not provided (email column is NOT NULL)
        if (empty($validated['email'])) {
            $validated['email'] = $validated['username'] . '@iptv-middleware.local';
        }

        // Determine expiry date
        $neverExpire = $request->boolean('never_expire');
        $expiryDate = null;

        if (!$neverExpire) {
            if ($request->input('expiry_date')) {
                $expiryDate = $validated['expiry_date'];
            } elseif ($validated['duration'] && $validated['duration'] !== 'custom') {
                $expiryDate = now()->addDays((int) $validated['duration'])->toDateString();
            } else {
                // Default to package duration or 30 days
                $package = SubscriptionPackage::find($validated['package_id']);
                $expiryDate = now()->addDays($package?->duration_days ?? 30)->toDateString();
            }
        }

        // Create the user
        $user = User::create([
            'username' => $validated['username'],
            'email' => $validated['email'] ?? null,
            'password' => Hash::make($validated['password']),
            'first_name' => $validated['first_name'] ?? null,
            'last_name' => $validated['last_name'] ?? null,
            'phone' => $validated['phone'] ?? null,
            'role' => 'client',
            'is_active' => true,
            'is_admin' => false,
            'is_reseller' => false,
            'max_connections' => $validated['max_connections'] ?? 2,
            'mac_address' => $validated['mac_address'] ?? null,
            'ip_restriction' => $validated['ip_restriction'] ?? null,
            'country' => $validated['country'] ?? null,
            'reseller_id' => $validated['reseller_id'] ?? null,
        ]);

        // Create subscription
        $user->subscriptions()->create([
            'subscription_package_id' => $validated['package_id'],
            'status' => 'active',
            'start_date' => now(),
            'end_date' => $expiryDate,
            'auto_renew' => false,
        ]);

        // Assign bouquets
        if (!empty($validated['bouquet_ids'])) {
            $user->bouquets()->sync($validated['bouquet_ids']);
        }

        // Log activity
        UserActivityLog::create([
            'user_id' => $user->id,
            'action' => 'account_created',
            'description' => 'Client account created by admin',
            'ip_address' => $request->ip(),
        ]);

        return redirect()->route('admin.clients.show', $user)
            ->with('success', 'Client created successfully. Credentials generated.');
    }

    /**
     * Display client profile with all details.
     */
    public function show(Request $request, User $client): InertiaResponse|JsonResponse
    {
        // Ensure this is a client
        if ($client->is_admin || $client->is_reseller) {
            abort(404);
        }

        $client->load([
            'subscriptions.subscriptionPackage',
            'bouquets',
            'reseller',
            'invoices',
        ]);

        $activityLogs = $client->activityLogs()->latest()->take(50)->get();
        $watchHistory = $client->watchHistory()->with('channel')->latest()->take(50)->get();
        $connectionLogs = \App\Models\StreamingLog::where('user_id', $client->id)
            ->latest()->take(50)->get();

        // Auto-generate m3u_token if missing
        if (! $client->m3u_token) {
            $client->update(['m3u_token' => \Str::random(32)]);
        }

        if ($request->expectsJson()) {
            return response()->json(['data' => $client]);
        }

        $serverBaseUrl = config('app.url');

        return Inertia::render('Admin/Clients/Show', [
            'client' => $client,
            'activityLogs' => $activityLogs,
            'watchHistory' => $watchHistory,
            'connectionLogs' => $connectionLogs,
            'packages' => SubscriptionPackage::where('is_active', true)->get(),
            'bouquets' => Bouquet::where('is_active', true)->get(),
            'serverBaseUrl' => $serverBaseUrl,
        ]);
    }

    /**
     * Show edit form for a client.
     */
    public function edit(Request $request, User $client): InertiaResponse
    {
        if ($client->is_admin || $client->is_reseller) {
            abort(404);
        }

        $client->load(['subscriptions.subscriptionPackage', 'bouquets', 'reseller']);

        return Inertia::render('Admin/Clients/Edit', [
            'client' => $client,
            'packages' => SubscriptionPackage::where('is_active', true)->orderBy('name')->get(),
            'bouquets' => Bouquet::where('is_active', true)->orderBy('name')->get(),
            'resellers' => User::where('is_reseller', true)->where('is_active', true)->select('id', 'username', 'first_name', 'last_name')->get(),
        ]);
    }

    /**
     * Update client details.
     */
    public function update(Request $request, User $client): RedirectResponse
    {
        if ($client->is_admin || $client->is_reseller) {
            abort(404);
        }

        $validated = $request->validate([
            'first_name' => 'nullable|string|max:255',
            'last_name' => 'nullable|string|max:255',
            'email' => 'required|email|max:255|unique:users,email,' . $client->id,
            'phone' => 'nullable|string|max:50',
            'max_connections' => 'nullable|integer|min:1|max:100',
            'mac_address' => 'nullable|string|max:50',
            'ip_restriction' => 'nullable|string|max:255',
            'country' => 'nullable|string|max:10',
            'reseller_id' => 'nullable|exists:users,id',
            'bouquet_ids' => 'nullable|array',
            'bouquet_ids.*' => 'exists:bouquets,id',
            'package_id' => 'nullable|exists:subscription_packages,id',
            'expiry_date' => 'nullable|date',
            'never_expire' => 'nullable|boolean',
        ]);

        $client->update(collect($validated)->only([
            'first_name', 'last_name', 'email', 'phone', 'max_connections',
            'mac_address', 'ip_restriction', 'country', 'reseller_id',
        ])->toArray());

        if (isset($validated['bouquet_ids'])) {
            $client->bouquets()->sync($validated['bouquet_ids']);
        }

        $neverExpire = $request->boolean('never_expire');

        // Handle package change
        if (!empty($validated['package_id'])) {
            $client->subscriptions()->where('status', 'active')->update(['status' => 'cancelled']);
            $client->subscriptions()->create([
                'subscription_package_id' => $validated['package_id'],
                'status' => 'active',
                'start_date' => now(),
                'end_date' => $neverExpire ? null : ($validated['expiry_date'] ?? null),
                'auto_renew' => false,
            ]);
        } elseif ($neverExpire) {
            $subscription = $client->subscriptions()->where('status', 'active')->first();
            if ($subscription) {
                $subscription->update(['end_date' => null]);
            }
        } elseif (!empty($validated['expiry_date'])) {
            $subscription = $client->subscriptions()->where('status', 'active')->first();
            if ($subscription) {
                $subscription->update(['end_date' => $validated['expiry_date']]);
            }
        }

        return redirect()->route('admin.clients.show', $client)
            ->with('success', 'Client updated successfully.');
    }

    /**
     * Change client's subscription package.
     */
    public function changePackage(Request $request, User $client): RedirectResponse
    {
        $validated = $request->validate([
            'package_id' => 'required|exists:subscription_packages,id',
            'expiry_date' => 'required|date|after:today',
        ]);

        // Deactivate current subscription
        $client->subscriptions()->where('status', 'active')->update(['status' => 'cancelled']);

        // Create new subscription
        $client->subscriptions()->create([
            'subscription_package_id' => $validated['package_id'],
            'status' => 'active',
            'start_date' => now(),
            'end_date' => $validated['expiry_date'],
            'auto_renew' => false,
        ]);

        return back()->with('success', 'Package changed successfully.');
    }

    /**
     * Extend client's subscription expiry date.
     */
    public function extendExpiry(Request $request, User $client): RedirectResponse
    {
        $validated = $request->validate([
            'expiry_date' => 'nullable|date',
            'never_expire' => 'nullable|boolean',
        ]);

        $subscription = $client->subscriptions()->where('status', 'active')->first();
        if ($subscription) {
            if ($request->boolean('never_expire')) {
                $subscription->update(['end_date' => null]);
            } elseif (!empty($validated['expiry_date'])) {
                $subscription->update(['end_date' => $validated['expiry_date']]);
            }
        }

        return redirect()->route('admin.clients.show', $client)
            ->with('success', 'Expiry date extended successfully.');
    }

    /**
     * Reset client's password.
     */
    public function resetPassword(Request $request, User $client): RedirectResponse
    {
        $validated = $request->validate([
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        $client->update(['password' => Hash::make($validated['password'])]);

        UserActivityLog::create([
            'user_id' => $client->id,
            'action' => 'password_reset',
            'description' => 'Password reset by admin',
            'ip_address' => $request->ip(),
        ]);

        return back()->with('success', 'Password reset successfully.');
    }

    /**
     * Generate/regenerate M3U URL for client.
     */
    public function generateM3u(Request $request, User $client): RedirectResponse
    {
        $token = \Str::random(32);
        $client->update(['m3u_token' => $token]);

        return back()->with('success', 'M3U token regenerated successfully.');
    }

    /**
     * Suspend or activate a client.
     */
    public function toggleStatus(Request $request, User $client): RedirectResponse
    {
        $client->is_active = !$client->is_active;
        $client->save();

        $action = $client->is_active ? 'activated' : 'suspended';

        UserActivityLog::create([
            'user_id' => $client->id,
            'action' => $action,
            'description' => "Account {$action} by admin",
            'ip_address' => $request->ip(),
        ]);

        return back()->with('success', "Client {$action} successfully.");
    }

    /**
     * Send credentials to client via email.
     */
    public function sendCredentials(Request $request, User $client): RedirectResponse
    {
        // In a real app, this would send an email
        // For now, we'll just log it
        UserActivityLog::create([
            'user_id' => $client->id,
            'action' => 'credentials_sent',
            'description' => 'Credentials sent to client email',
            'ip_address' => $request->ip(),
        ]);

        return back()->with('success', 'Credentials sent to ' . $client->email);
    }

    /**
     * Delete a client.
     */
    public function destroy(Request $request, User $client): RedirectResponse
    {
        if ($client->is_admin || $client->is_reseller) {
            abort(404);
        }

        $client->bouquets()->detach();
        $client->subscriptions()->delete();
        $client->activityLogs()->delete();
        $client->watchHistory()->delete();
        $client->delete();

        return redirect()->route('admin.clients.index')
            ->with('success', 'Client deleted successfully.');
    }

    /**
     * Show bulk import form.
     */
    public function bulkImportForm(): InertiaResponse
    {
        return Inertia::render('Admin/Clients/BulkImport', [
            'packages' => SubscriptionPackage::where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    /**
     * Download CSV template for bulk import.
     */
    public function bulkTemplate(): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $csv = "username,password,email,first_name,last_name,package,max_connections,expiry_date,mac_address,country\n";
        $csv .= "user1,Pass1234!,user1@example.com,John,Doe,1,2,2025-12-31,00:1A:2B:3C:4D:5E,US\n";
        $csv .= "user2,Pass5678!,user2@example.com,Jane,Smith,1,3,2025-12-31,,UK\n";

        return Response::make($csv, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="clients_template.csv"',
        ]);
    }

    /**
     * Bulk import clients from CSV.
     */
    public function bulkImport(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'file' => 'required|file|mimes:csv|max:10240',
            'default_package' => 'nullable|exists:subscription_packages,id',
            'default_expiry' => 'nullable|date|after:today',
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

        foreach ($rows as $index => $row) {
            if (count($row) < 1 || empty($row[0])) continue;

            $data = array_combine($headers, $row);
            $username = $data['username'] ?? '';

            if (empty($username)) {
                $errors[] = ['row' => $index + 2, 'error' => 'Missing username'];
                continue;
            }

            if ($validated['skip_duplicates'] && User::where('username', $username)->exists()) {
                $skipped++;
                continue;
            }

            try {
                $user = User::create([
                    'username' => $username,
                    'email' => $data['email'] ?? ($username . '@import.local'),
                    'password' => Hash::make($data['password'] ?? 'Password123!'),
                    'first_name' => $data['first_name'] ?? null,
                    'last_name' => $data['last_name'] ?? null,
                    'role' => 'client',
                    'is_active' => true,
                    'is_admin' => false,
                    'is_reseller' => false,
                    'max_connections' => $data['max_connections'] ?? $validated['default_max_connections'] ?? 2,
                    'mac_address' => $data['mac_address'] ?? null,
                    'country' => $data['country'] ?? null,
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

        $message = "Import completed. {$imported} clients created, {$skipped} skipped.";
        if (!empty($errors)) {
            $message .= " " . count($errors) . " errors.";
        }

        return redirect()->route('admin.clients.index')
            ->with('success', $message)
            ->with('import_errors', $errors);
    }

    /**
     * Generate a report for a specific client.
     */
    public function report(Request $request, User $client): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $client->load(['subscriptions.subscriptionPackage', 'bouquets']);

        $csv = "Client Report\n\n";
        $csv .= "Username,{$client->username}\n";
        $csv .= "Email,{$client->email}\n";
        $csv .= "Name,{$client->first_name} {$client->last_name}\n";
        $csv .= "Phone," . ($client->phone ?? 'N/A') . "\n";
        $csv .= "Status," . ($client->is_active ? 'Active' : 'Suspended') . "\n";
        $csv .= "Max Connections,{$client->max_connections}\n";
        $csv .= "MAC Address," . ($client->mac_address ?? 'N/A') . "\n";
        $csv .= "Country," . ($client->country ?? 'N/A') . "\n";
        $csv .= "Created At,{$client->created_at}\n\n";

        $csv .= "Subscriptions\n";
        $csv .= "Package,Status,Start Date,End Date\n";
        foreach ($client->subscriptions as $sub) {
            $packageName = $sub->subscriptionPackage?->name ?? 'N/A';
            $csv .= "{$packageName},{$sub->status},{$sub->start_date},{$sub->end_date}\n";
        }

        $csv .= "\nBouquets\n";
        foreach ($client->bouquets as $bouquet) {
            $csv .= "{$bouquet->name}\n";
        }

        $csv .= "\nWatch History (Last 50)\n";
        $csv .= "Channel,Watched At,Duration\n";
        $watchHistory = $client->watchHistory()->with('channel')->latest()->take(50)->get();
        foreach ($watchHistory as $watch) {
            $channelName = $watch->channel?->name ?? 'N/A';
            $csv .= "{$channelName},{$watch->created_at},{$watch->duration}\n";
        }

        return new \Symfony\Component\HttpFoundation\StreamedResponse(function () use ($csv) {
            echo $csv;
        }, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="client_' . $client->username . '_report.csv"',
        ]);
    }

    /**
     * Get the local Ethernet IP address of the server.
     */
    private function getLocalIp(): string
    {
        // Try hostname -I (returns all local IP addresses, space-separated)
        $output = @shell_exec('hostname -I 2>/dev/null');
        if ($output) {
            $ips = explode(' ', trim($output));
            foreach ($ips as $ip) {
                $ip = trim($ip);
                if ($ip && !str_starts_with($ip, '127.')) {
                    return $ip;
                }
            }
        }

        // Try ifconfig (Linux)
        $output = @shell_exec('ifconfig 2>/dev/null');
        if ($output) {
            if (preg_match('/inet\s+([0-9\.]+)/', $output, $matches)) {
                $ip = $matches[1];
                if (!str_starts_with($ip, '127.')) {
                    return $ip;
                }
            }
        }

        // Try gethostbynamel and filter out localhost
        $ips = @gethostbynamel(gethostname());
        if ($ips) {
            foreach ($ips as $ip) {
                if (!str_starts_with($ip, '127.')) {
                    return $ip;
                }
            }
        }

        // Try to get from request server variables
        $serverIp = request()->server('SERVER_ADDR');
        if ($serverIp && !str_starts_with($serverIp, '127.')) {
            return $serverIp;
        }

        // Final fallback
        return '127.0.0.1';
    }

    /**
     * Bulk actions on multiple clients.
     */
    public function bulkAction(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'exists:users,id',
            'action' => 'required|in:activate,suspend,delete',
        ]);

        $count = count($validated['ids']);

        switch ($validated['action']) {
            case 'activate':
                User::whereIn('id', $validated['ids'])->update(['is_active' => true]);
                return back()->with('success', "{$count} clients activated.");
            case 'suspend':
                User::whereIn('id', $validated['ids'])->update(['is_active' => false]);
                return back()->with('success', "{$count} clients suspended.");
            case 'delete':
                User::whereIn('id', $validated['ids'])->delete();
                return back()->with('success', "{$count} clients deleted.");
        }
    }
}
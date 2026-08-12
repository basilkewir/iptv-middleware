<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Server;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Inertia\Inertia;
use Inertia\Response;

class ServerController extends Controller
{
    public function index(Request $request): Response|JsonResponse
    {
        $servers = Server::all()->map(function ($server) {
            return [
                'id' => $server->id,
                'name' => $server->name,
                'ip_address' => $server->ip_address,
                'port' => $server->port,
                'status' => $server->status,
                'cpu_usage' => $server->cpu_usage,
                'memory_usage' => $server->memory_usage,
                'current_connections' => $server->current_connections,
                'max_connections' => $server->max_connections,
                'health' => $server->status === 'active' ? 'healthy' : 'unhealthy',
            ];
        });

        if ($request->expectsJson()) {
            return response()->json(['data' => $servers]);
        }

        return Inertia::render('Admin/Servers/Index', ['servers' => $servers]);
    }

    public function show(Request $request, Server $server): Response|JsonResponse
    {
        $server->load(['streamAssignments.channel']);

        $stats = [
            'server' => [
                'id' => $server->id,
                'name' => $server->name,
                'ip_address' => $server->ip_address,
                'port' => $server->port,
                'status' => $server->status,
                'cpu_usage' => $server->cpu_usage,
                'memory_usage' => $server->memory_usage,
                'current_connections' => $server->current_connections,
                'max_connections' => $server->max_connections,
            ],
            'real_time' => $this->getRealTimeStats($server),
            'channels' => $server->streamAssignments->map->channel->filter()->values(),
        ];

        if ($request->expectsJson()) {
            return response()->json(['data' => $stats]);
        }

        return Inertia::render('Admin/Servers/Index', ['servers' => [$stats['server']]]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'ip_address' => 'required|ip',
            'port' => 'required|integer|min:1|max:65535',
            'max_connections' => 'required|integer|min:1',
            'status' => 'nullable|in:active,inactive,maintenance',
        ]);

        $validated['status'] = $validated['status'] ?? 'active';

        Server::create($validated);

        return redirect()->route('admin.servers.index')
            ->with('success', 'Server created successfully.');
    }

    public function update(Request $request, Server $server): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'ip_address' => 'sometimes|ip',
            'port' => 'sometimes|integer|min:1|max:65535',
            'max_connections' => 'sometimes|integer|min:1',
            'status' => 'sometimes|in:active,inactive,maintenance',
        ]);

        $server->update($validated);

        return redirect()->route('admin.servers.index')
            ->with('success', 'Server updated successfully.');
    }

    public function monitor(Request $request, Server $server): JsonResponse
    {
        $stats = $this->getRealTimeStats($server);

        return response()->json(['data' => $stats]);
    }

    public function toggleStatus(Request $request, Server $server): JsonResponse
    {
        $server->status = $server->status === 'active' ? 'inactive' : 'active';
        $server->save();

        return response()->json([
            'message' => 'Server status updated successfully.',
            'data' => ['status' => $server->status],
        ]);
    }

    public function test(Request $request, Server $server): RedirectResponse
    {
        try {
            $response = Http::timeout(5)->get("http://{$server->ip_address}:{$server->port}/stats");
            $status = $response->successful() ? 'Connection successful.' : 'Connection failed (HTTP ' . $response->status() . ').';
        } catch (\Exception $e) {
            $status = 'Connection failed: ' . $e->getMessage();
        }

        return back()->with($response->successful() ? 'success' : 'error', $status);
    }

    private function getRealTimeStats(Server $server): array
    {
        try {
            $response = Http::timeout(5)->get("http://{$server->ip_address}:{$server->port}/stats");

            if ($response->successful()) {
                return $response->json();
            }
        } catch (\Exception $e) {
            // Fallback to database stats
        }

        return [
            'cpu_usage' => $server->cpu_usage,
            'memory_usage' => $server->memory_usage,
            'current_connections' => $server->current_connections,
            'status' => $server->status,
        ];
    }
}

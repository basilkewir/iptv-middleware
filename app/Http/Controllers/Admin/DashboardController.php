<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Channel;
use App\Models\Server;
use App\Models\StreamingLog;
use App\Models\Subscription;
use App\Models\SystemLog;
use App\Models\User;
use App\Models\VODContent;
use App\Services\SystemMonitorService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(Request $request): Response|JsonResponse
    {
        $range = $request->input('range', '30d');
        $days = match ($range) {
            'today' => 1,
            '7d' => 7,
            '30d' => 30,
            '90d' => 90,
            default => 30,
        };

        $startDate = now()->subDays($days - 1)->startOfDay();
        $endDate = now()->endOfDay();

        $stats = [
            'total_users' => User::count(),
            'active_subscriptions' => Subscription::where('status', 'active')->count(),
            'revenue_today' => (float) Subscription::where('status', 'active')
                ->whereDate('created_at', today())
                ->sum('amount_paid'),
            'revenue_month' => (float) Subscription::where('status', 'active')
                ->whereMonth('created_at', now()->month)
                ->whereYear('created_at', now()->year)
                ->sum('amount_paid'),
            'servers_online' => Server::where('is_active', true)->count(),
            'active_streams' => Server::where('is_active', true)->sum('current_connections'),
            'total_channels' => Channel::where('is_active', true)->count(),
            'total_vod' => VODContent::where('is_active', true)->count(),
            'user_growth' => $this->getUserGrowth($startDate, $endDate),
            'bandwidth_usage' => $this->getBandwidthUsage($startDate, $endDate),
            'top_channels' => $this->getTopChannels($startDate, $endDate),
            'recent_activity' => $this->getRecentActivity(),
            'server_health' => $this->getServerHealth(),
            'system' => app(SystemMonitorService::class)->summary(),
        ];

        if ($request->expectsJson()) {
            return response()->json(['data' => $stats]);
        }

        return Inertia::render('Admin/Dashboard/Index', ['stats' => $stats]);
    }

    private function getUserGrowth(Carbon $start, Carbon $end): array
    {
        $data = User::select(
            DB::raw('DATE(created_at) as date'),
            DB::raw('COUNT(*) as count')
        )
            ->whereBetween('created_at', [$start, $end])
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->keyBy('date');

        $result = [];
        $current = $start->copy();
        while ($current <= $end) {
            $dateStr = $current->format('Y-m-d');
            $count = $data->get($dateStr)?->count ?? 0;
            $result[] = [
                'date' => $current->format('M d'),
                'count' => (int) $count,
            ];
            $current->addDay();
        }

        return $result;
    }

    private function getBandwidthUsage(Carbon $start, Carbon $end): array
    {
        $data = StreamingLog::select(
            DB::raw('DATE(created_at) as date'),
            DB::raw('SUM(bytes_sent) as total_bytes')
        )
            ->whereBetween('created_at', [$start, $end])
            ->where('action', 'start')
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->keyBy('date');

        $result = [];
        $current = $start->copy();
        while ($current <= $end) {
            $dateStr = $current->format('Y-m-d');
            $bytes = $data->get($dateStr)?->total_bytes ?? 0;
            $mb = $bytes > 0 ? round($bytes / (1024 * 1024), 2) : 0;
            $result[] = [
                'date' => $current->format('M d'),
                'bandwidth_mb' => $mb,
            ];
            $current->addDay();
        }

        return $result;
    }

    private function getTopChannels(Carbon $start, Carbon $end): array
    {
        return Channel::query()
            ->select('channels.id', 'channels.name', 'channels.logo_url', 'channels.view_count')
            ->leftJoin('streaming_logs', 'streaming_logs.channel_id', '=', 'channels.id')
            ->whereBetween('streaming_logs.created_at', [$start, $end])
            ->where('streaming_logs.action', 'start')
            ->groupBy('channels.id', 'channels.name', 'channels.logo_url', 'channels.view_count')
            ->orderByDesc('channels.view_count')
            ->limit(10)
            ->get()
            ->map(fn ($channel) => [
                'id' => $channel->id,
                'name' => $channel->name,
                'logo_url' => $channel->logo_url,
                'view_count' => $channel->view_count,
            ])
            ->toArray();
    }

    private function getRecentActivity(): array
    {
        $activities = [];

        $recentUsers = User::latest()
            ->take(5)
            ->get()
            ->map(fn ($user) => [
                'id' => $user->id,
                'type' => 'user',
                'title' => 'New user registered',
                'message' => trim($user->first_name . ' ' . $user->last_name) ?: $user->username,
                'email' => $user->email,
                'created_at' => $user->created_at->toIso8601String(),
                'time' => $user->created_at->diffForHumans(),
            ]);

        $recentLogs = SystemLog::whereIn('level', ['info', 'warning', 'error'])
            ->latest()
            ->take(5)
            ->get()
            ->map(fn ($log) => [
                'id' => $log->id,
                'type' => 'system',
                'title' => match ($log->level) {
                    'error' => 'System Error',
                    'warning' => 'System Warning',
                    default => 'System Event',
                },
                'message' => $log->message,
                'email' => null,
                'created_at' => $log->created_at?->toIso8601String(),
                'time' => $log->created_at?->diffForHumans(),
            ]);

        $recentSubscriptions = Subscription::with('user')
            ->where('status', 'active')
            ->latest()
            ->take(5)
            ->get()
            ->map(fn ($sub) => [
                'id' => $sub->id,
                'type' => 'subscription',
                'title' => 'New subscription',
                'message' => $sub->user
                    ? (trim($sub->user->first_name . ' ' . $sub->user->last_name) ?: $sub->user->username)
                    : 'Unknown user',
                'email' => $sub->user?->email,
                'created_at' => $sub->created_at->toIso8601String(),
                'time' => $sub->created_at->diffForHumans(),
            ]);

        $activities = collect()
            ->merge($recentUsers)
            ->merge($recentLogs)
            ->merge($recentSubscriptions)
            ->sortByDesc('created_at')
            ->take(10)
            ->values()
            ->toArray();

        return $activities;
    }

    private function getServerHealth(): array
    {
        $monitor = app(SystemMonitorService::class);
        $summary = $monitor->summary();

        return Server::all()
            ->map(fn ($server) => [
                'id' => $server->id,
                'name' => $server->name,
                'host' => $server->host,
                'location' => $server->location,
                'status' => $server->is_active ? 'active' : 'inactive',
                'current_streams' => $server->current_connections,
                'max_streams' => $server->max_connections,
                'bandwidth' => $server->bandwidth,
                'cpu' => $summary['cpu_usage'],
                'memory' => $summary['memory_usage'],
                'disk' => $summary['disk_usage'],
            ])
            ->toArray();
    }
}

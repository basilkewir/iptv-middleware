<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Channel;
use App\Models\Invoice;
use App\Models\Stream;
use App\Models\StreamingLog;
use App\Models\StreamingServer;
use App\Models\Subscription;
use App\Models\SubscriptionPackage;
use App\Models\User;
use App\Models\VODContent;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class AnalyticsController extends Controller
{
    public function index(Request $request): Response
    {
        $stats = [
            'overview' => $this->getOverview(),
            'revenue' => $this->getRevenueAnalytics(),
            'streaming' => $this->getStreamingAnalytics(),
            'user_trends' => $this->getUserRegistrationTrends(),
            'revenue_trends' => $this->getRevenueTrends(),
            'bandwidth_by_server' => $this->getBandwidthByServer(),
            'top_channels' => $this->getTopChannelsByViews(),
            'server_health' => $this->getServerHealthSummary(),
        ];

        return Inertia::render('Admin/Reports/Index', ['analytics' => $stats]);
    }

    public function export(Request $request)
    {
        $request->validate([
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
        ]);

        $start = $request->input('start_date')
            ? Carbon::parse($request->input('start_date'))->startOfDay()
            : now()->subDays(30)->startOfDay();
        $end = $request->input('end_date')
            ? Carbon::parse($request->input('end_date'))->endOfDay()
            : now()->endOfDay();

        $data = [
            'report_date' => now()->format('Y-m-d H:i:s'),
            'period' => ['from' => $start->format('Y-m-d'), 'to' => $end->format('Y-m-d')],
            'overview' => $this->getOverview(),
            'daily_registrations' => $this->getUserRegistrationTrends($start, $end),
            'daily_revenue' => $this->getRevenueTrends($start, $end),
            'daily_bandwidth' => $this->getBandwidthByServer($start, $end),
            'top_channels' => $this->getTopChannelsByViews($start, $end),
            'streaming_stats' => $this->getStreamingAnalytics($start, $end),
        ];

        $filename = 'analytics_report_' . now()->format('Y-m-d_His') . '.json';
        $json = json_encode($data, JSON_PRETTY_PRINT);

        return response($json, 200, [
            'Content-Type' => 'application/json',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    private function getOverview(): array
    {
        $totalUsers = User::count();
        $usersThisMonth = User::whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->count();
        $usersLastMonth = User::whereMonth('created_at', now()->subMonth()->month)
            ->whereYear('created_at', now()->subMonth()->year)
            ->count();
        $userGrowthPercent = $usersLastMonth > 0
            ? round((($usersThisMonth - $usersLastMonth) / $usersLastMonth) * 100, 1)
            : 0;

        $totalRevenue = (float) Invoice::where('status', 'paid')->sum('total');
        $revenueThisMonth = (float) Invoice::where('status', 'paid')
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->sum('total');
        $revenueLastMonth = (float) Invoice::where('status', 'paid')
            ->whereMonth('created_at', now()->subMonth()->month)
            ->whereYear('created_at', now()->subMonth()->year)
            ->sum('total');
        $revenueGrowthPercent = $revenueLastMonth > 0
            ? round((($revenueThisMonth - $revenueLastMonth) / $revenueLastMonth) * 100, 1)
            : 0;

        $activeSubscriptions = Subscription::where('status', 'active')->count();

        $serversOnline = StreamingServer::where('is_active', true)->count();
        $totalServers = StreamingServer::count();
        $activeStreams = Stream::where('status', 'active')->count();
        $totalViewers = Stream::where('status', 'active')->sum('current_viewers');

        return [
            'total_users' => $totalUsers,
            'users_this_month' => $usersThisMonth,
            'user_growth_percent' => $userGrowthPercent,
            'total_channels' => Channel::where('is_active', true)->count(),
            'total_vod' => VODContent::where('is_active', true)->count(),
            'active_subscriptions' => $activeSubscriptions,
            'total_revenue' => $totalRevenue,
            'revenue_this_month' => $revenueThisMonth,
            'revenue_growth_percent' => $revenueGrowthPercent,
            'servers_online' => $serversOnline,
            'total_servers' => $totalServers,
            'active_streams' => $activeStreams,
            'total_viewers' => (int) $totalViewers,
        ];
    }

    private function getRevenueAnalytics(): array
    {
        $revenueByPackage = Subscription::where('status', 'active')
            ->with('subscriptionPackage')
            ->get()
            ->groupBy(fn ($sub) => $sub->subscriptionPackage?->name ?? 'Unknown')
            ->map(fn ($subs, $name) => [
                'package_name' => $name,
                'count' => $subs->count(),
                'revenue' => (float) $subs->sum(fn ($sub) => $sub->subscriptionPackage?->price ?? 0),
            ])
            ->values()
            ->toArray();

        $paidInvoices = Invoice::where('status', 'paid')->count();
        $totalInvoices = Invoice::count();
        $paymentSuccessRate = $totalInvoices > 0 ? round(($paidInvoices / $totalInvoices) * 100, 1) : 0;

        return [
            'revenue_by_package' => $revenueByPackage,
            'payment_success_rate' => $paymentSuccessRate,
            'total_invoices' => $totalInvoices,
            'paid_invoices' => $paidInvoices,
        ];
    }

    private function getStreamingAnalytics(?Carbon $start = null, ?Carbon $end = null): array
    {
        $start ??= now()->subDays(30)->startOfDay();
        $end ??= now()->endOfDay();

        $totalStreams = Stream::count();
        $activeStreams = Stream::where('status', 'active')->count();

        $avgBitrate = Stream::where('status', 'active')
            ->whereNotNull('avg_bitrate')
            ->avg('avg_bitrate');

        $avgLatency = Stream::where('status', 'active')
            ->whereNotNull('avg_latency')
            ->avg('avg_latency');

        $totalWatchTime = Stream::sum('total_watch_time');

        $streamErrors = StreamingLog::where('status', 'error')
            ->whereBetween('created_at', [$start, $end])
            ->count();

        $totalStreamSessions = StreamingLog::where('action', 'start')
            ->whereBetween('created_at', [$start, $end])
            ->count();

        $errorRate = $totalStreamSessions > 0
            ? round(($streamErrors / $totalStreamSessions) * 100, 2)
            : 0;

        $codecDistribution = Stream::where('status', 'active')
            ->whereNotNull('codec')
            ->select('codec', DB::raw('COUNT(*) as count'))
            ->groupBy('codec')
            ->get()
            ->pluck('count', 'codec')
            ->toArray();

        return [
            'total_streams' => $totalStreams,
            'active_streams' => $activeStreams,
            'avg_bitrate' => round($avgBitrate ?? 0, 2),
            'avg_latency_ms' => round($avgLatency ?? 0, 2),
            'total_watch_time_hours' => round($totalWatchTime / 3600, 2),
            'error_rate_percent' => $errorRate,
            'total_sessions' => $totalStreamSessions,
            'codec_distribution' => $codecDistribution,
        ];
    }

    private function getUserRegistrationTrends(?Carbon $start = null, ?Carbon $end = null): array
    {
        $start ??= now()->subDays(30)->startOfDay();
        $end ??= now()->endOfDay();

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
            $result[] = [
                'date' => $current->format('M d'),
                'count' => (int) ($data->get($dateStr)?->count ?? 0),
            ];
            $current->addDay();
        }

        return $result;
    }

    private function getRevenueTrends(?Carbon $start = null, ?Carbon $end = null): array
    {
        $start ??= now()->subDays(30)->startOfDay();
        $end ??= now()->endOfDay();

        $data = Invoice::select(
            DB::raw('DATE(created_at) as date'),
            DB::raw('SUM(total) as total')
        )
            ->where('status', 'paid')
            ->whereBetween('created_at', [$start, $end])
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->keyBy('date');

        $result = [];
        $current = $start->copy();
        while ($current <= $end) {
            $dateStr = $current->format('Y-m-d');
            $result[] = [
                'date' => $current->format('M d'),
                'revenue' => (float) ($data->get($dateStr)?->total ?? 0),
            ];
            $current->addDay();
        }

        return $result;
    }

    private function getBandwidthByServer(?Carbon $start = null, ?Carbon $end = null): array
    {
        $start ??= now()->subDays(30)->startOfDay();
        $end ??= now()->endOfDay();

        return StreamingServer::all()
            ->map(function ($server) use ($start, $end) {
                $bytesSent = StreamingLog::where('streaming_server_id', $server->id)
                    ->whereBetween('created_at', [$start, $end])
                    ->where('action', 'start')
                    ->sum('bytes_sent');

                return [
                    'server_id' => $server->id,
                    'name' => $server->name,
                    'location' => $server->location,
                    'bandwidth_gb' => round($bytesSent / (1024 * 1024 * 1024), 2),
                    'current_connections' => $server->current_connections,
                    'max_connections' => $server->max_connections,
                    'utilization_percent' => $server->max_connections > 0
                        ? round(($server->current_connections / $server->max_connections) * 100, 1)
                        : 0,
                ];
            })
            ->toArray();
    }

    private function getTopChannelsByViews(?Carbon $start = null, ?Carbon $end = null): array
    {
        $start ??= now()->subDays(30)->startOfDay();
        $end ??= now()->endOfDay();

        return Channel::query()
            ->select('channels.id', 'channels.name', 'channels.logo_url', 'channels.view_count')
            ->leftJoin('streaming_logs', 'streaming_logs.channel_id', '=', 'channels.id')
            ->whereBetween('streaming_logs.created_at', [$start, $end])
            ->where('streaming_logs.action', 'start')
            ->groupBy('channels.id', 'channels.name', 'channels.logo_url', 'channels.view_count')
            ->orderByDesc('channels.view_count')
            ->limit(15)
            ->get()
            ->map(fn ($channel) => [
                'id' => $channel->id,
                'name' => $channel->name,
                'logo_url' => $channel->logo_url,
                'view_count' => $channel->view_count,
            ])
            ->toArray();
    }

    private function getServerHealthSummary(): array
    {
        return StreamingServer::all()
            ->map(fn ($server) => [
                'id' => $server->id,
                'name' => $server->name,
                'host' => $server->host,
                'location' => $server->location,
                'status' => $server->is_active ? 'active' : 'inactive',
                'current_connections' => $server->current_connections,
                'max_connections' => $server->max_connections,
                'utilization_percent' => $server->max_connections > 0
                    ? round(($server->current_connections / $server->max_connections) * 100, 1)
                    : 0,
                'provider' => $server->provider,
            ])
            ->toArray();
    }
}

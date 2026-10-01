<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminChannel\AdminChannel;
use App\Services\AdminChannel\MyChannelHlsService;
use App\Services\SystemMonitorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Monitoring dashboard — host metrics (CPU, memory, disks, per-NIC traffic)
 * plus the state of the My Channel playouts and the playlists driving them.
 */
class MonitoringController extends Controller
{
    public function index(Request $request): Response|JsonResponse
    {
        $payload = $this->payload();

        if ($request->expectsJson()) {
            return response()->json(['data' => $payload]);
        }

        return Inertia::render('Admin/Monitoring/Index', [
            'monitoring' => $payload,
        ]);
    }

    /**
     * Lightweight poll target for the realtime charts. Records a sample in the
     * rolling history and returns the same shape as the page payload so the
     * frontend can swap it in wholesale.
     */
    public function metrics(): JsonResponse
    {
        return response()->json(['data' => $this->payload()]);
    }

    private function payload(): array
    {
        $system = app(SystemMonitorService::class)->monitoring();
        $channels = $this->channelStatuses();

        return [
            'system' => $system,
            'channels' => $channels,
            'channels_summary' => [
                'total' => count($channels),
                'running' => count(array_filter($channels, fn (array $c) => $c['state'] === 'running')),
                'stalled' => count(array_filter($channels, fn (array $c) => $c['state'] === 'stalled')),
                'stopped' => count(array_filter($channels, fn (array $c) => $c['state'] === 'stopped')),
                'playlist_items' => array_sum(array_column($channels, 'playlist_items')),
            ],
        ];
    }

    /**
     * Every My Channel with the playlist that feeds it and whether its playout
     * process is actually alive and advancing.
     *
     * @return array<int, array<string, mixed>>
     */
    private function channelStatuses(): array
    {
        $hls = app(MyChannelHlsService::class);

        return AdminChannel::query()
            ->where('is_my_channel', true)
            ->withCount([
                'myChannelPlaylist',
                'myChannelPlaylist as active_playlist_items_count' => fn ($query) => $query->where('is_active', true),
            ])
            ->orderBy('channel_number')
            ->get()
            ->map(function (AdminChannel $channel) use ($hls) {
                $running = $hls->isRunning($channel);
                $stalled = $running && $hls->isStalled($channel);

                return [
                    'id' => $channel->id,
                    'name' => $channel->channel_name,
                    'slug' => $channel->channel_slug,
                    'channel_number' => $channel->channel_number,
                    'broadcast_status' => $channel->broadcast_status,
                    'playlist_type' => $channel->playlist_type,
                    'playout_mode' => $channel->playout_mode,
                    'loop_playlist' => (bool) $channel->loop_playlist,
                    'playlist_items' => (int) $channel->my_channel_playlist_count,
                    'active_playlist_items' => (int) $channel->active_playlist_items_count,
                    'running' => $running,
                    'stalled' => $stalled,
                    'state' => $stalled ? 'stalled' : ($running ? 'running' : 'stopped'),
                    'last_broadcast' => optional($channel->last_broadcast)->toIso8601String(),
                ];
            })
            ->toArray();
    }
}

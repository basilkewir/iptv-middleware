<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminChannel\AdminChannel;
use App\Models\AdminChannel\AdminChannelAnalytics;
use App\Models\AdminChannel\AdminChannelBroadcastLog;
use App\Models\AdminChannel\AdminChannelOverlay;
use App\Models\AdminChannel\AdminChannelPlaylistItem;
use App\Models\AdminChannel\AdminChannelSchedule;
use App\Models\AdminChannel\AdminChannelSubscription;
use App\Models\AdminChannel\AdminChannelViewLog;
use App\Models\AdminChannel\MyChannelBroadcast;
use App\Models\AdminChannel\MyChannelContent;
use App\Models\AdminChannel\MyChannelMediaFolder;
use App\Models\AdminChannel\MyChannelPlaylist;
use App\Models\AdminChannel\MyChannelSetting;
use App\Models\Bouquet;
use App\Models\ContentCategory;
use App\Models\EPGSource;
use App\Models\SubscriptionPackage;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str as StrHelper;
use App\Services\AdminChannel\AdminChannelService;
use App\Services\AdminChannel\MyChannelHlsService;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class AdminChannelController extends Controller
{
    public function __construct(
        protected AdminChannelService $adminChannelService
    ) {}

    public function index(Request $request): InertiaResponse|JsonResponse
    {
        $filters = [
            'search' => $request->input('search'),
            'is_active' => $request->input('is_active'),
            'channel_type' => $request->input('channel_type'),
            'is_my_channel' => true,
            'is_featured' => $request->input('is_featured'),
            'is_public' => $request->input('is_public'),
            'category' => $request->input('category'),
            'country' => $request->input('country'),
            'language' => $request->input('language'),
            'broadcast_status' => $request->input('broadcast_status'),
            'playout_mode' => $request->input('playout_mode'),
            'sort_by' => $request->input('sort_by', 'created_at'),
            'sort_direction' => $request->input('sort_direction', 'desc'),
            'per_page' => $request->input('per_page', 20),
        ];

        $channels = $this->adminChannelService->getAllChannels($filters);

        if ($request->expectsJson()) {
            return response()->json(['data' => $channels]);
        }

        return Inertia::render('Admin/Channels/MyChannel/Index', [
            'channels' => $channels,
        ]);
    }

    public function create(): InertiaResponse
    {
        return Inertia::render('Admin/Channels/MyChannel/Wizard', [
            'categories' => ContentCategory::where('is_active', true)->get(),
            'bouquets' => Bouquet::where('is_active', true)->get(),
            'packages' => SubscriptionPackage::where('is_active', true)->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'channel_name' => 'required|string|max:255',
            'channel_slug' => 'nullable|string|alpha_dash|max:255|unique:admin_channels,channel_slug',
            'channel_number' => 'nullable|integer',
            'channel_type' => 'required|in:admin,user,system,custom',
            'is_my_channel' => 'boolean',
            'playlist_type' => 'nullable|in:continuous,scheduled,mixed',
            'description' => 'nullable|string',
            'logo_url' => 'nullable|string|max:500',
            'banner_url' => 'nullable|string|max:500',
            'background_color' => 'nullable|string|max:7',
            'accent_color' => 'nullable|string|max:7',
            'text_color' => 'nullable|string|max:7',
            'watermark_url' => 'nullable|string|max:500',
            'stream_url' => 'nullable|string|max:500',
            'stream_type' => 'required|in:hls,rtmp,mpegts,http,srt',
            'stream_key' => 'nullable|string|max:100',
            'output_resolution' => 'nullable|string|max:20',
            'output_bitrate' => 'nullable|integer',
            'output_frame_rate' => 'nullable|decimal:5,2',
            'video_codec' => 'nullable|string|max:50',
            'audio_codec' => 'nullable|string|max:50',
            'broadcast_status' => 'required|in:offline,ready,scheduled,live,ended,error',
            'broadcast_mode' => 'required|in:manual,auto,scheduled',
            'scheduled_start' => 'nullable|date',
            'scheduled_end' => 'nullable|date',
            'last_broadcast' => 'nullable|date',
            'timezone' => 'required|string|max:50',
            'duration_type' => 'required|in:continuous,limited,specific',
            'playout_mode' => 'required|in:playlist,schedule,live_input,mixed',
            'default_duration' => 'required|integer|min:0',
            'loop_playlist' => 'boolean',
            'shuffle_mode' => 'boolean',
            'transition_type' => 'required|in:cut,fade,slide,dissolve',
            'transition_duration' => 'required|integer|min:0|max:30',
            'enable_ticker' => 'boolean',
            'ticker_text' => 'nullable|string',
            'ticker_speed' => 'required|integer|min:10|max:100',
            'ticker_color' => 'nullable|string|max:7',
            'ticker_background' => 'nullable|string|max:9',
            'ticker_direction' => 'required|in:left,right,up,down',
            'enable_overlay_logo' => 'boolean',
            'overlay_logo_position' => 'required|in:top-left,top-right,bottom-left,bottom-right,center',
            'overlay_logo_x' => 'nullable|numeric|min:0|max:100',
            'overlay_logo_y' => 'nullable|numeric|min:0|max:100',
            'overlay_logo_size' => 'required|integer|min:50|max:200',
            'overlay_logo_opacity' => 'required|numeric|min:0|max:1',
            'enable_overlay_clock' => 'boolean',
            'overlay_clock_position' => 'required|in:top-left,top-right,bottom-left,bottom-right',
            'overlay_clock_x' => 'nullable|numeric|min:0|max:100',
            'overlay_clock_y' => 'nullable|numeric|min:0|max:100',
            'overlay_clock_format' => 'required|in:HH:MM:SS,HH:MM,MM/DD/YYYY,YYYY-MM-DD',
            'enable_watermark' => 'boolean',
            'watermark_position' => 'required|in:top-left,top-right,bottom-left,bottom-right',
            'watermark_opacity' => 'required|numeric|min:0|max:1',
            'content_owner' => 'nullable|string|max:255',
            'license_type' => 'required|in:free,premium,subscription,pay_per_view,restricted',
            'license_expiry' => 'nullable|date',
            'content_restrictions' => 'nullable|array',
            'region_restrictions' => 'nullable|array',
            'is_public' => 'boolean',
            'is_featured' => 'boolean',
            'is_adult' => 'boolean',
            'featured_order' => 'nullable|integer|min:0',
            'require_subscription' => 'boolean',
            'subscription_package_id' => 'nullable|exists:subscription_packages,id',
            'genre' => 'nullable|string|max:100',
            'category' => 'nullable|string|max:100',
            'language' => 'required|string|max:10',
            'country' => 'nullable|string|max:100',
            'tags' => 'nullable|array',
            'package_ids' => 'nullable|array',
            'bouquet_ids' => 'nullable|array',
            'transcoding_device' => 'nullable|in:cpu,gpu',
            'settings' => 'nullable|array',
        ]);

        $user = $request->user();

        $channel = $this->adminChannelService->createChannel($data, $user);

        if (! empty($data['settings'])) {
            $this->adminChannelService->upsertMyChannelSettings($channel, $data['settings']);
        }

        return redirect()->route('admin.admin.channels.index');
    }

    public function show(Request $request, AdminChannel $channel): InertiaResponse|JsonResponse
    {
        $channel = $channel->load(['packages', 'bouquets', 'subscriptions', 'playlistItems', 'schedules', 'overlays', 'myChannelSettings']);

        if ($request->expectsJson()) {
            return response()->json(['data' => $channel]);
        }

        return Inertia::render('Admin/Channels/MyChannel/Show', [
            'channel' => $channel,
            'categories' => ContentCategory::where('is_active', true)->get(),
            'bouquets' => Bouquet::where('is_active', true)->get(),
            'packages' => SubscriptionPackage::where('is_active', true)->get(),
        ]);
    }

    public function edit(Request $request, AdminChannel $channel): InertiaResponse
    {
        $channel = $channel->load(['packages', 'bouquets', 'playlistItems', 'overlays']);

        return Inertia::render('Admin/Channels/MyChannel/Wizard', [
            'channel' => $channel,
            'categories' => ContentCategory::where('is_active', true)->get(),
            'bouquets' => Bouquet::where('is_active', true)->get(),
            'packages' => SubscriptionPackage::where('is_active', true)->get(),
        ]);
    }

    public function update(Request $request, AdminChannel $channel): RedirectResponse
    {
        $data = $request->validate([
            'channel_name' => 'sometimes|required|string|max:255',
            'channel_slug' => 'sometimes|nullable|string|alpha_dash|max:255|unique:admin_channels,channel_slug,' . $channel->id,
            'channel_number' => 'sometimes|nullable|integer',
            'channel_type' => 'sometimes|required|in:admin,user,system,custom',
            'is_my_channel' => 'sometimes|boolean',
            'playlist_type' => 'sometimes|nullable|in:continuous,scheduled,mixed',
            'description' => 'sometimes|nullable|string',
            'logo_url' => 'sometimes|nullable|string|max:500',
            'banner_url' => 'sometimes|nullable|string|max:500',
            'background_color' => 'sometimes|nullable|string|max:7',
            'accent_color' => 'sometimes|nullable|string|max:7',
            'text_color' => 'sometimes|nullable|string|max:7',
            'watermark_url' => 'sometimes|nullable|string|max:500',
            'stream_url' => 'sometimes|nullable|string|max:500',
            'stream_type' => 'sometimes|required|in:hls,rtmp,mpegts,http,srt',
            'stream_key' => 'sometimes|nullable|string|max:100',
            'output_resolution' => 'sometimes|nullable|string|max:20',
            'output_bitrate' => 'sometimes|nullable|integer',
            'output_frame_rate' => 'sometimes|nullable|decimal:5,2',
            'video_codec' => 'sometimes|nullable|string|max:50',
            'audio_codec' => 'sometimes|nullable|string|max:50',
            'broadcast_status' => 'sometimes|required|in:offline,ready,scheduled,live,ended,error',
            'broadcast_mode' => 'sometimes|required|in:manual,auto,scheduled',
            'scheduled_start' => 'sometimes|nullable|date',
            'scheduled_end' => 'sometimes|nullable|date',
            'last_broadcast' => 'sometimes|nullable|date',
            'timezone' => 'sometimes|required|string|max:50',
            'duration_type' => 'sometimes|required|in:continuous,limited,specific',
            'playout_mode' => 'sometimes|required|in:playlist,schedule,live_input,mixed',
            'default_duration' => 'sometimes|required|integer|min:0',
            'loop_playlist' => 'sometimes|boolean',
            'shuffle_mode' => 'sometimes|boolean',
            'transition_type' => 'sometimes|required|in:cut,fade,slide,dissolve',
            'transition_duration' => 'sometimes|required|integer|min:0|max:30',
            'enable_ticker' => 'sometimes|boolean',
            'ticker_text' => 'sometimes|nullable|string',
            'ticker_speed' => 'sometimes|required|integer|min:10|max:100',
            'ticker_color' => 'sometimes|nullable|string|max:7',
            'ticker_background' => 'sometimes|nullable|string|max:9',
            'ticker_direction' => 'sometimes|required|in:left,right,up,down',
            'enable_overlay_logo' => 'sometimes|boolean',
            'overlay_logo_position' => 'sometimes|required|in:top-left,top-right,bottom-left,bottom-right,center',
            'overlay_logo_x' => 'sometimes|nullable|numeric|min:0|max:100',
            'overlay_logo_y' => 'sometimes|nullable|numeric|min:0|max:100',
            'overlay_logo_size' => 'sometimes|required|integer|min:50|max:200',
            'overlay_logo_opacity' => 'sometimes|required|numeric|min:0|max:1',
            'enable_overlay_clock' => 'sometimes|boolean',
            'overlay_clock_position' => 'sometimes|required|in:top-left,top-right,bottom-left,bottom-right',
            'overlay_clock_x' => 'sometimes|nullable|numeric|min:0|max:100',
            'overlay_clock_y' => 'sometimes|nullable|numeric|min:0|max:100',
            'overlay_clock_format' => 'sometimes|required|in:HH:MM:SS,HH:MM,MM/DD/YYYY,YYYY-MM-DD',
            'enable_watermark' => 'sometimes|boolean',
            'watermark_position' => 'sometimes|required|in:top-left,top-right,bottom-left,bottom-right',
            'watermark_opacity' => 'sometimes|required|numeric|min:0|max:1',
            'content_owner' => 'sometimes|nullable|string|max:255',
            'license_type' => 'sometimes|required|in:free,premium,subscription,pay_per_view,restricted',
            'license_expiry' => 'sometimes|nullable|date',
            'content_restrictions' => 'sometimes|nullable|array',
            'region_restrictions' => 'sometimes|nullable|array',
            'is_public' => 'sometimes|boolean',
            'is_featured' => 'sometimes|boolean',
            'is_adult' => 'sometimes|boolean',
            'featured_order' => 'sometimes|nullable|integer|min:0',
            'require_subscription' => 'sometimes|boolean',
            'subscription_package_id' => 'sometimes|nullable|exists:subscription_packages,id',
            'genre' => 'sometimes|nullable|string|max:100',
            'category' => 'sometimes|nullable|string|max:100',
            'language' => 'sometimes|required|string|max:10',
            'country' => 'sometimes|nullable|string|max:100',
            'tags' => 'sometimes|nullable|array',
            'package_ids' => 'sometimes|nullable|array',
            'bouquet_ids' => 'sometimes|nullable|array',
            'transcoding_device' => 'sometimes|nullable|in:cpu,gpu',
            'settings' => 'sometimes|nullable|array',
        ]);

        $this->adminChannelService->updateChannel($channel, $data);

        if (! empty($data['settings'])) {
            $this->adminChannelService->upsertMyChannelSettings($channel, $data['settings']);
        }

        return redirect()->route('admin.admin.channels.edit', $channel->channel_slug);
    }

    public function destroy(Request $request, AdminChannel $channel): RedirectResponse
    {
        $this->adminChannelService->deleteChannel($channel);

        return redirect()->route('admin.admin.channels.index');
    }

    public function toggleStatus(Request $request, AdminChannel $channel): JsonResponse
    {
        $isActive = $request->input('is_active', false);

        $updatedChannel = $this->adminChannelService->toggleStatus($channel, $isActive);

        return response()->json(['channel' => $updatedChannel]);
    }

    public function toggleFeatured(Request $request, AdminChannel $channel): JsonResponse
    {
        $isFeatured = $request->input('is_featured', false);

        $updatedChannel = $this->adminChannelService->toggleFeatured($channel, $isFeatured);

        return response()->json(['channel' => $updatedChannel]);
    }

    public function approve(Request $request, AdminChannel $channel): JsonResponse
    {
        $user = $request->user();

        $updatedChannel = $this->adminChannelService->approveChannel($channel, $user);

        return response()->json(['channel' => $updatedChannel]);
    }

    public function testStream(Request $request, AdminChannel $channel): JsonResponse
    {
        $streamUrl = $request->input('stream_url');
        $sourceType = $request->input('source_type', 'stream');
        $startTime = microtime(true);

        if (empty($streamUrl)) {
            return response()->json([
                'success' => false,
                'data' => ['status' => 'offline', 'error' => 'No stream URL configured'],
            ]);
        }

        if ($sourceType === 'youtube' || str_contains($streamUrl, 'youtube.com') || str_contains($streamUrl, 'youtu.be')) {
            $ytService = new \App\Services\YouTubeService();
            $result = $ytService->verifyUrl($streamUrl);
            $responseTime = round((microtime(true) - $startTime) * 1000);
            if ($result['success']) {
                return response()->json([
                    'success' => true,
                    'data' => [
                        'status' => 'online',
                        'stream_url' => $result['stream_url'],
                        'response_time' => $responseTime,
                        'detected_type' => 'hls',
                    ],
                ]);
            }
            return response()->json([
                'success' => false,
                'data' => [
                    'status' => 'offline',
                    'response_time' => $responseTime,
                    'error' => $result['error'] ?? 'YouTube resolution failed',
                ],
            ]);
        }

        $probeResult = $this->probeStreamLocal($streamUrl);
        $responseTime = round((microtime(true) - $startTime) * 1000);

        return response()->json([
            'success' => $probeResult['online'],
            'data' => [
                'status' => $probeResult['online'] ? 'online' : 'offline',
                'http_code' => $probeResult['http_code'] ?? 0,
                'response_time' => $responseTime,
                'detected_type' => $probeResult['detected_type'] ?? null,
                'codec' => $probeResult['codec'] ?? null,
                'resolution' => $probeResult['resolution'] ?? null,
                'error' => $probeResult['error'] ?? null,
            ],
        ]);
    }

    private function probeStreamLocal(string $url): array
    {
        $result = ['online' => false, 'error' => null];

        $isMulticast = str_starts_with($url, 'udp://') || str_starts_with($url, 'rtp://');
        $isHls = str_contains(strtolower($url), '.m3u8');
        $timeout = $isMulticast ? 10 : 15;

        if ($isMulticast) {
            $cmd = sprintf(
                'timeout %d ffprobe -v error -show_streams -show_format -of json -analyzeduration 5M -probesize 5M %s 2>&1; echo "EXIT:$?"',
                $timeout,
                escapeshellarg($url)
            );
        } else {
            $cmd = sprintf(
                'timeout %d ffprobe -v error -show_streams -show_format -of json -analyzeduration 10M -probesize 1M -user_agent "IPTV-Middleware/1.0" %s 2>&1; echo "EXIT:$?"',
                $timeout,
                escapeshellarg($url)
            );
        }

        $output = shell_exec($cmd);

        $exitCode = 1;
        if (preg_match('/EXIT:(\d+)\s*$/', $output, $m)) {
            $exitCode = (int) $m[1];
            $output = preg_replace('/EXIT:\d+\s*$/', '', $output);
        }

        $data = json_decode(trim($output), true);

        if ($exitCode === 0 && $data && isset($data['streams'])) {
            $result['online'] = true;

            foreach ($data['streams'] as $stream) {
                if (($stream['codec_type'] ?? '') === 'video' && ! isset($result['codec'])) {
                    $result['codec'] = $stream['codec_name'] ?? null;
                    $result['resolution'] = ($stream['width'] ?? 0) . 'x' . ($stream['height'] ?? 0);
                }
            }

            $fmt = $data['format'] ?? [];
            $result['detected_type'] = $isHls ? 'hls' : ($isMulticast ? 'udp' : 'http');
        } else {
            $errorMsg = $data['error']['message'] ?? null;
            if (preg_match('/error.*?((?:Connection|HTTP|403|404|timeout|refused|No route).*)/i', $output, $em)) {
                $errorMsg = $em[1];
            }
            $result['error'] = $errorMsg ?: 'Stream unreachable (exit=' . $exitCode . ')';
        }

        return $result;
    }

    public function scanMulticast(Request $request): JsonResponse
    {
        $url = $request->input('url');

        if (empty($url)) {
            return response()->json([
                'success' => false,
                'data' => ['programs' => []],
            ]);
        }

        // Run ffprobe to detect programs in the multi-program TS
        $timeout = 20;
        $command = sprintf(
            'timeout %d ffprobe -v error -show_streams -show_format -of json -analyzeduration 10M -probesize 1M -user_agent "IPTV-Middleware-Scanner" %s 2>&1; echo "EXIT:$?"',
            $timeout,
            escapeshellarg($url)
        );

        $output = shell_exec($command . ' 2>&1');

        // Extract exit code
        $exitCode = 1;
        if (preg_match('/EXIT:(\d+)\s*$/', $output, $m)) {
            $exitCode = (int) $m[1];
            $output = preg_replace('/EXIT:\d+\s*$/', '', $output);
        }

        $data = json_decode(trim($output), true);

        $programs = [];

        if ($exitCode === 0 && $data && (isset($data['streams']) || isset($data['format']))) {
            // Extract program IDs from the streams
            if (isset($data['programs'])) {
                foreach ($data['programs'] as $prog) {
                    $programs[] = [
                        'program_id' => $prog['program_id'] ?? null,
                        'name' => $prog['name'] ?? 'Program ' . $prog['program_id'],
                    ];
                }
            } else {
                // Fallback: assign program IDs based on stream order
                $streamCount = isset($data['streams']) ? count($data['streams']) : 0;
                for ($i = 0; $i < $streamCount; $i++) {
                    $programs[] = [
                        'program_id' => $i + 1,
                        'name' => 'Program ' . ($i + 1),
                    ];
                }
            }
        }

        return response()->json([
            'success' => $exitCode === 0,
            'data' => ['programs' => $programs],
        ]);
    }

    public function bulkDelete(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'exists:admin_channels,id',
        ]);

        $ids = $validated['ids'];

        if (empty($ids)) {
            return response()->json(['message' => 'No channels selected'], 400);
        }

        AdminChannel::whereIn('id', $ids)->delete();

        return response()->json(['message' => count($ids) . ' channel(s) deleted successfully']);
    }

    public function bulkToggleStatus(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'exists:admin_channels,id',
            'is_active' => 'required|boolean',
        ]);

        $ids = $validated['ids'];
        $isActive = $validated['is_active'];

        if (empty($ids)) {
            return response()->json(['message' => 'No channels selected'], 400);
        }

        AdminChannel::whereIn('id', $ids)->update(['is_active' => $isActive]);

        return response()->json(['message' => count($ids) . ' channel(s) updated successfully']);
    }

    public function scanQualityAll(Request $request): JsonResponse
    {
        $result = $this->adminChannelService->scanQualityForAllChannels();

        return response()->json($result);
    }

    public function generateEpg(AdminChannel $channel): JsonResponse
    {
        $epgData = $this->adminChannelService->generateEpgForChannel($channel);

        return response()->json(['epg_data' => $epgData]);
    }

    public function getPlaylist(AdminChannel $channel): JsonResponse
    {
        $playlist = $this->adminChannelService->getPlaylistForChannel($channel);

        return response()->json(['playlist' => $playlist]);
    }

    public function getOverlays(AdminChannel $channel): JsonResponse
    {
        $overlays = $this->adminChannelService->getActiveOverlays($channel);

        return response()->json(['overlays' => $overlays]);
    }

    public function getStats(Request $request, AdminChannel $channel): JsonResponse
    {
        $dateRange = $request->only(['start_date', 'end_date']);

        $stats = $this->adminChannelService->getChannelStats($channel, $dateRange);

        return response()->json(['stats' => $stats]);
    }

    public function addPlaylistItem(Request $request, AdminChannel $channel): JsonResponse
    {
        $data = $request->validate([
            'content_type' => 'required|string|max:50',
            'content_id' => 'nullable|integer',
            'content_title' => 'nullable|string|max:255',
            'content_description' => 'nullable|string',
            'media_url' => 'required|string|max:500',
            'thumbnail_url' => 'nullable|string|max:500',
            'media_duration' => 'nullable|integer|min:0',
            'file_size' => 'nullable|integer|min:0',
            'order_index' => 'nullable|integer|min:0',
            'start_time_offset' => 'nullable|integer|min:0',
            'end_time_offset' => 'nullable|integer|min:0',
            'transition_duration' => 'nullable|integer|min:0',
            'transition_type' => 'nullable|string|in:cut,fade,slide,dissolve',
            'scheduled_start' => 'nullable|date',
            'scheduled_end' => 'nullable|date',
            'is_enabled' => 'boolean',
            'metadata' => 'nullable|array',
        ]);

        $playlistItem = $this->adminChannelService->addPlaylistItem($channel, $data);

        return response()->json(['playlist_item' => $playlistItem]);
    }

    public function updatePlaylistItem(Request $request, AdminChannel $channel, AdminChannelPlaylistItem $playlistItem): JsonResponse
    {
        $data = $request->validate([
            'content_type' => 'sometimes|string|max:50',
            'content_id' => 'sometimes|nullable|integer',
            'content_title' => 'sometimes|nullable|string|max:255',
            'content_description' => 'sometimes|nullable|string',
            'media_url' => 'sometimes|string|max:500',
            'thumbnail_url' => 'sometimes|nullable|string|max:500',
            'media_duration' => 'sometimes|nullable|integer|min:0',
            'file_size' => 'sometimes|nullable|integer|min:0',
            'order_index' => 'sometimes|nullable|integer|min:0',
            'start_time_offset' => 'sometimes|nullable|integer|min:0',
            'end_time_offset' => 'sometimes|nullable|integer|min:0',
            'transition_duration' => 'sometimes|nullable|integer|min:0',
            'transition_type' => 'sometimes|nullable|string|in:cut,fade,slide,dissolve',
            'scheduled_start' => 'sometimes|nullable|date',
            'scheduled_end' => 'sometimes|nullable|date',
            'is_enabled' => 'sometimes|boolean',
            'metadata' => 'sometimes|nullable|array',
        ]);

        $updatedPlaylistItem = $this->adminChannelService->updatePlaylistItem($playlistItem, $data);

        return response()->json(['playlist_item' => $updatedPlaylistItem]);
    }

    public function removePlaylistItem(Request $request, AdminChannel $channel, AdminChannelPlaylistItem $playlistItem): JsonResponse
    {
        $this->adminChannelService->deletePlaylistItem($playlistItem);

        return response()->json(['message' => 'Playlist item removed successfully']);
    }

    public function reorderPlaylistItems(Request $request, AdminChannel $channel): JsonResponse
    {
        $data = $request->validate([
            'item_ids' => 'required|array',
            'item_ids.*' => 'integer|exists:admin_channel_playlist_items,id',
        ]);

        $this->adminChannelService->reorderPlaylistItems($channel, $data['item_ids']);

        return response()->json(['message' => 'Playlist items reordered successfully']);
    }

    public function addSchedule(Request $request, AdminChannel $channel): JsonResponse
    {
        $data = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'schedule_type' => 'required|in:once,daily,weekly,monthly',
            'schedule_days' => 'nullable|array',
            'start_time' => 'required|date',
            'end_time' => 'nullable|date',
            'status' => 'required|in:scheduled,active,completed,cancelled',
            'playlist_ids' => 'nullable|array',
            'overlay_ids' => 'nullable|array',
            'metadata' => 'nullable|array',
        ]);

        $schedule = $this->adminChannelService->addSchedule($channel, $data);

        return response()->json(['schedule' => $schedule]);
    }

    public function updateSchedule(Request $request, AdminChannel $channel, AdminChannelSchedule $schedule): JsonResponse
    {
        $data = $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'description' => 'sometimes|nullable|string',
            'schedule_type' => 'sometimes|required|in:once,daily,weekly,monthly',
            'schedule_days' => 'sometimes|nullable|array',
            'start_time' => 'sometimes|required|date',
            'end_time' => 'sometimes|nullable|date',
            'status' => 'sometimes|required|in:scheduled,active,completed,cancelled',
            'playlist_ids' => 'sometimes|nullable|array',
            'overlay_ids' => 'sometimes|nullable|array',
            'metadata' => 'sometimes|nullable|array',
        ]);

        $updatedSchedule = $this->adminChannelService->updateSchedule($schedule, $data);

        return response()->json(['schedule' => $updatedSchedule]);
    }

    public function removeSchedule(Request $request, AdminChannel $channel, AdminChannelSchedule $schedule): JsonResponse
    {
        $this->adminChannelService->deleteSchedule($schedule);

        return response()->json(['message' => 'Schedule removed successfully']);
    }

    public function addOverlay(Request $request, AdminChannel $channel): JsonResponse
    {
        $data = $request->validate([
            'overlay_name' => 'required|string|max:255',
            'overlay_type' => 'required|in:logo,clock,ticker,watermark,custom',
            'overlay_url' => 'nullable|string|max:500',
            'overlay_text' => 'nullable|string',
            'position' => 'required|string|in:top-left,top-right,bottom-left,bottom-right,center',
            'size' => 'required|integer|min:50|max:200',
            'opacity' => 'required|numeric|min:0|max:1',
            'color' => 'nullable|string|max:7',
            'background_color' => 'nullable|string|max:7',
            'z_index' => 'required|integer|min:0|max:100',
            'is_enabled' => 'boolean',
            'start_time' => 'nullable|date',
            'end_time' => 'nullable|date',
            'animation' => 'nullable|array',
        ]);

        $overlay = $this->adminChannelService->addOverlay($channel, $data);

        return response()->json(['overlay' => $overlay]);
    }

    public function updateOverlay(Request $request, AdminChannel $channel, AdminChannelOverlay $overlay): JsonResponse
    {
        $data = $request->validate([
            'overlay_name' => 'sometimes|required|string|max:255',
            'overlay_type' => 'sometimes|required|in:logo,clock,ticker,watermark,custom',
            'overlay_url' => 'sometimes|nullable|string|max:500',
            'overlay_text' => 'sometimes|nullable|string',
            'position' => 'sometimes|required|string|in:top-left,top-right,bottom-left,bottom-right,center',
            'size' => 'sometimes|required|integer|min:50|max:200',
            'opacity' => 'sometimes|required|numeric|min:0|max:1',
            'color' => 'sometimes|nullable|string|max:7',
            'background_color' => 'sometimes|nullable|string|max:7',
            'z_index' => 'sometimes|required|integer|min:0|max:100',
            'is_enabled' => 'sometimes|boolean',
            'start_time' => 'sometimes|nullable|date',
            'end_time' => 'sometimes|nullable|date',
            'animation' => 'sometimes|nullable|array',
        ]);

        $updatedOverlay = $this->adminChannelService->updateOverlay($overlay, $data);

        return response()->json(['overlay' => $updatedOverlay]);
    }

    public function removeOverlay(Request $request, AdminChannel $channel, AdminChannelOverlay $overlay): JsonResponse
    {
        $this->adminChannelService->deleteOverlay($overlay);

        return response()->json(['message' => 'Overlay removed successfully']);
    }

    public function startBroadcast(Request $request, AdminChannel $channel): JsonResponse
    {
        $data = $request->validate([
            'event_type' => 'required|in:broadcast_start,broadcast_end,broadcast_error,stream_restart,quality_change,overlay_change,schedule_change',
            'title' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'stream_url' => 'nullable|string|max:500',
            'stream_type' => 'nullable|string|in:hls,rtmp,mpegts,http,srt',
            'quality' => 'nullable|string|in:240p,360p,480p,720p,1080p,4k',
            'viewers' => 'nullable|integer|min:0',
            'duration_seconds' => 'nullable|integer|min:0',
            'metadata' => 'nullable|array',
        ]);

        $log = $this->adminChannelService->startBroadcast($channel, $data);

        return response()->json(['broadcast_log' => $log]);
    }

    public function endBroadcast(Request $request, AdminChannel $channel): JsonResponse
    {
        $data = $request->validate([
            'event_type' => 'nullable|in:broadcast_end',
            'title' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'stream_url' => 'nullable|string|max:500',
            'stream_type' => 'nullable|string|in:hls,rtmp,mpegts,http,srt',
            'quality' => 'nullable|string|in:240p,360p,480p,720p,1080p,4k',
            'viewers' => 'nullable|integer|min:0',
            'duration_seconds' => 'nullable|integer|min:0',
            'metadata' => 'nullable|array',
        ]);

        $data['event_type'] = 'broadcast_end';

        $log = AdminChannelBroadcastLog::where('admin_channel_id', $channel->id)
            ->whereIn('event_type', ['broadcast_start', 'broadcast_running'])
            ->latest()
            ->first();

        if (! $log) {
            return response()->json(['message' => 'No active broadcast found'], 404);
        }

        $updatedLog = $this->adminChannelService->endBroadcast($log, $data);

        return response()->json(['broadcast_log' => $updatedLog]);
    }

    public function subscribeUser(Request $request, AdminChannel $channel): JsonResponse
    {
        $data = $request->validate([
            'user_id' => 'required|exists:users,id',
            'subscription_package_id' => 'nullable|exists:subscription_packages,id',
            'status' => 'required|in:active,inactive,expired,cancelled',
            'subscribed_at' => 'nullable|date',
            'expires_at' => 'nullable|date',
            'cancelled_at' => 'nullable|date',
            'metadata' => 'nullable|array',
        ]);

        $user = User::find($data['user_id']);
        $subscription = $this->adminChannelService->subscribeUserToChannel($channel, $user, $data);

        return response()->json(['subscription' => $subscription]);
    }

    public function unsubscribeUser(Request $request, AdminChannel $channel): JsonResponse
    {
        $data = $request->validate([
            'user_id' => 'required|exists:users,id',
            'status' => 'required|in:cancelled',
            'cancelled_at' => 'nullable|date',
            'metadata' => 'nullable|array',
        ]);

        $user = User::find($data['user_id']);
        $this->adminChannelService->unsubscribeUserFromChannel($channel, $user, $data);

        return response()->json(['message' => 'User unsubscribed successfully']);
    }

    public function getAnalytics(Request $request, AdminChannel $channel): JsonResponse
    {
        $days = $request->input('days', 30);
        $dateRange = [];

        if ($request->has('start_date') && $request->has('end_date')) {
            $dateRange = $request->only(['start_date', 'end_date']);
        }

        $analytics = $this->adminChannelService->getChannelAnalyticsSummary($channel, $days);

        if (!empty($dateRange)) {
            $analytics = $this->adminChannelService->getChannelStats($channel, $dateRange);
        }

        return response()->json(['analytics' => $analytics]);
    }

    public function generateDailyAnalytics(Request $request, AdminChannel $channel): JsonResponse
    {
        $date = $request->validate(['date' => 'required|date'])['date'];

        $analytics = $this->adminChannelService->generateAnalyticsForDate($channel, $date);

        return response()->json(['analytics' => $analytics]);
    }

    public function bulkImport(Request $request): JsonResponse
    {
        $request->validate([
            'channels' => 'required|array',
            'channels.*' => 'array',
        ]);

        $channelsData = $request->input('channels');
        $importedCount = 0;

        foreach ($channelsData as $channelData) {
            try {
                $this->adminChannelService->createChannel($channelData, $request->user());
                $importedCount++;
            } catch (\Exception $e) {
                continue;
            }
        }

        return response()->json([
            'message' => "Imported {$importedCount} channels successfully",
            'imported_count' => $importedCount,
        ]);
    }

    public function bulkExport(Request $request): JsonResponse
    {
        $filters = [
            'is_active' => $request->input('is_active'),
            'channel_type' => $request->input('channel_type'),
            'is_featured' => $request->input('is_featured'),
            'search' => $request->input('search'),
        ];

        $channels = $this->adminChannelService->getAllChannels($filters);

        return response()->json([
            'channels' => $channels->items(),
            'meta' => [
                'total' => $channels->total(),
                'per_page' => $channels->perPage(),
                'current_page' => $channels->currentPage(),
                'last_page' => $channels->lastPage(),
            ],
        ]);
    }

    public function uploadBrandingImage(Request $request): JsonResponse
    {
        $request->validate([
            'image' => 'required|image|max:5120',
            'field' => 'required|in:logo_url,banner_url,watermark_url',
        ]);

        $path = $request->file('image')->store('branding', 'public');

        return response()->json(['url' => Storage::disk('public')->url($path)]);
    }

    public function myChannelContent(Request $request, AdminChannel $channel): JsonResponse
    {
        $content = MyChannelContent::where('channel_id', $channel->id)
            ->with('folder')
            ->when($request->filled('is_featured'), fn($q) => $q->where('is_featured', true))
            ->when($request->filled('quality'), fn($q) => $q->where('quality_level', $request->input('quality')))
            ->when($request->filled('folder_id'), function ($q) use ($request) {
                $folderId = $request->input('folder_id');
                if (in_array($folderId, ['null', 'uncategorized', 'root'], true)) {
                    $q->whereNull('folder_id');
                } else {
                    $q->where('folder_id', (int) $folderId);
                }
            })
            ->orderBy('created_at', 'desc')
            ->paginate($request->input('per_page', 20));

        return response()->json(['content' => $content]);
    }

    public function uploadContent(Request $request, AdminChannel $channel): JsonResponse
    {
        $data = $request->validate([
            'file' => 'required|file|mimes:mp4,avi,mkv,mov,webm|max:2048000',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'folder_id' => 'nullable|integer',
        ]);

        if (! empty($data['folder_id']) && ! MyChannelMediaFolder::where('channel_id', $channel->id)->whereKey($data['folder_id'])->exists()) {
            throw ValidationException::withMessages(['folder_id' => 'Folder does not belong to this channel.']);
        }

        $file = $request->file('file');
        $userId = $request->user()->id;

        $fileName = time() . '_' . StrHelper::slug($data['title']) . '.' . $file->getClientOriginalExtension();
        $path = $file->storeAs('my_channel/' . $channel->id, $fileName, 'public');

        $thumbnailPath = null;
        $duration = null;
        $width = null;
        $height = null;
        $bitrate = null;
        $videoCodec = null;
        $audioCodec = null;
        $frameRate = null;
        $qualityLevel = 'hd';

        try {
            $ffprobe = app('ffmpeg.ffprobe', ['ffmpeg' => '/usr/bin/ffmpeg', 'ffprobe' => '/usr/bin/ffprobe']);
            $probe = $ffprobe->open(Storage::disk('public')->path($path));
            $format = $probe->getFormat();

            if ($format) {
                $duration = (int) $format->getDuration();
                $bitrate = (int) $format->getBitRate();
                $videoStream = collect($probe->getStreams())->first(fn($s) => $s->get('codec_type') === 'video');
                if ($videoStream) {
                    $width = (int) $videoStream->get('width');
                    $height = (int) $videoStream->get('height');
                    $videoCodec = $videoStream->get('codec_name');
                    $frameRate = $videoStream->get('avg_frame_rate');
                }
                $audioStream = collect($probe->getStreams())->first(fn($s) => $s->get('codec_type') === 'audio');
                if ($audioStream) {
                    $audioCodec = $audioStream->get('codec_name');
                }

                if ($height >= 2160) $qualityLevel = '4k';
                elseif ($height >= 1080) $qualityLevel = 'fhd';
                elseif ($height >= 720) $qualityLevel = 'hd';
                elseif ($height >= 480) $qualityLevel = 'sd';
                else $qualityLevel = 'low';
            }
        } catch (\Exception $e) {
            $duration = 0;
        }

        $thumbnailPath = $this->generateThumbnail(Storage::disk('public')->path($path), $channel->id);

        $content = MyChannelContent::create([
            'channel_id' => $channel->id,
            'folder_id' => $data['folder_id'] ?? null,
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'duration' => $duration,
            'file_name' => $fileName,
            'file_path' => $path,
            'file_size' => $file->getSize(),
            'thumbnail_url' => $thumbnailPath,
            'uploaded_by' => $userId,
            'quality_level' => $qualityLevel,
            'resolution_width' => $width,
            'resolution_height' => $height,
            'bitrate' => $bitrate,
            'video_codec' => $videoCodec,
            'audio_codec' => $audioCodec,
            'frame_rate' => $frameRate ?: 0,
        ]);

        // Prepare the normalized playout copy in the background
        try {
            \App\Jobs\PrepareMyChannelContent::dispatch($content->id);
        } catch (\Throwable $e) {
            Log::warning('Failed to dispatch content prepare', ['content_id' => $content->id, 'error' => $e->getMessage()]);
        }

        return response()->json(['content' => $content]);
    }

    protected function generateThumbnail(string $videoPath, int $channelId): ?string
    {
        try {
            $thumbnailName = 'thumb_' . time() . '_' . StrHelper::random(8) . '.jpg';
            $thumbnailPath = 'my_channel/' . $channelId . '/' . $thumbnailName;
            $fullThumbnailPath = Storage::disk('public')->path($thumbnailPath);

            $cmd = "/usr/bin/ffmpeg -i " . escapeshellarg($videoPath) .
                   " -ss 00:00:10 -vframes 1 -vf scale=320:-1 " .
                   escapeshellarg($fullThumbnailPath) . " 2>/dev/null";
            exec($cmd);

            return $thumbnailPath;
        } catch (\Exception $e) {
            return null;
        }
    }

    public function updateContent(Request $request, AdminChannel $channel, MyChannelContent $content): JsonResponse
    {
        $data = $request->validate([
            'title' => 'sometimes|required|string|max:255',
            'description' => 'sometimes|nullable|string',
            'folder_id' => 'sometimes|nullable|integer',
            'is_active' => 'sometimes|boolean',
            'is_featured' => 'sometimes|boolean',
            'featured_order' => 'sometimes|integer|min:0',
            'quality_level' => 'sometimes|in:4k,fhd,hd,sd,low',
        ]);

        if (array_key_exists('folder_id', $data) && $data['folder_id'] !== null
            && ! MyChannelMediaFolder::where('channel_id', $channel->id)->whereKey($data['folder_id'])->exists()) {
            throw ValidationException::withMessages(['folder_id' => 'Folder does not belong to this channel.']);
        }

        $content->update($data);

        return response()->json(['content' => $content->fresh('folder')]);
    }

    public function destroyContent(Request $request, AdminChannel $channel, MyChannelContent $content): JsonResponse
    {
        // Deleting a library item must also free the actual media on the
        // server: the raw file, its thumbnail and the prepared playout copy.
        if ($content->file_path) {
            Storage::disk('public')->delete($content->file_path);
        }
        if ($content->thumbnail_url) {
            Storage::disk('public')->delete($content->thumbnail_url);
        }

        $prepared = storage_path('app/streams/normalized/' . $channel->channel_slug . '/prepared_' . $content->id . '.mp4');
        @unlink($prepared);
        @unlink($prepared . '.sig.json');

        $content->delete();

        return response()->json(['message' => 'Content deleted successfully']);
    }

    // ─── My Channel Media Folders ──────────────────────────────────────────

    public function myChannelFolders(Request $request, AdminChannel $channel): JsonResponse
    {
        $folders = MyChannelMediaFolder::where('channel_id', $channel->id)
            ->withCount('contents')
            ->orderBy('name')
            ->get();

        return response()->json(['folders' => $folders]);
    }

    public function storeMyChannelFolder(Request $request, AdminChannel $channel): JsonResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'parent_id' => 'nullable|integer',
        ]);

        if (! empty($data['parent_id'])) {
            $parent = MyChannelMediaFolder::where('channel_id', $channel->id)->findOrFail($data['parent_id']);
            $data['parent_id'] = $parent->id;
        }

        $folder = MyChannelMediaFolder::create([
            'channel_id' => $channel->id,
            'parent_id' => $data['parent_id'] ?? null,
            'name' => trim($data['name']),
        ]);

        return response()->json(['folder' => $folder]);
    }

    public function updateMyChannelFolder(Request $request, AdminChannel $channel, MyChannelMediaFolder $folder): JsonResponse
    {
        if ($folder->channel_id !== $channel->id) {
            abort(404);
        }

        $data = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'parent_id' => 'sometimes|nullable|integer',
        ]);

        if (array_key_exists('parent_id', $data)) {
            $data['parent_id'] = $data['parent_id'] !== null
                ? MyChannelMediaFolder::where('channel_id', $channel->id)->findOrFail($data['parent_id'])->id
                : null;

            if ($data['parent_id'] !== null) {
                if ((int) $data['parent_id'] === (int) $folder->id) {
                    throw ValidationException::withMessages(['parent_id' => 'A folder cannot be its own parent.']);
                }
                if ($this->folderIsDescendant($folder, (int) $data['parent_id'])) {
                    throw ValidationException::withMessages(['parent_id' => 'Cannot move a folder into its own subfolder.']);
                }
            }
        }

        $folder->update($data);

        return response()->json(['folder' => $folder->fresh()]);
    }

    public function destroyMyChannelFolder(Request $request, AdminChannel $channel, MyChannelMediaFolder $folder): JsonResponse
    {
        if ($folder->channel_id !== $channel->id) {
            abort(404);
        }

        $parentId = $folder->parent_id;

        MyChannelMediaFolder::where('parent_id', $folder->id)->update(['parent_id' => $parentId]);
        MyChannelContent::where('folder_id', $folder->id)->update(['folder_id' => $parentId]);

        $folder->delete();

        return response()->json(['message' => 'Folder deleted']);
    }

    protected function folderIsDescendant(MyChannelMediaFolder $folder, int $candidateId): bool
    {
        $seen = [];

        do {
            $parent = MyChannelMediaFolder::whereKey($candidateId)->first();
            if (! $parent) {
                break;
            }
            if ((int) $parent->id === (int) $folder->id) {
                return true;
            }
            if (isset($seen[$parent->id])) {
                break;
            }
            $seen[$parent->id] = true;
            $candidateId = (int) ($parent->parent_id ?? 0);
        } while ($candidateId > 0);

        return false;
    }

    public function getMyChannelPlaylist(Request $request, AdminChannel $channel): JsonResponse
    {
        $playlist = MyChannelPlaylist::where('channel_id', $channel->id)
            ->with('content')
            ->orderBy('order_index', 'asc')
            ->get();

        return response()->json(['playlist' => $playlist]);
    }

    public function addToPlaylist(Request $request, AdminChannel $channel): JsonResponse
    {
        $data = $request->validate([
            'content_id' => 'required|exists:my_channel_content,id',
            'order_index' => 'nullable|integer|min:0',
            'start_offset' => 'nullable|integer|min:0',
            'end_offset' => 'nullable|integer|min:0',
            'custom_duration' => 'nullable|integer|min:0',
            'transition_type' => 'nullable|in:cut,fade,slide,dissolve',
            'transition_duration' => 'nullable|integer|min:0|max:30',
            'is_featured' => 'nullable|boolean',
        ]);

        $content = MyChannelContent::where('channel_id', $channel->id)->findOrFail($data['content_id']);

        $maxOrder = MyChannelPlaylist::where('channel_id', $channel->id)->max('order_index') ?? 0;

        $playlistItem = MyChannelPlaylist::create([
            'channel_id' => $channel->id,
            'content_id' => $data['content_id'],
            'order_index' => $data['order_index'] ?? ($maxOrder + 1),
            'start_offset' => $data['start_offset'] ?? 0,
            'end_offset' => $data['end_offset'] ?? 0,
            'custom_duration' => $data['custom_duration'] ?? 0,
            'transition_type' => $data['transition_type'] ?? 'cut',
            'transition_duration' => $data['transition_duration'] ?? 2,
            'is_featured' => $data['is_featured'] ?? false,
        ]);

        return response()->json(['playlist_item' => $playlistItem->load('content')]);
    }

    public function updateMyChannelPlaylistItem(Request $request, AdminChannel $channel, MyChannelPlaylist $playlistItem): JsonResponse
    {
        $data = $request->validate([
            'order_index' => 'sometimes|integer|min:0',
            'start_offset' => 'sometimes|integer|min:0',
            'end_offset' => 'sometimes|integer|min:0',
            'custom_duration' => 'sometimes|integer|min:0',
            'transition_type' => 'sometimes|in:cut,fade,slide,dissolve',
            'transition_duration' => 'sometimes|integer|min:0|max:30',
            'is_active' => 'sometimes|boolean',
            'is_featured' => 'sometimes|boolean',
        ]);

        $playlistItem->update($data);

        return response()->json(['playlist_item' => $playlistItem->fresh()->load('content')]);
    }

    public function removeMyChannelPlaylistItem(Request $request, AdminChannel $channel, MyChannelPlaylist $playlistItem): JsonResponse
    {
        $playlistItem->delete();

        return response()->json(['message' => 'Item removed from playlist']);
    }

    public function reorderMyChannelPlaylist(Request $request, AdminChannel $channel): JsonResponse
    {
        $data = $request->validate([
            'items' => 'required|array',
            'items.*.id' => 'required|exists:my_channel_playlist,id',
            'items.*.order_index' => 'required|integer|min:0',
        ]);

        foreach ($data['items'] as $item) {
            MyChannelPlaylist::where('id', $item['id'])
                ->where('channel_id', $channel->id)
                ->update(['order_index' => $item['order_index']]);
        }

        return response()->json(['message' => 'Playlist reordered']);
    }

    public function getMyChannelSettings(AdminChannel $channel): JsonResponse
    {
        $settings = MyChannelSetting::firstOrNew(['channel_id' => $channel->id]);

        return response()->json(['settings' => $settings]);
    }

    public function updateMyChannelSettings(Request $request, AdminChannel $channel): JsonResponse
    {
        $data = $request->validate([
            'broadcast_mode' => 'sometimes|in:24_7,scheduled,time_limited',
            'broadcast_timezone' => 'sometimes|string|max:50',
            'default_transition' => 'sometimes|in:cut,fade,slide,dissolve',
            'transition_duration' => 'sometimes|integer|min:0|max:30',
            'buffer_between_items' => 'sometimes|integer|min:0',
            'fallback_enabled' => 'sometimes|boolean',
            'default_quality' => 'sometimes|in:4k,fhd,hd,sd,low',
            'auto_adjust_quality' => 'sometimes|boolean',
            'notify_low_content' => 'sometimes|boolean',
            'low_content_threshold' => 'sometimes|integer|min:1',
            'notify_broadcast_start' => 'sometimes|boolean',
            'notify_broadcast_end' => 'sometimes|boolean',
            'enable_dvr' => 'sometimes|boolean',
            'enable_timeshift' => 'sometimes|boolean',
            'timeshift_duration' => 'sometimes|integer|min:0',
        ]);

        $settings = MyChannelSetting::updateOrCreate(
            ['channel_id' => $channel->id],
            $data
        );

        return response()->json(['settings' => $settings]);
    }

    public function updateChannelOverlays(Request $request, AdminChannel $channel): JsonResponse
    {
        $data = $request->validate([
            'enable_ticker'          => 'sometimes|boolean',
            'ticker_text'            => 'sometimes|nullable|string',
            'ticker_speed'           => 'sometimes|integer|min:1|max:100',
            'ticker_direction'       => 'sometimes|in:left,right,up,down',
            'ticker_color'           => 'sometimes|nullable|string|max:7',
            'ticker_background'      => 'sometimes|nullable|string|max:9',
            'enable_overlay_logo'    => 'sometimes|boolean',
            'logo_url'               => 'sometimes|nullable|string|max:500',
            'overlay_logo_position'  => 'sometimes|nullable|string|max:20',
            'overlay_logo_x'         => 'sometimes|numeric|min:0|max:100',
            'overlay_logo_y'         => 'sometimes|numeric|min:0|max:100',
            'overlay_logo_size'      => 'sometimes|integer|min:10|max:200',
            'overlay_logo_opacity'   => 'sometimes|numeric|min:0|max:1',
            'enable_overlay_clock'   => 'sometimes|boolean',
            'overlay_clock_position' => 'sometimes|nullable|string|max:20',
            'overlay_clock_x'        => 'sometimes|numeric|min:0|max:100',
            'overlay_clock_y'        => 'sometimes|numeric|min:0|max:100',
            'overlay_clock_format'   => 'sometimes|in:HH:MM:SS,HH:MM,MM/DD/YYYY,YYYY-MM-DD',
            'enable_watermark'       => 'sometimes|boolean',
            'watermark_position'     => 'sometimes|in:top-left,top-right,bottom-left,bottom-right',
            'watermark_opacity'      => 'sometimes|numeric|min:0|max:1',
        ]);

        $channel->update($data);

        // Apply overlay changes to a live channel.
        // Text-only changes (ticker_text, ticker_color, etc.) update instantly
        // via ticker.txt reload — no restart. Image/position/opacity changes
        // trigger a seamless restart from the next segment number.
        if ($this->isChannelLive($channel)) {
            app(MyChannelHlsService::class)->applyOverlayUpdate($channel->fresh(), $data);
        }

        return response()->json(['channel' => $channel->fresh()]);
    }

    public function getContentUploadProgress(Request $request, AdminChannel $channel): JsonResponse
    {
        $content = MyChannelContent::where('channel_id', $channel->id)
            ->where('is_transcoded', false)
            ->get();

        return response()->json(['content' => $content]);
    }

    public function getBroadcastStatus(AdminChannel $channel): JsonResponse
    {
        $broadcast = MyChannelBroadcast::where('channel_id', $channel->id)
            ->whereIn('status', ['starting', 'running'])
            ->latest('start_time')
            ->first();

        $settings = MyChannelSetting::where('channel_id', $channel->id)->first();

        return response()->json([
            'broadcast' => $broadcast,
            'settings' => $settings,
            'stream_url' => $channel->stream_url,
            'channel_name' => $channel->channel_name,
        ]);
    }

    public function startMyChannelBroadcast(Request $request, AdminChannel $channel): JsonResponse
    {
        $settings = MyChannelSetting::firstOrNew(['channel_id' => $channel->id]);

        $sessionId = StrHelper::uuid();

        $broadcast = MyChannelBroadcast::create([
            'channel_id' => $channel->id,
            'session_id' => $sessionId,
            'start_time' => now(),
            'scheduled_end' => now()->addHours(24),
            'status' => 'starting',
            'playlist_snapshot' => $channel->myChannelPlaylist()->with('content')->get()->toJson(),
        ]);

        $hlsService = app(MyChannelHlsService::class);
        $started = $hlsService->start($broadcast);

        if (! $started) {
            return response()->json([
                'broadcast' => $broadcast->fresh(),
                'error' => $broadcast->error_message ?? 'Failed to start HLS stream',
            ], 422);
        }

        return response()->json(['broadcast' => $broadcast->fresh(), 'message' => 'Broadcast started']);
    }

    public function stopMyChannelBroadcast(Request $request, AdminChannel $channel): JsonResponse
    {
        $broadcast = MyChannelBroadcast::where('channel_id', $channel->id)
            ->whereIn('status', ['starting', 'running', 'live'])
            ->latest('start_time')
            ->first();

        app(MyChannelHlsService::class)->stop($channel);

        if ($broadcast) {
            $broadcast->update([
                'end_time' => now(),
                'status' => 'ended',
            ]);

            $channel->update(['broadcast_status' => 'offline']);
        }

        return response()->json(['message' => 'Broadcast stopped']);
    }

    private function isChannelLive(AdminChannel $channel): bool
    {
        return app(MyChannelHlsService::class)->isRunning($channel);
    }
}
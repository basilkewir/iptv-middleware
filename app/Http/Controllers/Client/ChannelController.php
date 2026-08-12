<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use App\Models\UserChannel;
use App\Models\Channel\ChannelPlaylistItem;
use App\Models\Channel\ChannelSchedule;
use App\Models\Channel\ChannelOverlay;
use App\Models\Channel\ChannelBroadcastLog;
use App\Models\Channel\ChannelViewLog;
use App\Models\Channel\ChannelSubscription;
use App\Models\Channel\ChannelComment;
use App\Services\Channel\ChannelCreationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ChannelController extends Controller
{
    public function __construct(
        private readonly ChannelCreationService $channelService,
    ) {}

    public function index(Request $request): \Inertia\Response
    {
        $query = UserChannel::where('is_active', true)
            ->where('is_public', true)
            ->where('approved', true);

        if ($search = $request->input('search')) {
            $query->where('channel_name', 'like', "%{$search}%");
        }

        if ($genre = $request->input('genre')) {
            $query->where('genre', $genre);
        }

        if ($category = $request->input('category')) {
            $query->where('category', $category);
        }

        if ($language = $request->input('language')) {
            $query->where('language', $language);
        }

        if ($isLive = $request->boolean('is_live')) {
            $query->where('is_live', true);
        }

        if ($isFeatured = $request->boolean('is_featured')) {
            $query->where('is_featured', true);
        }

        $channels = $query->withCount(['subscriptions', 'viewLogs'])
            ->orderByDesc('is_featured')
            ->orderByDesc('views')
            ->paginate($request->input('per_page', 15));

        return \Inertia\Inertia::render('Client/Channel/Index', [
            'channels' => $channels,
            'search' => $request->input('search', ''),
        ]);
    }

    public function show(UserChannel $channel): \Inertia\Response
    {
        $channel->load([
            'playlistItems' => fn ($q) => $q->where('is_active', true)->orderBy('order_index'),
            'schedules' => fn ($q) => $q->where('is_active', true),
            'overlays' => fn ($q) => $q->where('is_active', true)->orderBy('z_index'),
            'subscriptions' => fn ($q) => $q->where('is_active', true),
            'comments' => fn ($q) => $q->where('is_approved', true)->latest(),
            'user',
        ]);

        return \Inertia\Inertia::render('Client/Channel/Show', [
            'channel' => $channel,
        ]);
    }

    public function edit(UserChannel $channel): \Inertia\Response
    {


        $channel->load('playlistItems', 'schedules', 'overlays');

        return \Inertia\Inertia::render('Client/Channel/Edit', [
            'channel' => $channel,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'channel_name' => 'required|string|max:255',
            'channel_slug' => 'nullable|string|max:255|unique:user_channels,channel_slug',
            'description' => 'nullable|string',
            'channel_number' => 'nullable|string|max:50',
            'logo_url' => 'nullable|string|max:500',
            'banner_url' => 'nullable|string|max:500',
            'background_color' => 'nullable|string|size:7',
            'accent_color' => 'nullable|string|size:7',
            'text_color' => 'nullable|string|size:7',
            'stream_url' => 'nullable|string|max:500',
            'stream_type' => 'nullable|in:hls,rtmp,mpegts,http',
            'stream_key' => 'nullable|string|max:100',
            'output_resolution' => 'nullable|string|max:20',
            'output_bitrate' => 'nullable|integer',
            'playlist_mode' => 'nullable|in:auto,manual,scheduled',
            'default_duration' => 'nullable|integer',
            'loop_playlist' => 'nullable|boolean',
            'shuffle_mode' => 'nullable|boolean',
            'is_live' => 'nullable|boolean',
            'broadcast_status' => 'nullable|in:offline,scheduled,live,ended',
            'scheduled_start' => 'nullable|timestamp',
            'scheduled_end' => 'nullable|timestamp',
            'timezone' => 'nullable|string|max:50',
            'enable_ticker' => 'nullable|boolean',
            'ticker_text' => 'nullable|string',
            'ticker_speed' => 'nullable|integer',
            'ticker_color' => 'nullable|string|size:7',
            'ticker_background' => 'nullable|string|size:7',
            'enable_overlay_logo' => 'nullable|boolean',
            'overlay_logo_position' => 'nullable|string',
            'overlay_logo_size' => 'nullable|integer',
            'language' => 'nullable|string|max:10',
            'genre' => 'nullable|string|max:100',
            'category' => 'nullable|string|max:100',
            'is_adult' => 'nullable|boolean',
            'is_featured' => 'nullable|boolean',
            'is_active' => 'nullable|boolean',
            'is_public' => 'nullable|boolean',
        ]);

        $channel = $this->channelService->createChannel($validated, Auth::user());

        return response()->json(['data' => $channel], 201);
    }

    public function update(Request $request, UserChannel $channel): JsonResponse
    {
        $validated = $request->validate([
            'channel_name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'channel_number' => 'nullable|string|max:50',
            'logo_url' => 'nullable|string|max:500',
            'banner_url' => 'nullable|string|max:500',
            'background_color' => 'nullable|string|size:7',
            'accent_color' => 'nullable|string|size:7',
            'text_color' => 'nullable|string|size:7',
            'stream_url' => 'nullable|string|max:500',
            'stream_type' => 'nullable|in:hls,rtmp,mpegts,http',
            'stream_key' => 'nullable|string|max:100',
            'output_resolution' => 'nullable|string|max:20',
            'output_bitrate' => 'nullable|integer',
            'playlist_mode' => 'nullable|in:auto,manual,scheduled',
            'default_duration' => 'nullable|integer',
            'loop_playlist' => 'nullable|boolean',
            'shuffle_mode' => 'nullable|boolean',
            'is_live' => 'nullable|boolean',
            'broadcast_status' => 'nullable|in:offline,scheduled,live,ended',
            'scheduled_start' => 'nullable|timestamp',
            'scheduled_end' => 'nullable|timestamp',
            'timezone' => 'nullable|string|max:50',
            'enable_ticker' => 'nullable|boolean',
            'ticker_text' => 'nullable|string',
            'ticker_speed' => 'nullable|integer',
            'ticker_color' => 'nullable|string|size:7',
            'ticker_background' => 'nullable|string|size:7',
            'enable_overlay_logo' => 'nullable|boolean',
            'overlay_logo_position' => 'nullable|string',
            'overlay_logo_size' => 'nullable|integer',
            'language' => 'nullable|string|max:10',
            'genre' => 'nullable|string|max:100',
            'category' => 'nullable|string|max:100',
            'is_adult' => 'nullable|boolean',
            'is_featured' => 'nullable|boolean',
            'is_active' => 'nullable|boolean',
            'is_public' => 'nullable|boolean',
        ]);

        $channel = $this->channelService->updateChannel($channel, $validated);

        return response()->json(['data' => $channel]);
    }

    public function destroy(UserChannel $channel): JsonResponse
    {


        $channel->delete();

        return response()->json(['message' => 'Channel deleted successfully']);
    }

    public function toggleStatus(UserChannel $channel): JsonResponse
    {


        $channel->update(['is_active' => !$channel->is_active]);

        return response()->json(['data' => $channel]);
    }

    public function playlistItems(UserChannel $channel): JsonResponse
    {
        $items = $channel->playlistItems()
            ->where('is_active', true)
            ->orderBy('order_index')
            ->paginate($request->input('per_page', 20));

        return response()->json(['data' => $items]);
    }

    public function addPlaylistItem(Request $request, UserChannel $channel): JsonResponse
    {
$validated = $request->validate([
            'content_type' => 'required|in:vod,series,episode,live,url,file',
            'content_id' => 'nullable|integer',
            'content_title' => 'required|string|max:255',
            'content_description' => 'nullable|string',
            'media_url' => 'required|string|max:500',
            'thumbnail_url' => 'nullable|string|max:500',
            'media_duration' => 'nullable|integer',
            'file_size' => 'nullable|integer',
            'order_index' => 'nullable|integer',
            'start_time_offset' => 'nullable|integer',
            'end_time_offset' => 'nullable|integer',
            'transition_duration' => 'nullable|integer',
            'transition_type' => 'nullable|in:cut,fade,slide,none',
            'day_of_week' => 'nullable|array',
            'override_duration' => 'nullable|integer',
            'override_quality' => 'nullable|string|max:20',
            'override_volume' => 'nullable|integer',
        ]);

        $item = $this->channelService->addPlaylistItem($channel, $validated);

        return response()->json(['data' => $item], 201);
    }

    public function updatePlaylistItem(Request $request, UserChannel $channel, ChannelPlaylistItem $item): JsonResponse
    {
$validated = $request->validate([
            'content_type' => 'sometimes|required|in:vod,series,episode,live,url,file',
            'content_id' => 'nullable|integer',
            'content_title' => 'sometimes|required|string|max:255',
            'content_description' => 'nullable|string',
            'media_url' => 'sometimes|required|string|max:500',
            'thumbnail_url' => 'nullable|string|max:500',
            'media_duration' => 'nullable|integer',
            'file_size' => 'nullable|integer',
            'order_index' => 'nullable|integer',
            'start_time_offset' => 'nullable|integer',
            'end_time_offset' => 'nullable|integer',
            'transition_duration' => 'nullable|integer',
            'transition_type' => 'nullable|in:cut,fade,slide,none',
            'day_of_week' => 'nullable|array',
            'override_duration' => 'nullable|integer',
            'override_quality' => 'nullable|string|max:20',
            'override_volume' => 'nullable|integer',
            'is_active' => 'nullable|boolean',
        ]);

        $item = $this->channelService->updatePlaylistItem($item, $validated);

        return response()->json(['data' => $item]);
    }

    public function removePlaylistItem(UserChannel $channel, ChannelPlaylistItem $item): JsonResponse
    {


        $this->channelService->removePlaylistItem($item);

        return response()->json(['message' => 'Playlist item removed']);
    }

    public function reorderPlaylistItems(Request $request, UserChannel $channel): JsonResponse
    {
$validated = $request->validate([
            'item_ids' => 'required|array',
            'item_ids.*' => 'integer',
        ]);

        $this->channelService->reorderPlaylistItems($channel, $validated['item_ids']);

        return response()->json(['message' => 'Playlist reordered']);
    }

    public function schedules(UserChannel $channel): JsonResponse
    {
        $schedules = $channel->schedules()->where('is_active', true)->get();

        return response()->json(['data' => $schedules]);
    }

    public function addSchedule(Request $request, UserChannel $channel): JsonResponse
    {
$validated = $request->validate([
            'day_of_week' => 'required|integer|between:0,6',
            'start_time' => 'required|string',
            'end_time' => 'required|string',
            'playlist_id' => 'nullable|integer',
            'content_type' => 'nullable|in:playlist,single,block',
            'loop_mode' => 'nullable|in:loop,once,stop',
            'priority' => 'nullable|integer',
        ]);

        $schedule = $this->channelService->addSchedule($channel, $validated);

        return response()->json(['data' => $schedule], 201);
    }

    public function updateSchedule(Request $request, UserChannel $channel, ChannelSchedule $schedule): JsonResponse
    {
$validated = $request->validate([
            'day_of_week' => 'sometimes|required|integer|between:0,6',
            'start_time' => 'sometimes|required|string',
            'end_time' => 'sometimes|required|string',
            'playlist_id' => 'nullable|integer',
            'content_type' => 'nullable|in:playlist,single,block',
            'loop_mode' => 'nullable|in:loop,once,stop',
            'priority' => 'nullable|integer',
            'is_active' => 'nullable|boolean',
        ]);

        $schedule = $this->channelService->updateSchedule($schedule, $validated);

        return response()->json(['data' => $schedule]);
    }

    public function removeSchedule(UserChannel $channel, ChannelSchedule $schedule): JsonResponse
    {


        $this->channelService->removeSchedule($schedule);

        return response()->json(['message' => 'Schedule removed']);
    }

    public function overlays(UserChannel $channel): JsonResponse
    {
        $overlays = $channel->overlays()->where('is_active', true)->orderBy('z_index')->get();

        return response()->json(['data' => $overlays]);
    }

    public function addOverlay(Request $request, UserChannel $channel): JsonResponse
    {
$validated = $request->validate([
            'overlay_type' => 'required|in:ticker,logo,watermark,clock,timer,custom',
            'overlay_name' => 'nullable|string|max:100',
            'ticker_text' => 'nullable|string',
            'ticker_speed' => 'nullable|integer',
            'ticker_direction' => 'nullable|in:left,right,up,down',
            'ticker_font_size' => 'nullable|integer',
            'ticker_font_color' => 'nullable|string|size:7',
            'ticker_background_color' => 'nullable|string|size:7',
            'ticker_opacity' => 'nullable|numeric',
            'logo_url' => 'nullable|string|max:500',
            'logo_position' => 'nullable|string',
            'logo_size' => 'nullable|integer',
            'logo_opacity' => 'nullable|numeric',
            'logo_margin_x' => 'nullable|integer',
            'logo_margin_y' => 'nullable|integer',
            'clock_format' => 'nullable|string|max:20',
            'clock_timezone' => 'nullable|string|max:50',
            'clock_font_size' => 'nullable|integer',
            'clock_font_color' => 'nullable|string|size:7',
            'clock_background_color' => 'nullable|string|size:7',
            'clock_position' => 'nullable|string|max:20',
            'display_duration' => 'nullable|integer',
            'start_delay' => 'nullable|integer',
            'end_advance' => 'nullable|integer',
            'z_index' => 'nullable|integer',
            'is_active' => 'nullable|boolean',
            'is_default' => 'nullable|boolean',
        ]);

        $overlay = $this->channelService->addOverlay($channel, $validated);

        return response()->json(['data' => $overlay], 201);
    }

    public function updateOverlay(Request $request, UserChannel $channel, ChannelOverlay $overlay): JsonResponse
    {
$validated = $request->validate([
            'overlay_type' => 'sometimes|required|in:ticker,logo,watermark,clock,timer,custom',
            'overlay_name' => 'nullable|string|max:100',
            'ticker_text' => 'nullable|string',
            'ticker_speed' => 'nullable|integer',
            'ticker_direction' => 'nullable|in:left,right,up,down',
            'ticker_font_size' => 'nullable|integer',
            'ticker_font_color' => 'nullable|string|size:7',
            'ticker_background_color' => 'nullable|string|size:7',
            'ticker_opacity' => 'nullable|numeric',
            'logo_url' => 'nullable|string|max:500',
            'logo_position' => 'nullable|string',
            'logo_size' => 'nullable|integer',
            'logo_opacity' => 'nullable|numeric',
            'logo_margin_x' => 'nullable|integer',
            'logo_margin_y' => 'nullable|integer',
            'clock_format' => 'nullable|string|max:20',
            'clock_timezone' => 'nullable|string|max:50',
            'clock_font_size' => 'nullable|integer',
            'clock_font_color' => 'nullable|string|size:7',
            'clock_background_color' => 'nullable|string|size:7',
            'clock_position' => 'nullable|string|max:20',
            'display_duration' => 'nullable|integer',
            'start_delay' => 'nullable|integer',
            'end_advance' => 'nullable|integer',
            'z_index' => 'nullable|integer',
            'is_active' => 'nullable|boolean',
            'is_default' => 'nullable|boolean',
        ]);

        $overlay = $this->channelService->updateOverlay($overlay, $validated);

        return response()->json(['data' => $overlay]);
    }

    public function removeOverlay(UserChannel $channel, ChannelOverlay $overlay): JsonResponse
    {


        $this->channelService->removeOverlay($overlay);

        return response()->json(['message' => 'Overlay removed']);
    }

    public function startBroadcast(Request $request, UserChannel $channel): JsonResponse
    {
$validated = $request->validate([
            'broadcast_id' => 'nullable|string|max:100',
            'content_type' => 'nullable|in:playlist,single,schedule',
            'content_id' => 'nullable|integer',
        ]);

        $log = $this->channelService->startBroadcast($channel, $validated);

        return response()->json(['data' => $log], 201);
    }

    public function endBroadcast(Request $request, UserChannel $channel, ChannelBroadcastLog $log): JsonResponse
    {
$validated = $request->validate([
            'duration' => 'nullable|integer',
            'viewers' => 'nullable|integer',
            'peak_viewers' => 'nullable|integer',
            'bandwidth_used' => 'nullable|integer',
            'status' => 'nullable|in:started,running,ended,error',
            'error_message' => 'nullable|string',
        ]);

        $log = $this->channelService->endBroadcast($log, $validated);

        return response()->json(['data' => $log]);
    }

    public function subscribe(Request $request, UserChannel $channel): JsonResponse
    {
        $validated = $request->validate([
            'subscription_type' => 'nullable|in:free,premium,trial',
            'end_date' => 'nullable|timestamp',
            'auto_renew' => 'nullable|boolean',
            'notify_new_content' => 'nullable|boolean',
            'notify_schedule' => 'nullable|boolean',
        ]);

        $subscription = $this->channelService->subscribeUser($channel, Auth::user(), $validated);

        return response()->json(['data' => $subscription], 201);
    }

    public function unsubscribe(UserChannel $channel): JsonResponse
    {
        $this->channelService->unsubscribeUser($channel, Auth::user());

        return response()->json(['message' => 'Unsubscribed successfully']);
    }

    public function subscriptions(UserChannel $channel): JsonResponse
    {
        $subscriptions = $channel->subscriptions()
            ->where('is_active', true)
            ->with('user')
            ->paginate($request->input('per_page', 15));

        return response()->json(['data' => $subscriptions]);
    }

    public function comments(UserChannel $channel): JsonResponse
    {
        $comments = $channel->comments()
            ->with('user')
            ->where('is_approved', true)
            ->latest()
            ->paginate($request->input('per_page', 20));

        return response()->json(['data' => $comments]);
    }

    public function addComment(Request $request, UserChannel $channel): JsonResponse
    {
        $validated = $request->validate([
            'comment' => 'required|string',
            'parent_id' => 'nullable|integer',
        ]);

        $comment = $this->channelService->addComment($channel, Auth::user(), $validated);

        return response()->json(['data' => $comment], 201);
    }

    public function approveComment(UserChannel $channel, ChannelComment $comment): JsonResponse
    {


        $comment = $this->channelService->approveComment($comment);

        return response()->json(['data' => $comment]);
    }

    public function deleteComment(UserChannel $channel, ChannelComment $comment): JsonResponse
    {


        $this->channelService->deleteComment($comment);

        return response()->json(['message' => 'Comment deleted']);
    }

    public function stats(UserChannel $channel): JsonResponse
    {


        $stats = $this->channelService->getChannelStats($channel);

        return response()->json(['data' => $stats]);
    }

    public function recordView(Request $request, UserChannel $channel): JsonResponse
    {
        $validated = $request->validate([
            'quality' => 'nullable|string|max:20',
            'bitrate' => 'nullable|integer',
            'resolution' => 'nullable|string|max:20',
            'progress' => 'nullable|integer',
        ]);

        ChannelViewLog::create([
            'channel_id' => $channel->id,
            'user_id' => Auth::id(),
            'session_id' => $request->session()->getId(),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'start_time' => now(),
            'quality' => $validated['quality'] ?? null,
            'bitrate' => $validated['bitrate'] ?? null,
            'resolution' => $validated['resolution'] ?? null,
            'progress' => $validated['progress'] ?? null,
        ]);

        $channel->increment('views');

        return response()->json(['message' => 'View recorded']);
    }

    public function myChannels(Request $request): \Inertia\Response
    {
        $query = Auth::user()->userChannels();

        if ($status = $request->input('status')) {
            $query->where('is_active', $status);
        }

        $channels = $query->withCount(['subscriptions', 'viewLogs'])
            ->latest()
            ->paginate($request->input('per_page', 15));

        return \Inertia\Inertia::render('Client/Channel/Index', [
            'channels' => $channels,
            'search' => '',
        ]);
    }
}
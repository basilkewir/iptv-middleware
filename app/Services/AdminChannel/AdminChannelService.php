<?php

namespace App\Services\AdminChannel;

use App\Models\AdminChannel\AdminChannel;
use App\Models\AdminChannel\AdminChannelPlaylistItem;
use App\Models\AdminChannel\AdminChannelSchedule;
use App\Models\AdminChannel\AdminChannelOverlay;
use App\Models\AdminChannel\AdminChannelBroadcastLog;
use App\Models\AdminChannel\AdminChannelAnalytics;
use App\Models\AdminChannel\AdminChannelViewLog;
use App\Models\AdminChannel\AdminChannelSubscription;
use App\Models\AdminChannel\MyChannelSetting;
use App\Models\SubscriptionPackage;
use App\Models\Bouquet;
use App\Models\User;
use App\Models\ContentCategory;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;

class AdminChannelService
{
    protected $cacheTTL = 3600;

    public function getAllChannels(array $filters = [])
    {
        $query = AdminChannel::with(['packages', 'subscriptions']);

        if (isset($filters['is_active'])) {
            $query->where('is_active', $filters['is_active']);
        }

        if (isset($filters['channel_type'])) {
            $query->where('channel_type', $filters['channel_type']);
        }

        if (isset($filters['is_my_channel'])) {
            $query->where('is_my_channel', $filters['is_my_channel']);
        }

        if (isset($filters['is_featured'])) {
            $query->where('is_featured', $filters['is_featured']);
        }

        if (isset($filters['is_public'])) {
            $query->where('is_public', $filters['is_public']);
        }

        if (isset($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('channel_name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhere('channel_number', 'like', "%{$search}%")
                    ->orWhere('genre', 'like', "%{$search}%")
                    ->orWhere('country', 'like', "%{$search}%")
                    ->orWhere('language', 'like', "%{$search}%");
            });
        }

        if (isset($filters['category'])) {
            $query->where('category', $filters['category']);
        }

        if (isset($filters['country'])) {
            $query->where('country', $filters['country']);
        }

        if (isset($filters['language'])) {
            $query->where('language', $filters['language']);
        }

        if (isset($filters['broadcast_status'])) {
            $query->where('broadcast_status', $filters['broadcast_status']);
        }

        if (isset($filters['playout_mode'])) {
            $query->where('playout_mode', $filters['playout_mode']);
        }

        $allowedSortColumns = ['created_at', 'channel_name', 'channel_number', 'broadcast_status', 'featured_order'];
        $sortBy = in_array($filters['sort_by'] ?? '', $allowedSortColumns) ? $filters['sort_by'] : 'created_at';
        $sortDirection = ($filters['sort_direction'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

        $query->orderBy($sortBy, $sortDirection);

        $perPage = $filters['per_page'] ?? 20;

        return $query->paginate($perPage);
    }

    public function getChannelById(int $id, bool $withRelations = true)
    {
        $query = AdminChannel::query();

        if ($withRelations) {
            $query->with(['packages', 'subscriptions.user', 'playlistItems', 'schedules', 'overlays']);
        }

        return $query->findOrFail($id);
    }

    public function createChannel(array $data, User $adminUser): AdminChannel
    {
        try {
            DB::beginTransaction();

            $channelSlug = $data['channel_slug'] ?? Str::slug($data['channel_name']);

            $channel = AdminChannel::create([
                'channel_name' => $data['channel_name'],
                'channel_slug' => $channelSlug,
                'channel_number' => $data['channel_number'] ?? null,
                'channel_type' => $data['channel_type'] ?? 'admin',
                'is_my_channel' => $data['is_my_channel'] ?? false,
                'playlist_type' => $data['playlist_type'] ?? 'continuous',
                'description' => $data['description'] ?? null,
                'logo_url' => $data['logo_url'] ?? null,
                'banner_url' => $data['banner_url'] ?? null,
                'background_color' => $data['background_color'] ?? null,
                'accent_color' => $data['accent_color'] ?? null,
                'text_color' => $data['text_color'] ?? null,
                'watermark_url' => $data['watermark_url'] ?? null,
                'stream_url' => $data['stream_url'] ?? null,
                'stream_type' => $data['stream_type'] ?? 'hls',
                'stream_key' => $data['stream_key'] ?? null,
                'output_resolution' => $data['output_resolution'] ?? null,
                'output_bitrate' => $data['output_bitrate'] ?? null,
                'output_frame_rate' => $data['output_frame_rate'] ?? null,
                'video_codec' => $data['video_codec'] ?? null,
                'audio_codec' => $data['audio_codec'] ?? null,
                'broadcast_status' => $data['broadcast_status'] ?? 'offline',
                'broadcast_mode' => $data['broadcast_mode'] ?? 'manual',
                'scheduled_start' => $data['scheduled_start'] ?? null,
                'scheduled_end' => $data['scheduled_end'] ?? null,
                'last_broadcast' => $data['last_broadcast'] ?? null,
                'timezone' => $data['timezone'] ?? 'UTC',
                'duration_type' => $data['duration_type'] ?? 'continuous',
                'playout_mode' => $data['playout_mode'] ?? 'playlist',
                'default_duration' => $data['default_duration'] ?? 0,
                'loop_playlist' => $data['loop_playlist'] ?? true,
                'shuffle_mode' => $data['shuffle_mode'] ?? false,
                'transition_type' => $data['transition_type'] ?? 'cut',
                'transition_duration' => $data['transition_duration'] ?? 2,
                'transcoding_device' => $data['transcoding_device'] ?? null,
                'enable_ticker' => $data['enable_ticker'] ?? false,
                'ticker_text' => $data['ticker_text'] ?? null,
                'ticker_speed' => $data['ticker_speed'] ?? 30,
                'ticker_color' => $data['ticker_color'] ?? null,
                'ticker_background' => $data['ticker_background'] ?? null,
                'ticker_direction' => $data['ticker_direction'] ?? 'left',
                'enable_overlay_logo' => $data['enable_overlay_logo'] ?? false,
                'overlay_logo_position' => $data['overlay_logo_position'] ?? 'top-left',
                'overlay_logo_x' => $data['overlay_logo_x'] ?? 2.00,
                'overlay_logo_y' => $data['overlay_logo_y'] ?? 2.00,
                'overlay_logo_size' => $data['overlay_logo_size'] ?? 100,
                'overlay_logo_opacity' => $data['overlay_logo_opacity'] ?? 1.00,
                'enable_overlay_clock' => $data['enable_overlay_clock'] ?? false,
                'overlay_clock_position' => $data['overlay_clock_position'] ?? 'top-right',
                'overlay_clock_x' => $data['overlay_clock_x'] ?? 2.00,
                'overlay_clock_y' => $data['overlay_clock_y'] ?? 2.00,
                'overlay_clock_format' => $data['overlay_clock_format'] ?? 'HH:MM:SS',
                'enable_watermark' => $data['enable_watermark'] ?? false,
                'watermark_position' => $data['watermark_position'] ?? 'bottom-right',
                'watermark_opacity' => $data['watermark_opacity'] ?? 0.50,
                'content_owner' => $data['content_owner'] ?? null,
                'license_type' => $data['license_type'] ?? 'free',
                'license_expiry' => $data['license_expiry'] ?? null,
                'is_public' => $data['is_public'] ?? true,
                'is_featured' => $data['is_featured'] ?? false,
                'is_adult' => $data['is_adult'] ?? false,
                'featured_order' => $data['featured_order'] ?? 0,
                'require_subscription' => $data['require_subscription'] ?? false,
                'genre' => $data['genre'] ?? null,
                'category' => $data['category'] ?? null,
                'language' => $data['language'] ?? 'en',
                'country' => $data['country'] ?? null,
                'tags' => $data['tags'] ?? null,
                'content_restrictions' => $data['content_restrictions'] ?? null,
                'region_restrictions' => $data['region_restrictions'] ?? null,
                'created_by' => $adminUser->id,
                'approved_by' => $data['approved_by'] ?? null,
                'approved_at' => $data['approved_at'] ?? null,
            ]);

            if (isset($data['package_ids'])) {
                $channel->packages()->sync($data['package_ids']);
            }

            if (isset($data['bouquet_ids'])) {
                $channel->bouquets()->sync($data['bouquet_ids']);
            }

            DB::commit();

            return $channel->load(['packages', 'bouquets', 'createdBy', 'approvedBy']);
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function updateChannel(AdminChannel $channel, array $data): AdminChannel
    {
        try {
            DB::beginTransaction();

            $channel->update($data);

            if (isset($data['package_ids'])) {
                $channel->packages()->sync($data['package_ids']);
            }

            if (isset($data['bouquet_ids'])) {
                $channel->bouquets()->sync($data['bouquet_ids']);
            }

            DB::commit();

            return $channel->fresh(['packages', 'bouquets']);
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function deleteChannel(AdminChannel $channel): bool
    {
        try {
            DB::beginTransaction();

            $channel->subscriptions()->delete();
            $channel->playlistItems()->delete();
            $channel->schedules()->delete();
            $channel->overlays()->delete();
            $channel->broadcastLogs()->delete();
            $channel->viewLogs()->delete();
            $channel->analytics()->delete();

            $channel->delete();

            DB::commit();

            return true;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function toggleStatus(AdminChannel $channel, bool $isActive): AdminChannel
    {
        $channel->update(['is_active' => $isActive]);
        return $channel->fresh();
    }

    public function toggleFeatured(AdminChannel $channel, bool $isFeatured): AdminChannel
    {
        $channel->update(['is_featured' => $isFeatured]);
        return $channel->fresh();
    }

    public function approveChannel(AdminChannel $channel, User $approver): AdminChannel
    {
        $channel->update([
            'is_approved' => true,
            'approved_at' => now(),
            'approved_by' => $approver->id,
        ]);
        return $channel->fresh(['approvedBy']);
    }

    public function addPlaylistItem(AdminChannel $channel, array $data): AdminChannelPlaylistItem
    {
        $item = AdminChannelPlaylistItem::create([
            'admin_channel_id' => $channel->id,
            'content_type' => $data['content_type'] ?? 'stream',
            'content_id' => $data['content_id'] ?? null,
            'content_title' => $data['content_title'] ?? null,
            'content_description' => $data['content_description'] ?? null,
            'media_url' => $data['media_url'] ?? null,
            'thumbnail_url' => $data['thumbnail_url'] ?? null,
            'media_duration' => $data['media_duration'] ?? 0,
            'file_size' => $data['file_size'] ?? 0,
            'order_index' => $data['order_index'] ?? 0,
            'start_time_offset' => $data['start_time_offset'] ?? 0,
            'end_time_offset' => $data['end_time_offset'] ?? 0,
            'transition_duration' => $data['transition_duration'] ?? 0,
            'transition_type' => $data['transition_type'] ?? 'cut',
            'scheduled_start' => $data['scheduled_start'] ?? null,
            'scheduled_end' => $data['scheduled_end'] ?? null,
            'is_enabled' => $data['is_enabled'] ?? true,
            'metadata' => $data['metadata'] ?? null,
        ]);

        return $item->load('adminChannel');
    }

    public function updatePlaylistItem(AdminChannelPlaylistItem $item, array $data): AdminChannelPlaylistItem
    {
        $item->update($data);
        return $item->fresh();
    }

    public function deletePlaylistItem(AdminChannelPlaylistItem $item): bool
    {
        return $item->delete();
    }

    public function reorderPlaylistItems(AdminChannel $channel, array $itemIds): bool
    {
        foreach ($itemIds as $index => $id) {
            AdminChannelPlaylistItem::where('admin_channel_id', $channel->id)
                ->where('id', $id)
                ->update(['order_index' => $index]);
        }

        return true;
    }

    public function addSchedule(AdminChannel $channel, array $data): AdminChannelSchedule
    {
        $schedule = AdminChannelSchedule::create([
            'admin_channel_id' => $channel->id,
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'schedule_type' => $data['schedule_type'] ?? 'once',
            'schedule_days' => $data['schedule_days'] ?? null,
            'start_time' => $data['start_time'],
            'end_time' => $data['end_time'] ?? null,
            'status' => $data['status'] ?? 'scheduled',
            'playlist_ids' => $data['playlist_ids'] ?? null,
            'overlay_ids' => $data['overlay_ids'] ?? null,
            'metadata' => $data['metadata'] ?? null,
        ]);

        return $schedule->load('adminChannel');
    }

    public function updateSchedule(AdminChannelSchedule $schedule, array $data): AdminChannelSchedule
    {
        $schedule->update($data);
        return $schedule->fresh();
    }

    public function deleteSchedule(AdminChannelSchedule $schedule): bool
    {
        return $schedule->delete();
    }

    public function addOverlay(AdminChannel $channel, array $data): AdminChannelOverlay
    {
        $overlay = AdminChannelOverlay::create([
            'admin_channel_id' => $channel->id,
            'overlay_name' => $data['overlay_name'],
            'overlay_type' => $data['overlay_type'] ?? 'logo',
            'overlay_url' => $data['overlay_url'] ?? null,
            'overlay_text' => $data['overlay_text'] ?? null,
            'position' => $data['position'] ?? 'top-left',
            'size' => $data['size'] ?? 100,
            'opacity' => $data['opacity'] ?? 1.00,
            'color' => $data['color'] ?? null,
            'background_color' => $data['background_color'] ?? null,
            'z_index' => $data['z_index'] ?? 1,
            'is_enabled' => $data['is_enabled'] ?? true,
            'start_time' => $data['start_time'] ?? null,
            'end_time' => $data['end_time'] ?? null,
            'animation' => $data['animation'] ?? null,
        ]);

        return $overlay->load('adminChannel');
    }

    public function updateOverlay(AdminChannelOverlay $overlay, array $data): AdminChannelOverlay
    {
        $overlay->update($data);
        return $overlay->fresh();
    }

    public function deleteOverlay(AdminChannelOverlay $overlay): bool
    {
        return $overlay->delete();
    }

    public function startBroadcast(AdminChannel $channel, array $data = []): AdminChannelBroadcastLog
    {
        $log = AdminChannelBroadcastLog::create([
            'admin_channel_id' => $channel->id,
            'event_type' => $data['event_type'] ?? 'broadcast_start',
            'title' => $data['title'] ?? null,
            'description' => $data['description'] ?? null,
            'stream_url' => $data['stream_url'] ?? $channel->stream_url,
            'stream_type' => $data['stream_type'] ?? $channel->stream_type,
            'quality' => $data['quality'] ?? $channel->quality,
            'viewers' => $data['viewers'] ?? 0,
            'duration_seconds' => $data['duration_seconds'] ?? 0,
            'metadata' => $data['metadata'] ?? null,
            'broadcast_start' => $data['broadcast_start'] ?? now(),
            'broadcast_end' => $data['broadcast_end'] ?? null,
        ]);

        $channel->update(['broadcast_status' => 'live', 'last_broadcast' => now()]);

        return $log->load('adminChannel');
    }

    public function endBroadcast(AdminChannelBroadcastLog $log, array $data = []): AdminChannelBroadcastLog
    {
        $log->update([
            'event_type' => $data['event_type'] ?? 'broadcast_end',
            'title' => $data['title'] ?? $log->title,
            'description' => $data['description'] ?? $log->description,
            'stream_url' => $data['stream_url'] ?? $log->stream_url,
            'stream_type' => $data['stream_type'] ?? $log->stream_type,
            'quality' => $data['quality'] ?? $log->quality,
            'viewers' => $data['viewers'] ?? $log->viewers,
            'duration_seconds' => $data['duration_seconds'] ?? $log->duration_seconds,
            'metadata' => $data['metadata'] ?? $log->metadata,
            'broadcast_end' => $data['broadcast_end'] ?? now(),
        ]);

        $log->adminChannel->update(['broadcast_status' => 'ended']);

        return $log->fresh(['adminChannel']);
    }

    public function subscribeUserToChannel(AdminChannel $channel, User $user, array $data = []): AdminChannelSubscription
    {
        return AdminChannelSubscription::updateOrCreate(
            ['admin_channel_id' => $channel->id, 'user_id' => $user->id],
            [
                'subscription_package_id' => $data['subscription_package_id'] ?? null,
                'status' => $data['status'] ?? 'active',
                'subscribed_at' => $data['subscribed_at'] ?? now(),
                'expires_at' => $data['expires_at'] ?? null,
                'cancelled_at' => $data['cancelled_at'] ?? null,
                'metadata' => $data['metadata'] ?? null,
            ]
        );
    }

    public function unsubscribeUserFromChannel(AdminChannel $channel, User $user, array $data = []): bool
    {
        $subscription = AdminChannelSubscription::where('admin_channel_id', $channel->id)
            ->where('user_id', $user->id)
            ->first();

        if (!$subscription) return false;

        $subscription->update([
            'status' => $data['status'] ?? 'cancelled',
            'cancelled_at' => $data['cancelled_at'] ?? now(),
            'metadata' => $data['metadata'] ?? $subscription->metadata,
        ]);

        return true;
    }

    public function getChannelStats(AdminChannel $channel, array $dateRange = [])
    {
        $query = AdminChannelAnalytics::query()->where('admin_channel_id', $channel->id);

        if (!empty($dateRange)) {
            $query->where('date', '>=', $dateRange['start_date'] ?? $dateRange['start'] ?? null)
                ->where('date', '<=', $dateRange['end_date'] ?? $dateRange['end'] ?? null);
        }

        $analytics = $query->latest()->first();

        $basicStats = [
            'total_views' => $channel->viewLogs()->count(),
            'total_watch_time' => $channel->viewLogs()->sum('watch_duration_seconds'),
            'total_subscribers' => $channel->subscriptions()->where('status', 'active')->count(),
            'total_broadcasts' => $channel->broadcastLogs()->count(),
            'total_overlays' => $channel->overlays()->count(),
            'total_schedules' => $channel->schedules()->count(),
            'total_playlist_items' => $channel->playlistItems()->count(),
            'current_viewers' => $channel->broadcastLogs()->whereIn('event_type', ['broadcast_start', 'broadcast_running'])->sum('viewers'),
            'live_status' => $channel->broadcast_status,
        ];

        if ($analytics) {
            $basicStats['today_views'] = $analytics->views;
            $basicStats['today_watch_time'] = $analytics->total_watch_time_seconds;
            $basicStats['peak_viewers'] = $analytics->peak_concurrent_viewers;
        }

        return $basicStats;
    }

    public function generateAnalyticsForDate(AdminChannel $channel, string $date)
    {
        $existing = AdminChannelAnalytics::where('admin_channel_id', $channel->id)
            ->where('date', $date)
            ->first();

        if ($existing) {
            return $existing;
        }

        $viewLogs = $channel->viewLogs()->whereDate('started_at', $date)->get();

        $analytics = AdminChannelAnalytics::create([
            'admin_channel_id' => $channel->id,
            'date' => $date,
            'views' => $viewLogs->count(),
            'unique_viewers' => $viewLogs->pluck('user_id')->unique()->count(),
            'total_watch_time_seconds' => $viewLogs->sum('watch_duration_seconds'),
            'peak_concurrent_viewers' => $channel->broadcastLogs()
                ->whereDate('created_at', $date)
                ->max('viewers') ?? 0,
            'average_watch_duration_seconds' => $viewLogs->count() > 0 ? ($viewLogs->sum('watch_duration_seconds') / $viewLogs->count()) : 0,
            'new_subscribers' => $channel->subscriptions()->whereDate('created_at', $date)->count(),
            'lost_subscribers' => $channel->subscriptions()->whereDate('cancelled_at', $date)->count(),
            'total_subscribers' => $channel->subscriptions()->count(),
            'buffering_events' => $channel->broadcastLogs()->where('event_type', 'buffering')->whereDate('created_at', $date)->count(),
            'error_events' => $channel->broadcastLogs()->where('event_type', 'broadcast_error')->whereDate('created_at', $date)->count(),
            'average_bitrate' => $channel->broadcastLogs()->whereDate('created_at', $date)->avg('bitrate') ?? 0,
            'geo_data' => $this->getGeoData($viewLogs),
            'device_data' => $this->getDeviceData($viewLogs),
            'quality_distribution' => $this->getQualityDistribution($viewLogs),
        ]);

        return $analytics;
    }

    protected function getGeoData($viewLogs)
    {
        $geoData = ['countries' => [], 'cities' => [], 'regions' => []];

        foreach ($viewLogs as $log) {
            if ($log->country) {
                $geoData['countries'][$log->country] = ($geoData['countries'][$log->country] ?? 0) + 1;
            }
            if ($log->region) {
                $geoData['regions'][$log->region] = ($geoData['regions'][$log->region] ?? 0) + 1;
            }
            if ($log->city) {
                $geoData['cities'][$log->city] = ($geoData['cities'][$log->city] ?? 0) + 1;
            }
        }

        return $geoData;
    }

    protected function getDeviceData($viewLogs)
    {
        $deviceData = ['types' => [], 'platforms' => []];

        foreach ($viewLogs as $log) {
            if ($log->device_type) {
                $deviceData['types'][$log->device_type] = ($deviceData['types'][$log->device_type] ?? 0) + 1;
            }
            if ($log->platform) {
                $deviceData['platforms'][$log->platform] = ($deviceData['platforms'][$log->platform] ?? 0) + 1;
            }
        }

        return $deviceData;
    }

    protected function getQualityDistribution($viewLogs)
    {
        $qualityData = [];

        foreach ($viewLogs as $log) {
            if ($log->quality_watched) {
                $qualityData[$log->quality_watched] = ($qualityData[$log->quality_watched] ?? 0) + 1;
            }
        }

        return $qualityData;
    }

    public function testStreamUrl(string $streamUrl): array
    {
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $streamUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_NOBODY, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);

        curl_close($ch);

        return [
            'success' => $httpCode >= 200 && $httpCode < 400 && !$curlError,
            'http_code' => $httpCode,
            'curl_error' => $curlError,
            'stream_url' => $streamUrl,
        ];
    }

    public function generateEpgForChannel(AdminChannel $channel): array
    {
        $schedules = $channel->schedules()->where('status', 'active')->get();
        $epgData = [];

        foreach ($schedules as $schedule) {
            $epgEntry = [
                'channel_id' => $channel->id,
                'title' => $schedule->title,
                'description' => $schedule->description,
                'start_time' => $schedule->start_time,
                'end_time' => $schedule->end_time,
                'schedule_type' => $schedule->schedule_type,
                'playlist_ids' => $schedule->playlist_ids,
                'overlay_ids' => $schedule->overlay_ids,
            ];

            $epgData[] = $epgEntry;
        }

        return $epgData;
    }

    public function getPlaylistForChannel(AdminChannel $channel): array
    {
        $items = $channel->playlistItems()
            ->where('is_enabled', true)
            ->orderBy('order_index')
            ->get()
            ->map(function ($item) {
                return [
                    'id' => $item->id,
                    'content_type' => $item->content_type,
                    'content_id' => $item->content_id,
                    'content_title' => $item->content_title,
                    'content_description' => $item->content_description,
                    'media_url' => $item->media_url,
                    'thumbnail_url' => $item->thumbnail_url,
                    'media_duration' => $item->media_duration,
                    'file_size' => $item->file_size,
                    'order_index' => $item->order_index,
                    'start_time_offset' => $item->start_time_offset,
                    'end_time_offset' => $item->end_time_offset,
                    'transition_duration' => $item->transition_duration,
                    'transition_type' => $item->transition_type,
                    'scheduled_start' => $item->scheduled_start,
                    'scheduled_end' => $item->scheduled_end,
                    'metadata' => $item->metadata,
                ];
            })
            ->toArray();

        return $items;
    }

    public function getActiveOverlays(AdminChannel $channel): array
    {
        $overlays = $channel->overlays()
            ->where('is_enabled', true)
            ->where(function ($q) {
                $q->whereNull('start_time')
                    ->orWhere('start_time', '<=', now())
                    ->orWhere(function ($q2) {
                        $q2->whereNotNull('end_time')
                            ->where('end_time', '>=', now());
                    });
            })
            ->orderBy('z_index')
            ->get()
            ->map(function ($overlay) {
                return [
                    'id' => $overlay->id,
                    'name' => $overlay->overlay_name,
                    'type' => $overlay->overlay_type,
                    'url' => $overlay->overlay_url,
                    'text' => $overlay->overlay_text,
                    'position' => $overlay->position,
                    'size' => $overlay->size,
                    'opacity' => $overlay->opacity,
                    'color' => $overlay->color,
                    'background_color' => $overlay->background_color,
                    'z_index' => $overlay->z_index,
                    'animation' => $overlay->animation,
                    'is_default' => $overlay->is_default,
                ];
            })
            ->toArray();

        return $overlays;
    }

    public function scanQualityForChannel(AdminChannel $channel): array
    {
        $result = ['success' => false, 'message' => ''];

        if (!$channel->stream_url) {
            $result['message'] = 'Channel does not have a stream URL';
            return $result;
        }

        $testResult = $this->testStreamUrl($channel->stream_url);

        if ($testResult['success']) {
            $channel->update(['broadcast_status' => 'ready']);
            $result['success'] = true;
            $result['message'] = 'Stream URL is valid and reachable';
            $result['stream_test'] = $testResult;
        } else {
            $result['message'] = 'Stream URL is not reachable or invalid';
            $result['stream_test'] = $testResult;
        }

        return $result;
    }

    public function scanQualityForAllChannels(): array
    {
        $results = ['success' => true, 'message' => '', 'channels' => [], 'failed' => []];

        $channels = AdminChannel::where('is_active', true)
            ->whereNotNull('stream_url')
            ->get();

        foreach ($channels as $channel) {
            $testResult = $this->testStreamUrl($channel->stream_url);

            if ($testResult['success']) {
                $channel->update(['broadcast_status' => 'ready']);
                $results['channels'][] = [
                    'id' => $channel->id,
                    'name' => $channel->channel_name,
                    'slug' => $channel->channel_slug,
                    'stream_test' => $testResult,
                ];
            } else {
                $results['failed'][] = [
                    'id' => $channel->id,
                    'name' => $channel->channel_name,
                    'slug' => $channel->channel_slug,
                    'reason' => $testResult['curl_error'] ?? 'HTTP ' . $testResult['http_code'],
                ];
            }
        }

        $results['message'] = count($results['channels']) . ' channels passed, ' . count($results['failed']) . ' channels failed';

        return $results;
    }

    public function getChannelAnalyticsSummary(AdminChannel $channel, int $days = 30)
    {
        $startDate = Carbon::now()->subDays($days);
        $endDate = Carbon::now();

        $analytics = AdminChannelAnalytics::where('admin_channel_id', $channel->id)
            ->where('date', '>=', $startDate)
            ->where('date', '<=', $endDate)
            ->get();

        $totalViews = $channel->viewLogs()->where('started_at', '>=', $startDate)->count();
        $totalWatchTime = $channel->viewLogs()->where('started_at', '>=', $startDate)->sum('watch_duration_seconds');
        $uniqueViewers = $channel->viewLogs()->where('started_at', '>=', $startDate)->pluck('user_id')->unique()->count();
        $totalSubscribers = $channel->subscriptions()->where('status', 'active')->count();

        $dailyData = $analytics->groupBy('date')->map(function ($dayData) {
            return [
                'date' => $dayData[0]->date,
                'views' => $dayData->sum('views'),
                'unique_viewers' => $dayData->sum('unique_viewers'),
                'watch_time' => $dayData->sum('total_watch_time_seconds'),
                'new_subscribers' => $dayData->sum('new_subscribers'),
                'lost_subscribers' => $dayData->sum('lost_subscribers'),
            ];
        });

        return [
            'total_views' => $totalViews,
            'total_watch_time' => $totalWatchTime,
            'unique_viewers' => $uniqueViewers,
            'total_subscribers' => $totalSubscribers,
            'daily_data' => $dailyData->toArray(),
            'peak_viewers' => $analytics->max('peak_concurrent_viewers') ?? 0,
            'average_bitrate' => $analytics->avg('average_bitrate') ?? 0,
        ];
    }

    public function upsertMyChannelSettings(AdminChannel $channel, array $data): MyChannelSetting
    {
        $allowed = [
            'broadcast_mode', 'broadcast_timezone',
            'default_transition', 'transition_duration', 'buffer_between_items',
            'fallback_enabled', 'fallback_playlist_id', 'fallback_after_empty',
            'default_quality', 'auto_adjust_quality',
            'notify_low_content', 'low_content_threshold',
            'notify_broadcast_start', 'notify_broadcast_end',
            'enable_dvr', 'enable_timeshift', 'timeshift_duration',
        ];

        $filtered = array_intersect_key($data, array_flip($allowed));

        return MyChannelSetting::updateOrCreate(
            ['channel_id' => $channel->id],
            $filtered
        );
    }
}
<?php

namespace App\Services\Channel;

use App\Models\UserChannel;
use App\Models\Channel\ChannelPlaylistItem;
use App\Models\Channel\ChannelSchedule;
use App\Models\Channel\ChannelOverlay;
use App\Models\Channel\ChannelSubscription;
use Illuminate\Support\Str;

class ChannelCreationService
{
    public function createChannel(array $data, \App\Models\User $user): UserChannel
    {
        $channel = UserChannel::create([
            'user_id' => $user->id,
            'channel_name' => $data['channel_name'],
            'channel_slug' => $data['channel_slug'] ?? Str::slug($data['channel_name']),
            'description' => $data['description'] ?? null,
            'channel_number' => $data['channel_number'] ?? null,
            'logo_url' => $data['logo_url'] ?? null,
            'banner_url' => $data['banner_url'] ?? null,
            'background_color' => $data['background_color'] ?? null,
            'accent_color' => $data['accent_color'] ?? null,
            'text_color' => $data['text_color'] ?? null,
            'stream_url' => $data['stream_url'] ?? null,
            'stream_type' => $data['stream_type'] ?? 'hls',
            'stream_key' => $data['stream_key'] ?? null,
            'output_resolution' => $data['output_resolution'] ?? null,
            'output_bitrate' => $data['output_bitrate'] ?? null,
            'playlist_mode' => $data['playlist_mode'] ?? 'auto',
            'default_duration' => $data['default_duration'] ?? 0,
            'loop_playlist' => $data['loop_playlist'] ?? true,
            'shuffle_mode' => $data['shuffle_mode'] ?? false,
            'is_live' => $data['is_live'] ?? false,
            'broadcast_status' => $data['broadcast_status'] ?? 'offline',
            'scheduled_start' => $data['scheduled_start'] ?? null,
            'scheduled_end' => $data['scheduled_end'] ?? null,
            'timezone' => $data['timezone'] ?? 'UTC',
            'enable_ticker' => $data['enable_ticker'] ?? false,
            'ticker_text' => $data['ticker_text'] ?? null,
            'ticker_speed' => $data['ticker_speed'] ?? 30,
            'ticker_color' => $data['ticker_color'] ?? null,
            'ticker_background' => $data['ticker_background'] ?? null,
            'enable_overlay_logo' => $data['enable_overlay_logo'] ?? false,
            'overlay_logo_position' => $data['overlay_logo_position'] ?? 'top-left',
            'overlay_logo_size' => $data['overlay_logo_size'] ?? 100,
            'language' => $data['language'] ?? 'en',
            'genre' => $data['genre'] ?? null,
            'category' => $data['category'] ?? null,
            'is_adult' => $data['is_adult'] ?? false,
            'is_featured' => $data['is_featured'] ?? false,
            'is_active' => $data['is_active'] ?? true,
            'is_public' => $data['is_public'] ?? true,
            'approved' => $data['approved'] ?? false,
        ]);

        return $channel;
    }

    public function updateChannel(UserChannel $channel, array $data): UserChannel
    {
        $channel->update($data);

        return $channel->fresh();
    }

    public function addPlaylistItem(UserChannel $channel, array $data): ChannelPlaylistItem
    {
        $item = ChannelPlaylistItem::create([
            'channel_id' => $channel->id,
            'content_type' => $data['content_type'],
            'content_id' => $data['content_id'] ?? null,
            'content_title' => $data['content_title'],
            'content_description' => $data['content_description'] ?? null,
            'media_url' => $data['media_url'],
            'thumbnail_url' => $data['thumbnail_url'] ?? null,
            'media_duration' => $data['media_duration'] ?? null,
            'file_size' => $data['file_size'] ?? null,
            'order_index' => $data['order_index'] ?? 0,
            'start_time_offset' => $data['start_time_offset'] ?? 0,
            'end_time_offset' => $data['end_time_offset'] ?? 0,
            'transition_duration' => $data['transition_duration'] ?? 2,
            'transition_type' => $data['transition_type'] ?? 'cut',
            'scheduled_start' => $data['scheduled_start'] ?? null,
            'scheduled_end' => $data['scheduled_end'] ?? null,
            'day_of_week' => $data['day_of_week'] ?? null,
            'override_duration' => $data['override_duration'] ?? 0,
            'override_quality' => $data['override_quality'] ?? null,
            'override_volume' => $data['override_volume'] ?? 100,
            'is_active' => $data['is_active'] ?? true,
            'is_featured' => $data['is_featured'] ?? false,
        ]);

        return $item;
    }

    public function updatePlaylistItem(ChannelPlaylistItem $item, array $data): ChannelPlaylistItem
    {
        $item->update($data);

        return $item->fresh();
    }

    public function removePlaylistItem(ChannelPlaylistItem $item): bool
    {
        return $item->delete();
    }

    public function reorderPlaylistItems(UserChannel $channel, array $itemIds): bool
    {
        foreach ($itemIds as $index => $id) {
            ChannelPlaylistItem::where('channel_id', $channel->id)
                ->where('id', $id)
                ->update(['order_index' => $index]);
        }

        return true;
    }

    public function addSchedule(UserChannel $channel, array $data): ChannelSchedule
    {
        $schedule = ChannelSchedule::create([
            'channel_id' => $channel->id,
            'day_of_week' => $data['day_of_week'],
            'start_time' => $data['start_time'],
            'end_time' => $data['end_time'],
            'playlist_id' => $data['playlist_id'] ?? null,
            'content_type' => $data['content_type'] ?? 'playlist',
            'loop_mode' => $data['loop_mode'] ?? 'loop',
            'priority' => $data['priority'] ?? 0,
            'is_active' => $data['is_active'] ?? true,
        ]);

        return $schedule;
    }

    public function updateSchedule(ChannelSchedule $schedule, array $data): ChannelSchedule
    {
        $schedule->update($data);

        return $schedule->fresh();
    }

    public function removeSchedule(ChannelSchedule $schedule): bool
    {
        return $schedule->delete();
    }

    public function addOverlay(UserChannel $channel, array $data): ChannelOverlay
    {
        $overlay = ChannelOverlay::create([
            'channel_id' => $channel->id,
            'overlay_type' => $data['overlay_type'],
            'overlay_name' => $data['overlay_name'] ?? null,
            'ticker_text' => $data['ticker_text'] ?? null,
            'ticker_speed' => $data['ticker_speed'] ?? 30,
            'ticker_direction' => $data['ticker_direction'] ?? 'left',
            'ticker_font_size' => $data['ticker_font_size'] ?? 24,
            'ticker_font_color' => $data['ticker_font_color'] ?? null,
            'ticker_background_color' => $data['ticker_background_color'] ?? null,
            'ticker_opacity' => $data['ticker_opacity'] ?? 1.00,
            'logo_url' => $data['logo_url'] ?? null,
            'logo_position' => $data['logo_position'] ?? 'top-left',
            'logo_size' => $data['logo_size'] ?? 100,
            'logo_opacity' => $data['logo_opacity'] ?? 1.00,
            'logo_margin_x' => $data['logo_margin_x'] ?? 10,
            'logo_margin_y' => $data['logo_margin_y'] ?? 10,
            'clock_format' => $data['clock_format'] ?? 'HH:MM:SS',
            'clock_timezone' => $data['clock_timezone'] ?? null,
            'clock_font_size' => $data['clock_font_size'] ?? 24,
            'clock_font_color' => $data['clock_font_color'] ?? null,
            'clock_background_color' => $data['clock_background_color'] ?? null,
            'clock_position' => $data['clock_position'] ?? 'top-right',
            'display_duration' => $data['display_duration'] ?? 0,
            'start_delay' => $data['start_delay'] ?? 0,
            'end_advance' => $data['end_advance'] ?? 0,
            'z_index' => $data['z_index'] ?? 0,
            'is_active' => $data['is_active'] ?? true,
            'is_default' => $data['is_default'] ?? false,
        ]);

        return $overlay;
    }

    public function updateOverlay(ChannelOverlay $overlay, array $data): ChannelOverlay
    {
        $overlay->update($data);

        return $overlay->fresh();
    }

    public function removeOverlay(ChannelOverlay $overlay): bool
    {
        return $overlay->delete();
    }

    public function startBroadcast(UserChannel $channel, array $data = []): ChannelBroadcastLog
    {
        return ChannelBroadcastLog::create([
            'channel_id' => $channel->id,
            'broadcast_id' => $data['broadcast_id'] ?? Str::uuid(),
            'start_time' => now(),
            'content_type' => $data['content_type'] ?? 'playlist',
            'content_id' => $data['content_id'] ?? null,
            'viewers' => 0,
            'peak_viewers' => 0,
            'bandwidth_used' => 0,
            'status' => 'started',
        ]);
    }

    public function endBroadcast(ChannelBroadcastLog $log, array $data = []): ChannelBroadcastLog
    {
        $log->update([
            'end_time' => now(),
            'duration' => $data['duration'] ?? null,
            'viewers' => $data['viewers'] ?? 0,
            'peak_viewers' => $data['peak_viewers'] ?? 0,
            'bandwidth_used' => $data['bandwidth_used'] ?? 0,
            'status' => $data['status'] ?? 'ended',
            'error_message' => $data['error_message'] ?? null,
        ]);

        return $log->fresh();
    }

    public function subscribeUser(UserChannel $channel, \App\Models\User $user, array $data = []): ChannelSubscription
    {
        return ChannelSubscription::updateOrCreate(
            ['channel_id' => $channel->id, 'user_id' => $user->id],
            [
                'subscription_type' => $data['subscription_type'] ?? 'free',
                'start_date' => now(),
                'end_date' => $data['end_date'] ?? null,
                'auto_renew' => $data['auto_renew'] ?? false,
                'notify_new_content' => $data['notify_new_content'] ?? true,
                'notify_schedule' => $data['notify_schedule'] ?? true,
                'is_active' => true,
            ]
        );
    }

    public function unsubscribeUser(UserChannel $channel, \App\Models\User $user): bool
    {
        return ChannelSubscription::where('channel_id', $channel->id)
            ->where('user_id', $user->id)
            ->update(['is_active' => false]);
    }

    public function addComment(UserChannel $channel, \App\Models\User $user, array $data): ChannelComment
    {
        return ChannelComment::create([
            'channel_id' => $channel->id,
            'user_id' => $user->id,
            'parent_id' => $data['parent_id'] ?? null,
            'comment' => $data['comment'],
            'is_approved' => $data['is_approved'] ?? false,
        ]);
    }

    public function approveComment(ChannelComment $comment): ChannelComment
    {
        $comment->update(['is_approved' => true]);

        return $comment->fresh();
    }

    public function deleteComment(ChannelComment $comment): bool
    {
        return $comment->delete();
    }

    public function getChannelStats(UserChannel $channel): array
    {
        return [
            'total_views' => $channel->viewLogs()->count(),
            'total_watch_time' => $channel->viewLogs()->sum('duration'),
            'total_subscribers' => $channel->subscriptions()->where('is_active', true)->count(),
            'total_comments' => $channel->comments()->where('is_approved', true)->count(),
            'total_playlist_items' => $channel->playlistItems()->where('is_active', true)->count(),
            'total_schedules' => $channel->schedules()->where('is_active', true)->count(),
            'total_overlays' => $channel->overlays()->where('is_active', true)->count(),
            'current_viewers' => $channel->broadcastLogs()
                ->whereIn('status', ['started', 'running'])
                ->sum('viewers'),
        ];
    }
}
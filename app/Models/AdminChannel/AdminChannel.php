<?php

namespace App\Models\AdminChannel;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class AdminChannel extends Model
{
    use HasFactory;

    protected $table = 'admin_channels';

    protected $fillable = [
        'channel_name',
        'channel_slug',
        'channel_number',
        'channel_type',
        'is_my_channel',
        'playlist_type',
        'description',
        'logo_url',
        'banner_url',
        'background_color',
        'accent_color',
        'text_color',
        'watermark_url',
        'stream_url',
        'stream_type',
        'stream_key',
        'output_resolution',
        'output_bitrate',
        'output_frame_rate',
        'video_codec',
        'audio_codec',
        'transcoding_device',
        'broadcast_status',
        'broadcast_mode',
        'scheduled_start',
        'scheduled_end',
        'last_broadcast',
        'timezone',
        'duration_type',
        'playout_mode',
        'default_duration',
        'loop_playlist',
        'shuffle_mode',
        'transition_type',
        'transition_duration',
        'enable_ticker',
        'ticker_text',
        'ticker_speed',
        'ticker_color',
        'ticker_background',
        'ticker_direction',
        'enable_overlay_logo',
        'overlay_logo_position',
        'overlay_logo_x',
        'overlay_logo_y',
        'overlay_logo_size',
        'overlay_logo_opacity',
        'enable_overlay_clock',
        'overlay_clock_position',
        'overlay_clock_x',
        'overlay_clock_y',
        'overlay_clock_format',
        'enable_watermark',
        'watermark_position',
        'watermark_opacity',
        'content_owner',
        'license_type',
        'license_expiry',
        'content_restrictions',
        'region_restrictions',
        'is_public',
        'is_featured',
        'is_adult',
        'featured_order',
        'require_subscription',
        'subscription_package_id',
        'genre',
        'category',
        'language',
        'country',
        'tags',
        'is_active',
        'is_approved',
        'created_by',
        'approved_by',
        'approved_at',
    ];

    protected $casts = [
        'license_expiry' => 'date',
        'scheduled_start' => 'datetime',
        'scheduled_end' => 'datetime',
        'last_broadcast' => 'datetime',
        'approved_at' => 'datetime',
        'enable_ticker' => 'boolean',
        'enable_overlay_logo' => 'boolean',
        'enable_overlay_clock' => 'boolean',
        'enable_watermark' => 'boolean',
        'is_my_channel' => 'boolean',
        'is_public' => 'boolean',
        'is_featured' => 'boolean',
        'is_adult' => 'boolean',
        'is_active' => 'boolean',
        'is_approved' => 'boolean',
        'loop_playlist' => 'boolean',
        'shuffle_mode' => 'boolean',
        'require_subscription' => 'boolean',
        'tags' => 'json',
        'content_restrictions' => 'json',
        'region_restrictions' => 'json',
        'fallback_playlist' => 'json',
    ];

    public function getRouteKeyName(): string
    {
        return 'channel_slug';
    }

    /**
     * Scope route-model binding so non-admin users stay within the My Channels
     * module: they can reach any my-channel (created by admins), but nothing else.
     */
    public function resolveRouteBinding($value, $field = null)
    {
        $query = $this->resolveRouteBindingQuery($this->newQuery(), $value, $field);

        $user = request()?->user();
        if ($user && ! $user->canManageAllMyChannels()) {
            $query->where('is_my_channel', true);
        }

        return $query->firstOrFail();
    }

    public function bouquets(): BelongsToMany
    {
        return $this->belongsToMany(\App\Models\Bouquet::class, 'admin_channel_bouquet', 'admin_channel_id', 'bouquet_id');
    }

    public function packages(): BelongsToMany
    {
        return $this->belongsToMany(\App\Models\SubscriptionPackage::class, 'admin_channel_package', 'admin_channel_id', 'subscription_package_id');
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(\App\Models\AdminChannel\AdminChannelSubscription::class, 'admin_channel_id');
    }

    public function playlistItems(): HasMany
    {
        return $this->hasMany(\App\Models\AdminChannel\AdminChannelPlaylistItem::class, 'admin_channel_id');
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(\App\Models\AdminChannel\AdminChannelSchedule::class, 'admin_channel_id');
    }

    public function overlays(): HasMany
    {
        return $this->hasMany(\App\Models\AdminChannel\AdminChannelOverlay::class, 'admin_channel_id');
    }

    public function broadcastLogs(): HasMany
    {
        return $this->hasMany(\App\Models\AdminChannel\AdminChannelBroadcastLog::class, 'admin_channel_id');
    }

    public function viewLogs(): HasMany
    {
        return $this->hasMany(\App\Models\AdminChannel\AdminChannelViewLog::class, 'admin_channel_id');
    }

    public function analytics(): HasMany
    {
        return $this->hasMany(\App\Models\AdminChannel\AdminChannelAnalytics::class, 'admin_channel_id');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'approved_by');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by');
    }

    public function subscriptionPackage(): BelongsTo
    {
        return $this->belongsTo(\App\Models\SubscriptionPackage::class, 'subscription_package_id');
    }

    public function myChannelSettings(): HasOne
    {
        return $this->hasOne(\App\Models\AdminChannel\MyChannelSetting::class, 'channel_id');
    }

    public function myChannelContent(): HasMany
    {
        return $this->hasMany(\App\Models\AdminChannel\MyChannelContent::class, 'channel_id');
    }

    public function myChannelPlaylist(): HasMany
    {
        return $this->hasMany(\App\Models\AdminChannel\MyChannelPlaylist::class, 'channel_id');
    }

    public function myChannelBroadcasts(): HasMany
    {
        return $this->hasMany(\App\Models\AdminChannel\MyChannelBroadcast::class, 'channel_id');
    }

    public function latestBroadcast(): HasMany
    {
        return $this->hasMany(\App\Models\AdminChannel\MyChannelBroadcast::class, 'channel_id')->latest('start_time');
    }
}
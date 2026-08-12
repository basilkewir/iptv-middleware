<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class UserChannel extends Model
{
    use HasFactory;

    protected $table = 'user_channels';

    protected $fillable = [
        'user_id',
        'channel_name',
        'channel_slug',
        'description',
        'channel_number',
        'logo_url',
        'banner_url',
        'background_color',
        'accent_color',
        'text_color',
        'stream_url',
        'stream_type',
        'stream_key',
        'output_resolution',
        'output_bitrate',
        'playlist_mode',
        'default_duration',
        'loop_playlist',
        'shuffle_mode',
        'is_live',
        'broadcast_status',
        'scheduled_start',
        'scheduled_end',
        'timezone',
        'enable_ticker',
        'ticker_text',
        'ticker_speed',
        'ticker_color',
        'ticker_background',
        'enable_overlay_logo',
        'overlay_logo_position',
        'overlay_logo_size',
        'language',
        'genre',
        'category',
        'is_adult',
        'is_featured',
        'is_active',
        'is_public',
        'approved',
        'approved_at',
        'views',
        'watch_time',
        'favorites',
    ];

    protected $casts = [
        'is_adult' => 'boolean',
        'is_featured' => 'boolean',
        'is_active' => 'boolean',
        'is_public' => 'boolean',
        'approved' => 'boolean',
        'is_live' => 'boolean',
        'loop_playlist' => 'boolean',
        'shuffle_mode' => 'boolean',
        'enable_ticker' => 'boolean',
        'enable_overlay_logo' => 'boolean',
        'default_duration' => 'integer',
        'ticker_speed' => 'integer',
        'overlay_logo_size' => 'integer',
        'views' => 'integer',
        'watch_time' => 'integer',
        'favorites' => 'integer',
        'output_bitrate' => 'integer',
        'scheduled_start' => 'datetime',
        'scheduled_end' => 'datetime',
        'approved_at' => 'datetime',
        'last_broadcast' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class);
    }

    public function playlistItems(): HasMany
    {
        return $this->hasMany(\App\Models\Channel\ChannelPlaylistItem::class, 'channel_id');
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(\App\Models\Channel\ChannelSchedule::class, 'channel_id');
    }

    public function overlays(): HasMany
    {
        return $this->hasMany(\App\Models\Channel\ChannelOverlay::class, 'channel_id');
    }

    public function broadcastLogs(): HasMany
    {
        return $this->hasMany(\App\Models\Channel\ChannelBroadcastLog::class, 'channel_id');
    }

    public function viewLogs(): HasMany
    {
        return $this->hasMany(\App\Models\Channel\ChannelViewLog::class, 'channel_id');
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(\App\Models\Channel\ChannelSubscription::class, 'channel_id');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(\App\Models\Channel\ChannelComment::class, 'channel_id');
    }
}

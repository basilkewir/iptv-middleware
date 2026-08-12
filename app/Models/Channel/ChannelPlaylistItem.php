<?php

namespace App\Models\Channel;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChannelPlaylistItem extends Model
{
    use HasFactory;

    protected $table = 'channel_playlist_items';

    protected $fillable = [
        'channel_id',
        'content_type',
        'content_id',
        'content_title',
        'content_description',
        'media_url',
        'thumbnail_url',
        'media_duration',
        'file_size',
        'order_index',
        'start_time_offset',
        'end_time_offset',
        'transition_duration',
        'transition_type',
        'scheduled_start',
        'scheduled_end',
        'day_of_week',
        'override_duration',
        'override_quality',
        'override_volume',
        'is_active',
        'is_featured',
        'plays',
        'watch_time',
    ];

    protected $casts = [
        'media_duration' => 'integer',
        'file_size' => 'integer',
        'order_index' => 'integer',
        'start_time_offset' => 'integer',
        'end_time_offset' => 'integer',
        'transition_duration' => 'integer',
        'override_duration' => 'integer',
        'override_volume' => 'integer',
        'is_active' => 'boolean',
        'is_featured' => 'boolean',
        'plays' => 'integer',
        'watch_time' => 'integer',
        'scheduled_start' => 'datetime',
        'scheduled_end' => 'datetime',
        'day_of_week' => 'array',
    ];

    public function channel(): BelongsTo
    {
        return $this->belongsTo(\App\Models\UserChannel::class, 'channel_id');
    }
}
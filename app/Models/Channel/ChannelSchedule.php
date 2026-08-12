<?php

namespace App\Models\Channel;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChannelSchedule extends Model
{
    use HasFactory;

    protected $table = 'channel_schedules';

    protected $fillable = [
        'channel_id',
        'day_of_week',
        'start_time',
        'end_time',
        'playlist_id',
        'content_type',
        'loop_mode',
        'priority',
        'is_active',
    ];

    protected $casts = [
        'day_of_week' => 'integer',
        'priority' => 'integer',
        'is_active' => 'boolean',
        'playlist_id' => 'integer',
    ];

    public function channel(): BelongsTo
    {
        return $this->belongsTo(\App\Models\UserChannel::class, 'channel_id');
    }

    public function playlistItem(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Channel\ChannelPlaylistItem::class, 'playlist_id');
    }
}
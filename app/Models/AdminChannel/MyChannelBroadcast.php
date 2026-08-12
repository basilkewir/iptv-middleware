<?php

namespace App\Models\AdminChannel;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MyChannelBroadcast extends Model
{
    protected $table = 'my_channel_broadcasts';

    protected $fillable = [
        'channel_id', 'session_id', 'start_time', 'end_time', 'scheduled_end',
        'duration', 'playlist_snapshot', 'current_item_id', 'current_item_position',
        'total_viewers', 'peak_viewers', 'total_views', 'bandwidth_used',
        'stream_quality', 'avg_bitrate', 'status', 'error_message',
    ];

    protected $casts = [
        'playlist_snapshot' => 'array',
        'start_time' => 'datetime',
        'end_time' => 'datetime',
        'scheduled_end' => 'datetime',
    ];

    public function channel(): BelongsTo
    {
        return $this->belongsTo(AdminChannel::class, 'channel_id');
    }

    public function currentItem(): BelongsTo
    {
        return $this->belongsTo(MyChannelContent::class, 'current_item_id');
    }
}

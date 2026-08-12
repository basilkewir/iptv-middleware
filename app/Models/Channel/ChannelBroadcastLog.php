<?php

namespace App\Models\Channel;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChannelBroadcastLog extends Model
{
    use HasFactory;

    protected $table = 'channel_broadcast_logs';

    protected $fillable = [
        'channel_id',
        'broadcast_id',
        'start_time',
        'end_time',
        'duration',
        'content_type',
        'content_id',
        'viewers',
        'peak_viewers',
        'bandwidth_used',
        'status',
        'error_message',
    ];

    protected $casts = [
        'start_time' => 'datetime',
        'end_time' => 'datetime',
        'duration' => 'integer',
        'viewers' => 'integer',
        'peak_viewers' => 'integer',
        'bandwidth_used' => 'integer',
    ];

    public function channel(): BelongsTo
    {
        return $this->belongsTo(\App\Models\UserChannel::class, 'channel_id');
    }
}
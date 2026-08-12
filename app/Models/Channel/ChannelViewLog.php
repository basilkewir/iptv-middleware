<?php

namespace App\Models\Channel;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChannelViewLog extends Model
{
    use HasFactory;

    protected $table = 'channel_view_logs';

    protected $fillable = [
        'channel_id',
        'user_id',
        'session_id',
        'ip_address',
        'user_agent',
        'start_time',
        'end_time',
        'duration',
        'progress',
        'quality',
        'bitrate',
        'resolution',
    ];

    protected $casts = [
        'start_time' => 'datetime',
        'end_time' => 'datetime',
        'duration' => 'integer',
        'progress' => 'integer',
        'bitrate' => 'integer',
    ];

    public function channel(): BelongsTo
    {
        return $this->belongsTo(\App\Models\UserChannel::class, 'channel_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class);
    }
}
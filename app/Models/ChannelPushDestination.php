<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChannelPushDestination extends Model
{
    use HasFactory;

    protected $fillable = [
        'channel_id',
        'push_destination_id',
        'stream_key',
        'video_bitrate',
        'audio_bitrate',
        'status',
        'ffmpeg_pid',
        'started_at',
        'stopped_at',
        'last_error',
        'restart_count',
        'last_restart_at',
    ];

    protected $casts = [
        'ffmpeg_pid' => 'integer',
        'video_bitrate' => 'integer',
        'audio_bitrate' => 'integer',
        'restart_count' => 'integer',
        'started_at' => 'datetime',
        'stopped_at' => 'datetime',
        'last_restart_at' => 'datetime',
    ];

    public function channel(): BelongsTo
    {
        return $this->belongsTo(Channel::class);
    }

    public function pushDestination(): BelongsTo
    {
        return $this->belongsTo(PushDestination::class);
    }

    public function isPushing(): bool
    {
        return $this->status === 'pushing';
    }
}

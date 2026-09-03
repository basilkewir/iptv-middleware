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
        'status',
        'ffmpeg_pid',
        'started_at',
        'stopped_at',
        'last_error',
    ];

    protected $casts = [
        'ffmpeg_pid' => 'integer',
        'started_at' => 'datetime',
        'stopped_at' => 'datetime',
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

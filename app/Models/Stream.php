<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Stream extends Model
{
    use HasFactory;

    protected $fillable = [
        'channel_id',
        'server_id',
        'stream_key',
        'stream_url',
        'stream_type',
        'status',
        'started_at',
        'stopped_at',
        'current_viewers',
        'total_watch_time',
        'avg_bitrate',
        'avg_fps',
        'avg_latency',
        'codec',
        'resolution',
        'bitrate',
    ];

    protected $casts = [

            'started_at' => 'datetime',
            'stopped_at' => 'datetime',
            'current_viewers' => 'integer',
            'total_watch_time' => 'integer',
            'avg_bitrate' => 'integer',
            'avg_fps' => 'integer',
            'avg_latency' => 'integer',
            'bitrate' => 'integer',
        
    ];

    public function channel(): BelongsTo
    {
        return $this->belongsTo(Channel::class);
    }

    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }

    public function connectionLogs(): HasMany
    {
        return $this->hasMany(StreamingLog::class);
    }

    public function errorLogs(): HasMany
    {
        return $this->hasMany(StreamingLog::class)->where('status', 'error');
    }
}
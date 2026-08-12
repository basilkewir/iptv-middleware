<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VODWatchHistory extends Model
{
    protected $table = 'vod_watch_history';

    protected $fillable = [
        'user_id', 'vod_content_id', 'episode_id', 'progress',
        'watch_duration', 'last_watched', 'watch_count',
        'device_id', 'ip_address', 'user_agent', 'completed',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'vod_content_id' => 'integer',
        'episode_id' => 'integer',
        'progress' => 'integer',
        'watch_duration' => 'integer',
        'watch_count' => 'integer',
        'device_id' => 'integer',
        'last_watched' => 'datetime',
        'completed' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function vodContent(): BelongsTo
    {
        return $this->belongsTo(VODContent::class, 'vod_content_id');
    }

    public function episode(): BelongsTo
    {
        return $this->belongsTo(VODEpisode::class, 'episode_id');
    }

    public function scopeCompleted($query)
    {
        return $query->where('completed', true);
    }

    public function scopeWatched($query)
    {
        return $query->where('progress', '>', 0);
    }
}

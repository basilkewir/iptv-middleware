<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VODEpisode extends Model
{
    protected $table = 'vod_episodes';

    protected $fillable = [
        'season_id', 'episode_number', 'title', 'description',
        'duration', 'thumbnail_url', 'stream_url', 'quality',
        'file_size', 'file_path', 'air_date', 'guest_stars',
        'director', 'writer', 'rating', 'tmdb_id', 'tmdb_data',
        'is_available', 'is_featured', 'views', 'watch_time',
    ];

    protected $casts = [
        'episode_number' => 'integer',
        'season_id' => 'integer',
        'duration' => 'integer',
        'file_size' => 'integer',
        'guest_stars' => 'array',
        'tmdb_data' => 'array',
        'rating' => 'decimal:1',
        'is_available' => 'boolean',
        'is_featured' => 'boolean',
        'views' => 'integer',
        'watch_time' => 'integer',
        'air_date' => 'date',
    ];

    public function season(): BelongsTo
    {
        return $this->belongsTo(VODSeason::class, 'season_id');
    }

    public function vodContent(): BelongsTo
    {
        return $this->hasOneThrough(VODContent::class, VODSeason::class, 'id', 'id', 'season_id', 'vod_content_id');
    }
}

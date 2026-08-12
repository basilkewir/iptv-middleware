<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VODMedia extends Model
{
    protected $table = 'vod_media';

    protected $fillable = [
        'content_type', 'content_id', 'vod_content_id',
        'season_id', 'season_number', 'episode_number',
        'episode_title', 'stream_url', 'stream_type',
        'quality', 'resolution', 'codec', 'file_name',
        'file_path', 'file_size', 'bitrate', 'duration',
        'air_date', 'still_url', 'is_available',
        'is_transcoded', 'language', 'subtitles',
    ];

    protected $casts = [
        'season_number' => 'integer',
        'episode_number' => 'integer',
        'file_size' => 'integer',
        'bitrate' => 'integer',
        'duration' => 'integer',
        'is_available' => 'boolean',
        'is_transcoded' => 'boolean',
        'subtitles' => 'array',
        'air_date' => 'date',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function media()
    {
        return $this->morphTo('media');
    }

    public function vodContent(): BelongsTo
    {
        return $this->belongsTo(VODContent::class, 'vod_content_id');
    }

    public function season(): BelongsTo
    {
        return $this->belongsTo(VODSeason::class, 'season_id');
    }

    public function episode(): BelongsTo
    {
        return $this->belongsTo(VODEpisode::class, 'episode_id');
    }

    public function qualityCache(): BelongsTo
    {
        return $this->hasOne(VODQualityCache::class, 'vod_media_id');
    }
}

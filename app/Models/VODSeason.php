<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VODSeason extends Model
{
    protected $table = 'vod_seasons';

    protected $fillable = [
        'vod_content_id', 'season_number', 'title', 'description',
        'poster_url', 'backdrop_url', 'season_year', 'episode_count',
        'total_duration', 'is_available', 'air_date',
    ];

    protected $casts = [
        'season_number' => 'integer',
        'season_year' => 'integer',
        'episode_count' => 'integer',
        'total_duration' => 'integer',
        'is_available' => 'boolean',
        'air_date' => 'date',
    ];

    public function vodContent(): BelongsTo
    {
        return $this->belongsTo(VODContent::class, 'vod_content_id');
    }

    public function episodes(): HasMany
    {
        return $this->hasMany(VODEpisode::class, 'season_id')
            ->orderBy('episode_number');
    }
}

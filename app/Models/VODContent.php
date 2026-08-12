<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\SoftDeletes;

class VODContent extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'vod_content';

    protected $fillable = [
        'title', 'original_title', 'slug', 'description', 'year', 'imdb_id', 'tmdb_id',
        'rating', 'poster_url', 'backdrop_url', 'banner_url', 'thumbnail_url',
        'trailer_url', 'type', 'content_type', 'duration', 'director', 'cast', 'genre',
        'country', 'language', 'age_rating', 'release_year', 'tmdb_data',
        'quality_level', 'quality_badge', 'quality_updated_at',
        'season_count', 'episode_count', 'total_duration',
        'is_active', 'is_featured', 'is_adult', 'is_available',
        'featured_order', 'view_count', 'views', 'watch_time',
        'like_count', 'rating_count', 'released_at',
    ];

    protected $casts = [
        'cast' => 'array',
        'genre' => 'array',
        'tmdb_data' => 'array',
        'year' => 'integer',
        'duration' => 'integer',
        'season_count' => 'integer',
        'episode_count' => 'integer',
        'total_duration' => 'integer',
        'featured_order' => 'integer',
        'views' => 'integer',
        'watch_time' => 'integer',
        'like_count' => 'integer',
        'rating_count' => 'integer',
        'rating' => 'decimal:3',
        'is_active' => 'boolean',
        'is_featured' => 'boolean',
        'is_adult' => 'boolean',
        'is_available' => 'boolean',
        'released_at' => 'datetime',
        'quality_updated_at' => 'datetime',
    ];

    protected $appends = [
        'content_type',
        'release_year',
    ];

    public function getContentTypeAttribute(): string
    {
        return $this->attributes['type'] ?? 'movie';
    }

    public function setContentTypeAttribute(string $value): void
    {
        $this->attributes['type'] = $value;
    }

    public function getReleaseYearAttribute(): ?int
    {
        return $this->attributes['year'] ?? null;
    }

    public function setReleaseYearAttribute(?int $value): void
    {
        $this->attributes['year'] = $value;
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(ContentCategory::class, 'vod_categories', 'vod_content_id', 'category_id')
            ->withTimestamps();
    }

    public function seasons(): HasMany
    {
        return $this->hasMany(VODSeason::class, 'vod_content_id')
            ->orderBy('season_number');
    }

    public function vodMedia(): HasMany
    {
        return $this->hasMany(VODMedia::class, 'vod_content_id');
    }

    public function episodes(): HasManyThrough
    {
        return $this->hasManyThrough(
            VODEpisode::class,
            VODSeason::class,
            'vod_content_id',
            'season_id',
            'id',
            'id'
        );
    }

    public function cast(): HasMany
    {
        return $this->hasMany(VODCast::class, 'vod_content_id');
    }

    public function crew(): HasMany
    {
        return $this->hasMany(VODCrew::class, 'vod_content_id');
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(VODReview::class, 'vod_content_id');
    }

    public function favorites(): HasMany
    {
        return $this->hasMany(VODFavorite::class, 'vod_content_id');
    }

    public function watchlist(): HasMany
    {
        return $this->hasMany(VODWatchlist::class, 'vod_content_id');
    }

    public function watchHistory(): HasMany
    {
        return $this->hasMany(VODWatchHistory::class, 'vod_content_id');
    }

    public function bouquets(): BelongsToMany
    {
        return $this->belongsToMany(Bouquet::class, 'bouquet_vod', 'vod_content_id', 'bouquet_id')
            ->withTimestamps();
    }

    public function persons(): BelongsToMany
    {
        return $this->belongsToMany(VODPerson::class, 'vod_casts', 'vod_content_id', 'person_id')
            ->withPivot(['character_name', 'role', 'order_index'])
            ->withTimestamps();
    }

    public function scopeByType($query, string $type)
    {
        return $query->where('type', $type);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeAvailable($query)
    {
        return $query->where('is_available', true);
    }

    public function scopeFeatured($query)
    {
        return $query->where('is_featured', true);
    }

    public function isMovie(): bool
    {
        return $this->content_type === 'movie';
    }

    public function isSeries(): bool
    {
        return in_array($this->content_type, ['series', 'tv_show']);
    }
}

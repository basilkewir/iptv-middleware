<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TMDBMetadata extends Model
{
    protected $table = 'tmdb_cache';

    public $timestamps = false;

    protected $fillable = [
        'tmdb_id',
        'media_type',
        'data',
        'poster_path',
        'backdrop_path',
        'popularity',
        'vote_average',
        'vote_count',
        'release_date',
        'last_updated',
    ];

    protected $casts = [
        'data' => 'array',
        'popularity' => 'float',
        'vote_average' => 'float',
        'vote_count' => 'integer',
        'release_date' => 'date',
        'last_updated' => 'datetime',
    ];

    public static function findByTmdb(int $tmdbId, string $mediaType): ?self
    {
        return static::where('tmdb_id', $tmdbId)
            ->where('media_type', $mediaType)
            ->first();
    }
}

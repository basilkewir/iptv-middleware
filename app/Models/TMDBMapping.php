<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class TMDBMapping extends Model
{
    protected $table = 'tmdb_mapping';

    public $timestamps = false;

    protected $fillable = [
        'content_type',
        'content_id',
        'tmdb_id',
        'media_type',
        'is_primary',
        'confidence_score',
        'mapped_at',
    ];

    protected $casts = [
        'is_primary' => 'boolean',
        'confidence_score' => 'float',
        'mapped_at' => 'datetime',
    ];

    public function content(): MorphTo
    {
        return $this->morphTo();
    }

    public static function findForContent(string $contentType, int $contentId)
    {
        return static::where('content_type', $contentType)
            ->where('content_id', $contentId)
            ->get();
    }
}

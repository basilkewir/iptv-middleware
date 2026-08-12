<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VODReview extends Model
{
    protected $table = 'vod_reviews';

    protected $fillable = [
        'vod_content_id', 'user_id', 'rating', 'title', 'comment',
        'spoiler', 'is_approved', 'likes', 'reported',
    ];

    protected $casts = [
        'vod_content_id' => 'integer',
        'user_id' => 'integer',
        'rating' => 'integer',
        'spoiler' => 'boolean',
        'is_approved' => 'boolean',
        'likes' => 'integer',
        'reported' => 'boolean',
    ];

    public function vodContent(): BelongsTo
    {
        return $this->belongsTo(VODContent::class, 'vod_content_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function isApproved(): bool
    {
        return $this->is_approved;
    }

    public function scopeApproved($query)
    {
        return $query->where('is_approved', true);
    }
}

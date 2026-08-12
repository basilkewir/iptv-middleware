<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserReview extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'vod_content_id',
        'rating',
        'title',
        'review',
        'is_approved',
    ];

    protected $casts = [

            'rating' => 'decimal:1',
            'is_approved' => 'boolean',
        
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function vodContent(): BelongsTo
    {
        return $this->belongsTo(VODContent::class, 'vod_content_id');
    }
}

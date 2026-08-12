<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VODFavorite extends Model
{
    protected $table = 'vod_favorites';

    public $timestamps = ['created_at'];

    const UPDATED_AT = null;

    protected $fillable = [
        'user_id', 'vod_content_id', 'favorite_order',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'vod_content_id' => 'integer',
        'favorite_order' => 'integer',
        'created_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function vodContent(): BelongsTo
    {
        return $this->belongsTo(VODContent::class, 'vod_content_id');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserWatchHistory extends Model
{
    use HasFactory;

    protected $table = 'user_watch_history';

    protected $fillable = [
        'user_id',
        'channel_id',
        'vod_content_id',
        'media_id',
        'watched_at',
        'duration_watched',
        'progress',
        'completed',
    ];

    protected $casts = [

            'watched_at' => 'datetime',
            'duration_watched' => 'integer',
            'progress' => 'decimal:2',
            'completed' => 'boolean',
        
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function channel(): BelongsTo
    {
        return $this->belongsTo(Channel::class);
    }

    public function vodContent(): BelongsTo
    {
        return $this->belongsTo(VODContent::class, 'vod_content_id');
    }
}

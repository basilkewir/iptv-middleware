<?php

namespace App\Models\Channel;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ChannelComment extends Model
{
    use HasFactory;

    protected $table = 'channel_comments';

    protected $fillable = [
        'channel_id',
        'user_id',
        'parent_id',
        'comment',
        'is_approved',
        'likes',
        'dislikes',
    ];

    protected $casts = [
        'is_approved' => 'boolean',
        'likes' => 'integer',
        'dislikes' => 'integer',
        'parent_id' => 'integer',
    ];

    public function channel(): BelongsTo
    {
        return $this->belongsTo(\App\Models\UserChannel::class, 'channel_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Channel\ChannelComment::class, 'parent_id');
    }

    public function replies(): HasMany
    {
        return $this->hasMany(\App\Models\Channel\ChannelComment::class, 'parent_id');
    }
}
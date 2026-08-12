<?php

namespace App\Models\Channel;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChannelSubscription extends Model
{
    use HasFactory;

    protected $table = 'channel_subscriptions';

    protected $fillable = [
        'channel_id',
        'user_id',
        'subscription_type',
        'start_date',
        'end_date',
        'auto_renew',
        'notify_new_content',
        'notify_schedule',
        'is_active',
    ];

    protected $casts = [
        'start_date' => 'datetime',
        'end_date' => 'datetime',
        'auto_renew' => 'boolean',
        'notify_new_content' => 'boolean',
        'notify_schedule' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function channel(): BelongsTo
    {
        return $this->belongsTo(\App\Models\UserChannel::class, 'channel_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class);
    }
}
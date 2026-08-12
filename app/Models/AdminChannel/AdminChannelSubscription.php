<?php

namespace App\Models\AdminChannel;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdminChannelSubscription extends Model
{
    use HasFactory;

    protected $table = 'admin_channel_subscriptions';

    protected $fillable = [
        'admin_channel_id',
        'user_id',
        'subscription_package_id',
        'status',
        'subscribed_at',
        'expires_at',
        'cancelled_at',
        'metadata',
    ];

    protected $casts = [
        'subscribed_at' => 'datetime',
        'expires_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'metadata' => 'json',
    ];

    public function adminChannel(): BelongsTo
    {
        return $this->belongsTo(AdminChannel::class, 'admin_channel_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'user_id');
    }

    public function subscriptionPackage(): BelongsTo
    {
        return $this->belongsTo(\App\Models\SubscriptionPackage::class, 'subscription_package_id');
    }
}
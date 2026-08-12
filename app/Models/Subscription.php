<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Subscription extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'subscription_package_id',
        'status',
        'start_date',
        'end_date',
        'auto_renew',
        'payment_reference',
    ];

    protected $casts = [

            'start_date' => 'datetime',
            'end_date' => 'datetime',
            'auto_renew' => 'boolean',
        
    ];

    public function isNeverExpire(): bool
    {
        return $this->end_date === null;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function subscriptionPackage(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPackage::class);
    }

    public function history(): HasMany
    {
        return $this->hasMany(SubscriptionHistory::class);
    }
}

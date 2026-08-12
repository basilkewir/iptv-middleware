<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SubscriptionPackage extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'price',
        'billing_cycle',
        'duration_days',
        'never_expire',
        'features',
        'max_connections',
        'is_active',
        'sort_order',
    ];

    protected $casts = [

            'price' => 'decimal:2',
            'duration_days' => 'integer',
            'never_expire' => 'boolean',
            'features' => 'array',
            'max_connections' => 'integer',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        
    ];

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }
}

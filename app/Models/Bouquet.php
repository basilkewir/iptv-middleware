<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Bouquet extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'icon_url',
        'category_id',
        'package_id',
        'sort_order',
        'is_active',
        'price',
        'parent_id',
    ];

    protected $casts = [

            'is_active' => 'boolean',
            'sort_order' => 'integer',
            'price' => 'decimal:2',
        
    ];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Bouquet::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Bouquet::class, 'parent_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ContentCategory::class);
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPackage::class);
    }

    public function channels(): BelongsToMany
    {
        return $this->belongsToMany(Channel::class, 'bouquet_channels', 'bouquet_id', 'channel_id')
            ->withPivot('sort_order')
            ->withTimestamps();
    }

    public function vodContent(): BelongsToMany
    {
        return $this->belongsToMany(VODContent::class, 'bouquet_vod', 'bouquet_id', 'vod_content_id')
            ->withPivot('sort_order')
            ->withTimestamps();
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'bouquet_user', 'bouquet_id', 'user_id')
            ->withTimestamps();
    }

    public function packages(): BelongsToMany
    {
        return $this->belongsToMany(SubscriptionPackage::class, 'bouquet_package', 'bouquet_id', 'subscription_package_id')
            ->withTimestamps();
    }
}

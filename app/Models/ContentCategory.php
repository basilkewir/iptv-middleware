<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ContentCategory extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'parent_id',
        'sort_order',
        'is_active',
        'icon',
        'banner_image',
        'color',
        'category_type',
        'auto_assign_channels',
        'auto_assign_vod',
        'include_in_m3u',
        'include_in_xmltv',
    ];

    protected $casts = [

            'is_active' => 'boolean',
            'sort_order' => 'integer',
            'auto_assign_channels' => 'boolean',
            'auto_assign_vod' => 'boolean',
            'include_in_m3u' => 'boolean',
            'include_in_xmltv' => 'boolean',
        
    ];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(ContentCategory::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(ContentCategory::class, 'parent_id');
    }

    public function channels(): BelongsToMany
    {
        return $this->belongsToMany(Channel::class, 'channel_categories', 'category_id', 'channel_id')
            ->withTimestamps();
    }

    public function vodContent(): BelongsToMany
    {
        return $this->belongsToMany(VODContent::class, 'vod_categories', 'category_id', 'vod_content_id')
            ->withTimestamps();
    }
}

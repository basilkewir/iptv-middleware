<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Channel extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'channel_number',
        'description',
        'logo_url',
        'genre',
        'country',
        'language',
        'stream_url',
        'stream_type',
        'backup_url_1',
        'backup_url_2',
        'quality',
        'bitrate',
        'epg_id',
        'epg_source_id',
        'epg_language',
        'timezone_offset',
        'is_active',
        'is_free',
        'is_adult',
        'is_available_to_all',
        'ip_restriction',
        'transcoding_enabled',
        'transcoding_profile',
        'transcoding_resolution',
        'transcoding_video_codec',
        'transcoding_audio_codec',
        'quality_level',
        'quality_badge',
        'quality_updated_at',
        'sort_order',
    ];

    protected $casts = [

            'is_active' => 'boolean',
            'is_free' => 'boolean',
            'is_adult' => 'boolean',
            'is_available_to_all' => 'boolean',
            'transcoding_enabled' => 'boolean',
            'channel_number' => 'integer',
            'bitrate' => 'integer',
            'sort_order' => 'integer',
        
    ];

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(ContentCategory::class, 'channel_categories', 'channel_id', 'category_id')
            ->withTimestamps();
    }

    public function epgPrograms(): HasMany
    {
        return $this->hasMany(EPGProgram::class);
    }

    public function epgSource(): BelongsTo
    {
        return $this->belongsTo(EPGSource::class);
    }

    public function streamAssignments(): HasMany
    {
        return $this->hasMany(StreamAssignment::class);
    }

    public function logs(): HasMany
    {
        return $this->hasMany(StreamingLog::class);
    }

    public function bouquets(): BelongsToMany
    {
        return $this->belongsToMany(Bouquet::class, 'bouquet_channels', 'channel_id', 'bouquet_id')
            ->withPivot('sort_order')
            ->withTimestamps();
    }

    public function restrictedPackages(): BelongsToMany
    {
        return $this->belongsToMany(SubscriptionPackage::class, 'channel_restricted_packages', 'channel_id', 'package_id')
            ->withTimestamps();
    }
}

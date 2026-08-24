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

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (Channel $channel) {
            if (is_null($channel->active_stream_url) && !is_null($channel->stream_url)) {
                $channel->active_stream_url = $channel->stream_url;
            }
        });
    }

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
        'program_number',
        'local_address',
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
        'source_status',
        'source_last_checked_at',
        'source_last_online_at',
        'source_check_attempts',
        'source_last_error',
        'active_stream_url',
        'active_source_index',
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
            'program_number' => 'integer',
            'source_check_attempts' => 'integer',
            'active_source_index' => 'integer',
            'source_last_checked_at' => 'datetime',
            'source_last_online_at' => 'datetime',
        
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

    /**
     * Get the URL that the ingest process should currently use.
     * Falls back to stream_url if active_stream_url is not set.
     */
    public function getActiveSourceUrlAttribute(): ?string
    {
        return $this->attributes['active_stream_url'] ?? $this->stream_url;
    }

    /**
     * Return all source URLs in priority order: [main, backup1, backup2].
     */
    public function getSourceUrls(): array
    {
        return array_filter([
            $this->stream_url,
            $this->backup_url_1,
            $this->backup_url_2,
        ], fn ($url) => !empty($url));
    }

    /**
     * Get a human-readable label for the currently active source.
     */
    public function getActiveSourceLabelAttribute(): string
    {
        return match ($this->active_source_index) {
            1 => 'Backup 1',
            2 => 'Backup 2',
            default => 'Primary',
        };
    }
}

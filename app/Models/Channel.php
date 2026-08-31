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
        'source_type',
        'source_url',
        'youtube_url',
        'youtube_cookies',
        'youtube_verified',
        'youtube_verified_at',
        'youtube_url_1',
        'youtube_url_1_verified',
        'youtube_url_2',
        'youtube_url_2_verified',
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
        'transcoding_device',
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
        'source_statuses_json',
        'sources_last_probed_at',
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
            'youtube_verified' => 'boolean',
            'youtube_url_1_verified' => 'boolean',
            'youtube_url_2_verified' => 'boolean',
            'sources_last_probed_at' => 'datetime',
            'source_last_checked_at' => 'datetime',
            'source_last_online_at' => 'datetime',
            'youtube_verified_at' => 'datetime',
            'youtube_cookies' => 'array',

    ];

    protected $appends = [
        'source_statuses',
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
     * Rewrite internal localhost:8080 (Flussonic) URLs to the public APP_URL
     * so external clients always receive a reachable stream URL.
     * Internal services that need the raw URL should read $channel->attributes['stream_url'].
     */
    private function publicUrl(?string $url): ?string
    {
        if ($url === null) {
            return null;
        }
        if (str_contains($url, 'localhost:8080')) {
            return str_replace('http://localhost:8080', rtrim(config('app.url'), '/'), $url);
        }
        return $url;
    }

    public function getStreamUrlAttribute(): ?string
    {
        return $this->publicUrl($this->attributes['stream_url'] ?? null);
    }

    public function getActiveStreamUrlAttribute(): ?string
    {
        return $this->publicUrl($this->attributes['active_stream_url'] ?? $this->attributes['stream_url'] ?? null);
    }

    /**
     * Get the URL that the ingest process should currently use.
     * Falls back to stream_url if active_stream_url is not set.
     */
    public function getActiveSourceUrlAttribute(): ?string
    {
        return $this->attributes['active_stream_url'] ?? $this->attributes['stream_url'] ?? null;
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
     * Per-source live status map, keyed by source index (0=primary, 1=backup1, 2=backup2).
     * Each entry: { index, label, url, status, last_checked_at, last_online_at, error }.
     */
    public function getSourceStatusesAttribute(): array
    {
        $raw = json_decode((string) ($this->attributes['source_statuses_json'] ?? ''), true);
        if (!is_array($raw)) {
            $raw = [];
        }
        return $this->normalizeSourceStatuses($raw);
    }

    public function setSourceStatusesAttribute(mixed $value): void
    {
        $this->attributes['source_statuses_json'] = json_encode($value ?: []);
    }

    /**
     * Normalize a per-source status map so every configured source has an entry.
     */
    public function normalizeSourceStatuses(array $statuses): array
    {
        $labels = ['Primary', 'Backup 1', 'Backup 2'];
        $urls = $this->getSourceUrls();
        $activeIndex = $this->active_source_index;

        $merged = [];
        for ($i = 0; $i < 3; $i++) {
            $configured = isset($urls[$i]);
            $exists = isset($statuses[$i]) && is_array($statuses[$i]);

            $merged[$i] = [
                'index'           => $i,
                'label'           => $labels[$i],
                'url'             => $exists ? ($statuses[$i]['url'] ?? null) : ($configured ? $urls[$i] : null),
                'status'          => $exists ? ($statuses[$i]['status'] ?? 'unknown') : ($configured ? 'unknown' : 'unconfigured'),
                'last_checked_at' => $exists ? ($statuses[$i]['last_checked_at'] ?? null) : null,
                'last_online_at'  => $exists ? ($statuses[$i]['last_online_at'] ?? null) : null,
                'error'           => $exists ? ($statuses[$i]['error'] ?? null) : null,
                'is_active'       => $i === $activeIndex && $configured,
            ];
        }

        return $merged;
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

    /**
     * Get the resolved source URL used by ffmpeg/ingest.
     * For YouTube channels this returns the resolved HLS URL stored in source_url.
     * For regular streams this returns stream_url.
     */
    public function getSourceUrlAttribute(): ?string
    {
        if ($this->source_type === 'youtube' && !empty($this->attributes['source_url'])) {
            return $this->attributes['source_url'];
        }
        return $this->attributes['active_stream_url'] ?? $this->attributes['stream_url'] ?? null;
    }

    /**
     * Check if this channel is a YouTube source.
     */
    public function isYouTube(): bool
    {
        return $this->source_type === 'youtube';
    }

    /**
     * Get the YouTube channel URL.
     */
    public function getYouTubeUrl(): ?string
    {
        return $this->youtube_url;
    }

    /**
     * Set the YouTube channel URL and extract the channel ID.
     */
    public function setYouTubeUrl(string $url): void
    {
        $this->youtube_url = $url;
        $this->source_type = 'youtube';
        $this->youtube_cookies = $this->youtube_cookies ?? [];
    }

    /**
     * Store cookies/verification data to bypass YouTube robot checks.
     */
    public function setYouTubeCookies(array $cookies): void
    {
        $this->youtube_cookies = $cookies;
        $this->youtube_verified = true;
        $this->youtube_verified_at = now();
    }

    /**
     * Get cookies for YouTube robot verification bypass.
     */
    public function getYouTubeCookies(): array
    {
        return $this->youtube_cookies ?? [];
    }

    /**
     * Check if YouTube robot verification has been bypassed.
     */
    public function isYouTubeVerified(): bool
    {
        return (bool) $this->youtube_verified;
    }

    /**
     * Build the cookie header string from stored cookies for HTTP requests.
     */
    public function getYouTubeCookieHeader(): string
    {
        $cookies = $this->youtube_cookies ?? [];
        if (empty($cookies)) {
            return '';
        }
        return collect($cookies)->map(fn ($value, $name) => "{$name}={$value}")->join('; ');
    }

    /**
     * Get all source URLs including YouTube-resolved URLs.
     */
    public function getAllSourceUrls(): array
    {
        if ($this->isYouTube()) {
            $urls = array_filter([
                $this->source_url,
                $this->stream_url,
                $this->backup_url_1,
                $this->backup_url_2,
            ], fn ($url) => !empty($url));
        } else {
            $urls = $this->getSourceUrls();
        }
        return array_values($urls);
    }

    /**
     * Get the YouTube URL configured for a backup source (1 or 2).
     */
    public function getYouTubeBackupUrl(int $index): ?string
    {
        return $index === 1 ? $this->youtube_url_1 : $this->youtube_url_2;
    }

    /**
     * Get whether a backup source (1 or 2) is YouTube-backed.
     */
    public function isYouTubeBackup(int $index): bool
    {
        return !empty($this->getYouTubeBackupUrl($index));
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PushDestination extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'protocol',
        'url',
        'stream_key',
        'username',
        'password',
        'is_active',
        'notes',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    protected $hidden = [
        'password',
    ];

    public function channelPushDestinations(): HasMany
    {
        return $this->hasMany(ChannelPushDestination::class);
    }

    public function getFullUrlAttribute(): string
    {
        $base = rtrim($this->url, '/');
        if (! empty($this->stream_key)) {
            return $base.'/'.ltrim($this->stream_key, '/');
        }

        return $base;
    }

    /**
     * Build the FFmpeg output URL with optional auth credentials.
     * RTMP: rtmp://user:pass@host/live/stream_key
     * SRT:  srt://host:port?passphrase=...
     */
    public function getAuthenticatedUrlAttribute(): string
    {
        $url = $this->full_url;

        if (empty($this->username) && empty($this->password)) {
            return $url;
        }

        if ($this->protocol === 'rtmp') {
            $parsed = parse_url($url);
            if ($parsed === false) {
                return $url;
            }

            $auth = '';
            if (! empty($this->username)) {
                $auth = rawurlencode($this->username);
                if (! empty($this->password)) {
                    $auth .= ':'.rawurlencode($this->password);
                }
                $auth .= '@';
            }

            $host = $parsed['host'] ?? '';
            $port = isset($parsed['port']) ? ':'.$parsed['port'] : '';
            $path = $parsed['path'] ?? '';

            return ($parsed['scheme'] ?? 'rtmp').'://'.$auth.$host.$port.$path;
        }

        if ($this->protocol === 'srt') {
            $separator = str_contains($url, '?') ? '&' : '?';

            return $url.$separator.'passphrase='.rawurlencode($this->password ?? '');
        }

        return $url;
    }
}

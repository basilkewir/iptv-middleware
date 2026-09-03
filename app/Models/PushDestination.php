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
        'is_active',
        'notes',
    ];

    protected $casts = [
        'is_active' => 'boolean',
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
}

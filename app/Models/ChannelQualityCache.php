<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChannelQualityCache extends Model
{
    protected $table = 'channel_quality_cache';

    protected $fillable = [
        'channel_id', 'quality_level', 'resolution_width', 'resolution_height',
        'bitrate', 'video_codec', 'audio_codec', 'frame_rate',
        'scan_timestamp', 'is_verified', 'verified_by',
    ];

    protected $casts = [

            'resolution_width' => 'integer',
            'resolution_height' => 'integer',
            'bitrate' => 'integer',
            'frame_rate' => 'decimal:2',
            'scan_timestamp' => 'datetime',
            'is_verified' => 'boolean',
        
    ];

    public function channel(): BelongsTo
    {
        return $this->belongsTo(Channel::class);
    }
}

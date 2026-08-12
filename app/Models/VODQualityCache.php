<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VODQualityCache extends Model
{
    protected $table = 'vod_quality_cache';

    protected $fillable = [
        'vod_media_id', 'quality_level', 'resolution_width', 'resolution_height',
        'bitrate', 'video_codec', 'audio_codec', 'frame_rate',
        'file_size', 'scan_timestamp', 'is_transcoded', 'source_quality',
    ];

    protected $casts = [
        'resolution_width' => 'integer',
        'resolution_height' => 'integer',
        'bitrate' => 'integer',
        'frame_rate' => 'decimal:2',
        'file_size' => 'integer',
        'scan_timestamp' => 'datetime',
        'is_transcoded' => 'boolean',
    ];

    public function vodMedia(): BelongsTo
    {
        return $this->belongsTo(VODMedia::class, 'vod_media_id');
    }
}

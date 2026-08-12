<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TranscodingProfile extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'slug', 'description', 'profile_type', 'resolution',
        'video_codec', 'bitrate', 'frame_rate', 'keyframe_interval',
        'pixel_format', 'color_space', 'profile', 'level', 'preset',
        'tune', 'crf', 'audio_codec', 'audio_bitrate', 'sample_rate',
        'channels', 'audio_language', 'hls_segment_duration',
        'hls_playlist_type', 'dash_profile', 'gpu_acceleration',
        'gpu_type', 'is_active', 'is_default', 'sort_order',
    ];

    protected $casts = [

            'bitrate' => 'integer', 'frame_rate' => 'integer',
            'keyframe_interval' => 'integer', 'crf' => 'integer',
            'audio_bitrate' => 'integer', 'sample_rate' => 'integer',
            'hls_segment_duration' => 'integer', 'gpu_acceleration' => 'boolean',
            'is_active' => 'boolean', 'is_default' => 'boolean',
            'sort_order' => 'integer',
        
    ];

    public function jobs(): HasMany
    {
        return $this->hasMany(TranscodingJob::class, 'profile_id');
    }

    public function filters(): HasMany
    {
        return $this->hasMany(TranscodingFilter::class, 'profile_id');
    }
}

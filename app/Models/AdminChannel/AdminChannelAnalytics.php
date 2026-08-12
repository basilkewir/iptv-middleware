<?php

namespace App\Models\AdminChannel;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdminChannelAnalytics extends Model
{
    use HasFactory;

    protected $table = 'admin_channel_analytics';

    protected $fillable = [
        'admin_channel_id',
        'date',
        'views',
        'unique_viewers',
        'total_watch_time_seconds',
        'peak_concurrent_viewers',
        'average_watch_duration_seconds',
        'new_subscribers',
        'lost_subscribers',
        'total_subscribers',
        'buffering_events',
        'error_events',
        'average_bitrate',
        'geo_data',
        'device_data',
        'quality_distribution',
    ];

    protected $casts = [
        'date' => 'date',
        'views' => 'integer',
        'unique_viewers' => 'integer',
        'total_watch_time_seconds' => 'integer',
        'peak_concurrent_viewers' => 'integer',
        'average_watch_duration_seconds' => 'integer',
        'new_subscribers' => 'integer',
        'lost_subscribers' => 'integer',
        'total_subscribers' => 'integer',
        'buffering_events' => 'integer',
        'error_events' => 'integer',
        'average_bitrate' => 'decimal:2',
        'geo_data' => 'json',
        'device_data' => 'json',
        'quality_distribution' => 'json',
    ];

    public function adminChannel(): BelongsTo
    {
        return $this->belongsTo(AdminChannel::class, 'admin_channel_id');
    }
}
<?php

namespace App\Models\AdminChannel;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MyChannelSetting extends Model
{
    protected $table = 'my_channel_settings';

    protected $fillable = [
        'channel_id', 'broadcast_mode', 'broadcast_timezone',
        'default_transition', 'transition_duration', 'buffer_between_items',
        'fallback_enabled', 'fallback_playlist_id', 'fallback_after_empty',
        'default_quality', 'auto_adjust_quality',
        'notify_low_content', 'low_content_threshold',
        'notify_broadcast_start', 'notify_broadcast_end',
        'enable_dvr', 'enable_timeshift', 'timeshift_duration',
    ];

    protected $casts = [
        'fallback_enabled' => 'boolean',
        'fallback_after_empty' => 'boolean',
        'auto_adjust_quality' => 'boolean',
        'notify_low_content' => 'boolean',
        'notify_broadcast_start' => 'boolean',
        'notify_broadcast_end' => 'boolean',
        'enable_dvr' => 'boolean',
        'enable_timeshift' => 'boolean',
    ];

    public function channel(): BelongsTo
    {
        return $this->belongsTo(AdminChannel::class, 'channel_id');
    }
}

<?php

namespace App\Models\AdminChannel;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MyChannelPlaylist extends Model
{
    protected $table = 'my_channel_playlist';

    protected $fillable = [
        'channel_id', 'content_id', 'order_index',
        'start_offset', 'end_offset', 'custom_duration',
        'scheduled_start', 'scheduled_end', 'day_of_week', 'time_of_day',
        'transition_type', 'transition_duration', 'override_quality',
        'is_active', 'is_featured',
    ];

    protected $casts = [
        'day_of_week' => 'array',
        'is_active' => 'boolean',
        'is_featured' => 'boolean',
        'scheduled_start' => 'datetime',
        'scheduled_end' => 'datetime',
    ];

    public function channel(): BelongsTo
    {
        return $this->belongsTo(AdminChannel::class, 'channel_id');
    }

    public function content(): BelongsTo
    {
        return $this->belongsTo(MyChannelContent::class, 'content_id');
    }
}

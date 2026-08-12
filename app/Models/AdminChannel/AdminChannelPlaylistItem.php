<?php

namespace App\Models\AdminChannel;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdminChannelPlaylistItem extends Model
{
    use HasFactory;

    protected $table = 'admin_channel_playlist_items';

    protected $fillable = [
        'admin_channel_id',
        'content_type',
        'content_id',
        'content_title',
        'content_description',
        'media_url',
        'thumbnail_url',
        'media_duration',
        'file_size',
        'order_index',
        'start_time_offset',
        'end_time_offset',
        'transition_duration',
        'transition_type',
        'scheduled_start',
        'scheduled_end',
        'is_enabled',
        'metadata',
    ];

    protected $casts = [
        'scheduled_start' => 'datetime',
        'scheduled_end' => 'datetime',
        'is_enabled' => 'boolean',
        'metadata' => 'json',
    ];

    public function adminChannel(): BelongsTo
    {
        return $this->belongsTo(AdminChannel::class, 'admin_channel_id');
    }
}
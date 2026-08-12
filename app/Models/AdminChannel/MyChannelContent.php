<?php

namespace App\Models\AdminChannel;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\User;

class MyChannelContent extends Model
{
    protected $table = 'my_channel_content';

    protected $fillable = [
        'channel_id', 'title', 'description', 'duration',
        'file_name', 'file_path', 'file_size', 'thumbnail_url',
        'uploaded_by', 'uploaded_at', 'last_played_at', 'play_count',
        'quality_level', 'resolution_width', 'resolution_height',
        'bitrate', 'video_codec', 'audio_codec', 'frame_rate',
        'is_active', 'is_featured', 'featured_order', 'is_transcoded',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_featured' => 'boolean',
        'is_transcoded' => 'boolean',
        'uploaded_at' => 'datetime',
        'last_played_at' => 'datetime',
        'frame_rate' => 'float',
    ];

    public function channel(): BelongsTo
    {
        return $this->belongsTo(AdminChannel::class, 'channel_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function playlistEntries(): HasMany
    {
        return $this->hasMany(MyChannelPlaylist::class, 'content_id');
    }
}

<?php

namespace App\Models\AdminChannel;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdminChannelBroadcastLog extends Model
{
    use HasFactory;

    protected $table = 'admin_channel_broadcast_logs';

    protected $fillable = [
        'admin_channel_id',
        'event_type',
        'title',
        'description',
        'stream_url',
        'stream_type',
        'quality',
        'viewers',
        'duration_seconds',
        'metadata',
        'broadcast_start',
        'broadcast_end',
    ];

    protected $casts = [
        'viewers' => 'integer',
        'duration_seconds' => 'integer',
        'metadata' => 'json',
        'broadcast_start' => 'datetime',
        'broadcast_end' => 'datetime',
    ];

    public function adminChannel(): BelongsTo
    {
        return $this->belongsTo(AdminChannel::class, 'admin_channel_id');
    }
}
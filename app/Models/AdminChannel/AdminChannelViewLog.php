<?php

namespace App\Models\AdminChannel;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdminChannelViewLog extends Model
{
    use HasFactory;

    protected $table = 'admin_channel_view_logs';

    protected $fillable = [
        'admin_channel_id',
        'user_id',
        'ip_address',
        'user_agent',
        'device_type',
        'platform',
        'country',
        'region',
        'city',
        'watch_duration_seconds',
        'quality_watched',
        'completed',
        'started_at',
        'ended_at',
    ];

    protected $casts = [
        'watch_duration_seconds' => 'integer',
        'quality_watched' => 'string',
        'completed' => 'boolean',
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
    ];

    public function adminChannel(): BelongsTo
    {
        return $this->belongsTo(AdminChannel::class, 'admin_channel_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'user_id');
    }
}
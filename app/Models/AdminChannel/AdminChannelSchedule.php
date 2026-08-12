<?php

namespace App\Models\AdminChannel;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdminChannelSchedule extends Model
{
    use HasFactory;

    protected $table = 'admin_channel_schedules';

    protected $fillable = [
        'admin_channel_id',
        'title',
        'description',
        'schedule_type',
        'schedule_days',
        'start_time',
        'end_time',
        'status',
        'playlist_ids',
        'overlay_ids',
        'metadata',
    ];

    protected $casts = [
        'schedule_days' => 'json',
        'playlist_ids' => 'json',
        'overlay_ids' => 'json',
        'metadata' => 'json',
        'start_time' => 'datetime',
        'end_time' => 'datetime',
    ];

    public function adminChannel(): BelongsTo
    {
        return $this->belongsTo(AdminChannel::class, 'admin_channel_id');
    }
}
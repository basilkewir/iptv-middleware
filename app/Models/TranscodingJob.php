<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TranscodingJob extends Model
{
    use HasFactory;

    protected $table = 'transcoding_jobs';

    protected $fillable = [
        'profile_id', 'channel_id', 'vod_content_id', 'job_type',
        'status', 'priority', 'input_url', 'output_url', 'progress',
        'error_message', 'logs', 'started_at', 'completed_at', 'scheduled_at',
    ];

    protected $casts = [

            'progress' => 'integer', 'priority' => 'integer',
            'started_at' => 'datetime', 'completed_at' => 'datetime',
            'scheduled_at' => 'datetime',
        
    ];

    public function profile(): BelongsTo
    {
        return $this->belongsTo(TranscodingProfile::class, 'profile_id');
    }

    public function channel(): BelongsTo
    {
        return $this->belongsTo(Channel::class);
    }

    public function vodContent(): BelongsTo
    {
        return $this->belongsTo(VODContent::class, 'vod_content_id');
    }
}

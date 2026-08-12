<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StreamingLog extends Model
{
    use HasFactory;

    protected $table = 'streaming_logs';

    protected $fillable = [
        'user_id',
        'streaming_server_id',
        'channel_id',
        'stream_type',
        'action',
        'stream_url',
        'ip_address',
        'user_agent',
        'session_id',
        'duration',
        'bytes_sent',
        'status',
        'error_message',
    ];

    protected $casts = [

            'duration' => 'integer',
            'bytes_sent' => 'integer',
        
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function streamingServer(): BelongsTo
    {
        return $this->belongsTo(StreamingServer::class);
    }

    public function channel(): BelongsTo
    {
        return $this->belongsTo(Channel::class);
    }
}

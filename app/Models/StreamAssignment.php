<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StreamAssignment extends Model
{
    use HasFactory;

    protected $fillable = [
        'streaming_server_id',
        'channel_id',
        'stream_url',
        'backup_stream_url',
        'is_active',
        'priority',
        'load_balance_weight',
    ];

    protected $casts = [

            'is_active' => 'boolean',
            'priority' => 'integer',
            'load_balance_weight' => 'integer',
        
    ];

    public function streamingServer(): BelongsTo
    {
        return $this->belongsTo(StreamingServer::class);
    }

    public function channel(): BelongsTo
    {
        return $this->belongsTo(Channel::class);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TMDBSyncLog extends Model
{
    protected $table = 'tmdb_sync_logs';

    public $timestamps = false;

    protected $fillable = [
        'operation',
        'tmdb_id',
        'content_type',
        'status',
        'data',
        'error_message',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'data' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];
}

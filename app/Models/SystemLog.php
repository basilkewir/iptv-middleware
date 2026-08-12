<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SystemLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'level',
        'channel',
        'message',
        'context',
        'exception',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [

            'context' => 'array',
            'created_at' => 'datetime',
        
    ];
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QualityDetectionLog extends Model
{
    protected $table = 'quality_detection_logs';

    protected $fillable = [
        'content_type', 'content_id', 'detected_quality', 'detection_method',
        'metadata', 'status', 'error_message', 'started_at', 'completed_at',
    ];

    protected $casts = [

            'metadata' => 'array',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        
    ];
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LicenseValidationLog extends Model
{
    use HasFactory;

    protected $casts = [
        'request_data' => 'array',
        'response_data' => 'array',
        'metadata' => 'array',
    ];

    protected $fillable = [
        'license_id',
        'device_id',
        'validation_type',
        'status',
        'ip_address',
        'user_agent',
        'request_data',
        'response_data',
        'error_message',
        'processing_time',
        'validated_at',
    ];

    const STATUS_SUCCESS = 'success';
    const STATUS_INVALID = 'invalid';
    const STATUS_EXPIRED = 'expired';
    const STATUS_BLOCKED = 'blocked';
    const STATUS_FAILED = 'failed';

    const TYPE_INITIAL = 'initial';
    const TYPE_RENEWAL = 'renewal';
}

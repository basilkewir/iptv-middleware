<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Webhook extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'url', 'event', 'is_active', 'secret',
        'retry_count', 'timeout_seconds', 'headers', 'payload_template',
    ];

    protected $casts = [

            'is_active' => 'boolean',
            'retry_count' => 'integer',
            'timeout_seconds' => 'integer',
            'headers' => 'array',
            'payload_template' => 'array',
        
    ];

    public function deliveries(): HasMany
    {
        return $this->hasMany(WebhookDelivery::class);
    }
}

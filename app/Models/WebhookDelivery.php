<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WebhookDelivery extends Model
{
    use HasFactory;

    protected $table = 'webhook_deliveries';

    protected $fillable = [
        'webhook_id', 'event', 'payload', 'response_headers',
        'response_status', 'response_body', 'status', 'attempt',
        'error_message', 'delivered_at',
    ];

    protected $casts = [

            'payload' => 'array',
            'response_headers' => 'array',
            'response_status' => 'integer',
            'attempt' => 'integer',
            'delivered_at' => 'datetime',
        
    ];

    public function webhook(): BelongsTo
    {
        return $this->belongsTo(Webhook::class);
    }
}

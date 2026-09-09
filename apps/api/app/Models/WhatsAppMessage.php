<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WhatsAppMessage extends Model
{
    use HasFactory;

    protected $table = 'whatsapp_messages';

    protected $fillable = [
        'provider_message_id', 'logical_key', 'provider_idempotency_key', 'direction', 'phone', 'message_type', 'body',
        'payload', 'status', 'error', 'attempts', 'outlet_id', 'order_id', 'claimed_at', 'sent_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'claimed_at' => 'datetime',
            'sent_at' => 'datetime',
            'attempts' => 'integer',
        ];
    }

    public function outlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}

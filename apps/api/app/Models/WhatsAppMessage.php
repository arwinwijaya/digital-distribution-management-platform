<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WhatsAppMessage extends Model
{
    use HasFactory;

    /**
     * Outbound promo blast created by F6 promotion broadcast.
     */
    public const MESSAGE_TYPE_PROMO_BROADCAST = 'promo_broadcast';

    public const MESSAGE_TYPE_ORDER_CONFIRMATION = 'order_confirmation';

    public const MESSAGE_TYPE_CATALOG = 'catalog';

    public const MESSAGE_TYPE_TEXT = 'text';

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

    /**
     * Scope to the outbound promotion broadcast rows. F6 marks its messages
     * with message_type='promo_broadcast'; message_type is a VARCHAR column
     * (see migration 2026_09_16_000006) so no schema change is required.
     */
    public function scopePromoBroadcast(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->where('message_type', self::MESSAGE_TYPE_PROMO_BROADCAST);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceReminder extends Model
{
    use HasFactory;

    public const EVENT_H_MINUS_ONE = 'h_minus_one';
    public const EVENT_OVERDUE = 'overdue';
    public const H_MINUS_ONE = self::EVENT_H_MINUS_ONE;
    public const OVERDUE = self::EVENT_OVERDUE;

    public const PENDING = 'pending';
    public const SENDING = 'sending';
    public const SENT = 'sent';
    public const FAILED = 'failed';
    public const SUPPRESSED = 'suppressed';

    protected $fillable = [
        'invoice_id',
        'event_type',
        'event_date',
        'status',
        'attempts',
        'next_attempt_at',
        'claimed_at',
        'claim_token',
        'sent_at',
        'failed_at',
        'last_error',
        'idempotency_key',
        'provider_message_id',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'event_date' => 'date',
            'next_attempt_at' => 'datetime',
            'claimed_at' => 'datetime',
            'sent_at' => 'datetime',
            'failed_at' => 'datetime',
            'attempts' => 'integer',
            'metadata' => 'array',
        ];
    }

    /** @return array<int, string> */
    public static function statuses(): array
    {
        return [self::PENDING, self::SENT, self::FAILED, self::SUPPRESSED];
    }

    /** @return array<int, string> */
    public static function eventTypes(): array
    {
        return [self::EVENT_H_MINUS_ONE, self::EVENT_OVERDUE];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }
}

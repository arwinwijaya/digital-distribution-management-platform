<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'order_id',
        'outlet_id',
        'status',
        'total_amount',
        'paid_amount',
        'commission_percentage',
        'idempotency_key',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'total_amount' => 'decimal:2',
            'paid_amount' => 'decimal:2',
            'commission_percentage' => 'decimal:2',
        ];
    }

    /**
     * Get the outlet that owns the order.
     */
    public function outlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class);
    }

    /**
     * Get the items for the order.
     */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * Get the status history for the order.
     */
    public function statusHistory(): HasMany
    {
        return $this->hasMany(OrderStatusHistory::class)->orderBy('created_at');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function delivery(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(Delivery::class);
    }

    /**
     * Generate a unique server-generated order ID.
     * Format: ORD-YYYYMMDD-XXXXX (5-digit random suffix)
     */
    public static function generateUniqueOrderId(): string
    {
        do {
            $datePart = now()->format('Ymd');
            $randomPart = strtoupper(substr(uniqid(), -5));
            $orderId = "ORD-{$datePart}-{$randomPart}";
        } while (static::where('order_id', $orderId)->exists());

        return $orderId;
    }

    /**
     * Record a status change in the history.
     */
    public function recordStatus(string $status, ?string $notes = null): OrderStatusHistory
    {
        $this->update(['status' => $status]);

        return $this->statusHistory()->create([
            'status' => $status,
            'notes' => $notes,
        ]);
    }
}

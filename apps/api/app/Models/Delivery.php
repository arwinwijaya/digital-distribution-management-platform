<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use InvalidArgumentException;

class Delivery extends Model
{
    use HasFactory;

    public const ASSIGNED = 'assigned';
    public const IN_PROGRESS = 'in_progress';
    public const DELIVERED = 'delivered';
    public const FAILED = 'failed';

    protected $fillable = [
        'order_id',
        'driver_id',
        'assigned_by_id',
        'status',
        'assigned_at',
        'started_at',
        'delivered_at',
        'failure_reason',
        'recipient_name',
        'proof_of_delivery_url',
        'proof_of_delivery',
        'notes',
        'route_data',
    ];

    protected function casts(): array
    {
        return [
            'assigned_at' => 'datetime',
            'started_at' => 'datetime',
            'delivered_at' => 'datetime',
            'proof_of_delivery' => 'array',
            'route_data' => 'array',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'driver_id');
    }

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by_id');
    }

    public function statusHistory(): HasMany
    {
        return $this->hasMany(DeliveryStatusHistory::class)->oldest();
    }

    public static function canTransition(string $from, string $to): bool
    {
        return match ($from) {
            self::ASSIGNED => in_array($to, [self::IN_PROGRESS, self::FAILED], true),
            self::IN_PROGRESS => in_array($to, [self::DELIVERED, self::FAILED], true),
            default => false,
        };
    }

    public function transitionTo(string $status, User $actor, array $metadata = [], ?string $notes = null): DeliveryStatusHistory
    {
        if (!self::canTransition($this->status, $status)) {
            throw new InvalidArgumentException("Cannot transition delivery from {$this->status} to {$status}.");
        }

        $from = $this->status;
        $this->status = $status;
        if ($status === self::IN_PROGRESS) {
            $this->started_at = now();
        } elseif ($status === self::DELIVERED) {
            $this->delivered_at = now();
        }
        $this->save();

        return $this->statusHistory()->create([
            'actor_id' => $actor->id,
            'from_status' => $from,
            'status' => $status,
            'metadata' => $metadata ?: null,
            'notes' => $notes,
        ]);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RecommendationAction extends Model
{
    use HasFactory;

    public const TYPES = ['draft_order', 'draft_campaign'];

    public const STATUSES = ['draft', 'pending_approval', 'approved', 'rejected', 'executed', 'failed', 'cancelled'];

    protected $fillable = [
        'source_event_id',
        'outlet_id',
        'created_by',
        'approved_by',
        'executed_by',
        'type',
        'status',
        'payload',
        'idempotency_key',
        'idempotency_payload_hash',
        'approved_at',
        'executed_at',
        'rejection_reason',
        'execution_result',
        'method',
        'method_version',
        'fallback',
        'data_sufficiency',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'approved_at' => 'datetime',
            'executed_at' => 'datetime',
            'execution_result' => 'array',
            'fallback' => 'boolean',
            'metadata' => 'array',
        ];
    }

    public function sourceEvent(): BelongsTo
    {
        return $this->belongsTo(RecommendationEvent::class, 'source_event_id');
    }

    public function outlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function executor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'executed_by');
    }

    public function events(): HasMany
    {
        return $this->hasMany(RecommendationActionEvent::class);
    }
}

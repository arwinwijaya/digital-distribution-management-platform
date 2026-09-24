<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReplenishmentPlan extends Model
{
    use HasFactory;

    public const STATUSES = ['draft', 'approved', 'rejected', 'executed', 'failed', 'cancelled'];

    protected $fillable = [
        'supplier_id',
        'created_by',
        'approved_by',
        'status',
        'window_start',
        'window_end',
        'approved_at',
        'executed_at',
        'execution_result',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'window_start' => 'date',
            'window_end' => 'date',
            'approved_at' => 'datetime',
            'executed_at' => 'datetime',
            'execution_result' => 'array',
            'metadata' => 'array',
        ];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(ReplenishmentPlanItem::class);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReplenishmentPlanItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'replenishment_plan_id',
        'product_id',
        'reorder_quantity',
        'data_sufficiency',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'reorder_quantity' => 'decimal:3',
            'metadata' => 'array',
        ];
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(ReplenishmentPlan::class, 'replenishment_plan_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}

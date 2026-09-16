<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Promotion extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'discount_type',
        'discount_value',
        'max_discount',
        'product_id',
        'min_order',
        'start_date',
        'end_date',
        'is_active',
        'broadcast_at',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'discount_value' => 'decimal:2',
            'max_discount'   => 'decimal:2',
            'min_order'      => 'decimal:2',
            'start_date'     => 'date',
            'end_date'       => 'date',
            'is_active'      => 'boolean',
            'broadcast_at'   => 'datetime',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /**
     * Determine if this promo is currently valid (today within date range).
     */
    public function isCurrentlyValid(): bool
    {
        $today = now()->startOfDay();

        return $this->is_active
            && $today->lte($this->end_date)
            && $today->gte($this->start_date);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Product extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'supplier_id',
        'name',
        'description',
        'price',
        'sku',
        'stock_quantity',
        'category',
        'is_active',
    ];

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /**
     * Scope products that can currently be purchased.
     *
     * Supplier-less products remain eligible for legacy compatibility. Products
     * owned by a supplier require the canonical active subscription status.
     */
    public function scopePurchasable(Builder $query): Builder
    {
        return $query
            ->where('products.is_active', true)
            ->where(function (Builder $supplier) {
                $supplier->whereNull('products.supplier_id')
                    ->orWhereHas('supplier', fn (Builder $supplierQuery) => $supplierQuery->where('subscription_status', 'active'));
            });
    }

    /**
     * Check purchase eligibility for an already-loaded product.
     * Eager-load supplier when checking multiple products to avoid N+1 queries.
     */
    public function isPurchasable(): bool
    {
        return $this->is_active
            && ($this->supplier_id === null || $this->supplier?->subscription_status === 'active');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'stock_quantity' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Scope: filter by search term on name
     */
    public function scopeSearch($query, ?string $search)
    {
        if ($search) {
            return $query->where('name', 'like', "%{$search}%");
        }

        return $query;
    }
}

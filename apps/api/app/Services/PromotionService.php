<?php

namespace App\Services;

use App\Models\Promotion;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class PromotionService
{
    /**
     * Create a promotion with overlap check + actor audit.
     */
    public function create(array $validated, int $actorId): Promotion
    {
        return DB::transaction(function () use ($validated, $actorId): Promotion {
            $this->assertNoOverlappingPromotion(
                $validated['product_id'] ?? null,
                $validated['start_date'],
                $validated['end_date']
            );

            return Promotion::create(array_merge($validated, ['created_by' => $actorId]));
        });
    }

    /**
     * Update a promotion. Broadcast promos are immutable (422).
     */
    public function update(Promotion $promo, array $validated): Promotion
    {
        if ($promo->broadcast_at !== null) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'promotion_id' => 'Broadcast promotions cannot be edited.',
            ]);
        }

        return DB::transaction(function () use ($promo, $validated): Promotion {
            $productId = $validated['product_id'] ?? $promo->product_id;
            $startDate = $validated['start_date'] ?? $promo->start_date;
            $endDate   = $validated['end_date'] ?? $promo->end_date;

            $this->assertNoOverlappingPromotion($productId, $startDate, $endDate, $promo->id);

            $promo->update($validated);

            return $promo->fresh();
        });
    }

    /**
     * Delete a promotion. Broadcast promos cannot be deleted (422).
     */
    public function delete(Promotion $promo): void
    {
        if ($promo->broadcast_at !== null) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'promotion_id' => 'Broadcast promotions cannot be deleted.',
            ]);
        }

        $promo->delete();
    }

    /**
     * Single promo per product per time: overlapping dates → 422.
     * Timezone: dates compared as calendar days; start inclusive, end inclusive.
     *
     * Overlap rule: newStart <= existingEnd AND newEnd >= existingStart.
     * Only consider active promos (is_active=true).
     */
    public function assertNoOverlappingPromotion(
        ?int $productId,
        string|\DateTimeInterface $startDate,
        string|\DateTimeInterface $endDate,
        ?int $excludeId = null,
    ): void {
        // Global promos (product_id=null) do not participate in per-product overlap.
        if ($productId === null) {
            return;
        }

        $query = Promotion::query()
            ->where('product_id', $productId)
            ->where('is_active', true)
            ->where('start_date', '<=', $this->toDate($endDate))
            ->where('end_date', '>=', $this->toDate($startDate));

        if ($excludeId !== null) {
            $query->where('id', '!=', $excludeId);
        }

        if ($query->lockForUpdate()->exists()) {
            throw ValidationException::withMessages([
                'dates' => 'An overlapping promotion already exists for this product.',
            ]);
        }
    }

    /**
     * Calculate discount for a given pre-discount subtotal.
     *
     * Returns 0 when min_order threshold is not met.
     */
    public function calculateDiscount(Promotion $promo, float $subtotal): float
    {
        if ((float) $promo->min_order > 0 && $subtotal < (float) $promo->min_order) {
            return 0.0;
        }

        if (! $promo->is_active) {
            return 0.0;
        }

        if ($promo->discount_type === 'percentage') {
            $discount = $subtotal * ((float) $promo->discount_value / 100);
            if ($promo->max_discount !== null) {
                $discount = min($discount, (float) $promo->max_discount);
            }
        } else {
            $discount = (float) $promo->discount_value;
        }

        // Discount never exceeds subtotal
        return round(min($discount, $subtotal), 2);
    }

    /**
     * Check if promo is valid for a specific date (inclusive range).
     */
    public function isValidOn(Promotion $promo, \DateTimeInterface|string $date): bool
    {
        $d = $this->toDate($date);

        // @phpstan-ignore-next-line model date casts
        return $promo->is_active
            && $d >= $this->toDate($promo->start_date)
            && $d <= $this->toDate($promo->end_date);
    }

    private function toDate(\DateTimeInterface|string $value): string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        return substr((string) $value, 0, 10);
    }
}

<?php

namespace App\Services;

use App\Models\ReplenishmentPlan;
use App\Models\ReplenishmentPlanItem;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Read-only service that converts stock-planning signals into
 * per-supplier draft replenishment plans with items.
 *
 * Does NOT mutate stock. Does NOT call OrderCreationService or any
 * external supplier API. Idempotent per (window_start, window_end, supplier_id).
 */
class ReplenishmentService
{
    public function __construct(
        private ?StockPlanningService $stockPlanning = null,
        private ?ActiveDataSnapshotReader $reader = null,
    ) {
        $this->stockPlanning ??= app(StockPlanningService::class);
        $this->reader ??= app(ActiveDataSnapshotReader::class);
    }

    /**
     * Generate draft replenishment plans for the given window.
     *
     * @param  array{start: string, end: string, timezone?: string}  $window
     * @return Collection<int, ReplenishmentPlan>
     */
    public function generate(array $window, ?User $actor = null): Collection
    {
        $start = (string) $window['start'];
        $end = (string) $window['end'];

        $signals = $this->readSignals($window);

        if (empty($signals)) {
            return new Collection();
        }

        $signalsWithReorderOrInsufficient = array_filter($signals, function (array $s): bool {
            $hasReorder = ($s['status'] ?? '') === 'reorder' && (($s['reorder_quantity'] ?? 0) > 0);
            $isInsufficient = ($s['status'] ?? '') === 'insufficient-data' || ($s['supplier_id'] ?? null) === null;
            return $hasReorder || $isInsufficient;
        });

        if (empty($signalsWithReorderOrInsufficient)) {
            return new Collection();
        }

        $bySupplier = [];
        foreach ($signalsWithReorderOrInsufficient as $signal) {
            // PHP casts a null array key to ""; keep an explicit sentinel.
            $supplierId = $signal['supplier_id'] ?? null;
            $key = $supplierId === null ? '__null__' : (string) (int) $supplierId;
            $bySupplier[$key] ??= [];
            $bySupplier[$key][] = $signal;
        }

        $plans = new Collection();
        foreach ($bySupplier as $key => $supplierSignals) {
            $supplierId = $key === '__null__' ? null : (int) $key;
            $plan = $this->createOrFindPlan($start, $end, $supplierId, $actor);
            $this->syncItems($plan, $supplierSignals);
            $plans->push($plan->load('items'));
        }

        return $plans;
    }

    /**
     * Read signals from the active published snapshot if available,
     * otherwise fall back to computing them via StockPlanningService.
     *
     * @return array<string, array{product_id: int, supplier_id: ?int, reorder_quantity: int, status: string, ...}>
     */
    private function readSignals(array $window): array
    {
        $section = $this->reader->section('stock');
        if (! empty($section)) {
            return array_map(function (array $row): array {
                $payload = $row['payload'] ?? [];
                return array_merge($payload, [
                    'supplier_id' => $payload['supplier_id'] ?? null,
                    'reorder_quantity' => $payload['reorder_quantity'] ?? 0,
                    'status' => $payload['status'] ?? 'ok',
                ]);
            }, $section);
        }

        $produced = $this->stockPlanning->produce($window);
        return array_map(function (array $s): array {
            return array_merge($s, [
                'supplier_id' => $s['supplier_id'] ?? null,
                'reorder_quantity' => $s['reorder_quantity'] ?? 0,
                'status' => $s['status'] ?? 'ok',
            ]);
        }, $produced);
    }

    private function createOrFindPlan(string $start, string $end, ?int $supplierId, ?User $actor): ReplenishmentPlan
    {
        return DB::transaction(function () use ($start, $end, $supplierId, $actor) {
            $existing = ReplenishmentPlan::whereDate('window_start', $start)
                ->whereDate('window_end', $end)
                ->where(function ($q) use ($supplierId) {
                    if ($supplierId === null) {
                        $q->whereNull('supplier_id');
                    } else {
                        $q->where('supplier_id', $supplierId);
                    }
                })
                ->first();

            if ($existing) {
                return $existing;
            }

            return ReplenishmentPlan::create([
                'supplier_id' => $supplierId,
                'created_by' => $actor?->id,
                'status' => 'draft',
                'window_start' => $start,
                'window_end' => $end,
            ]);
        });
    }

    private function syncItems(ReplenishmentPlan $plan, array $signals): void
    {
        DB::transaction(function () use ($plan, $signals) {
            $existingProductIds = $plan->items->pluck('product_id')->all();
            $incomingProductIds = array_column($signals, 'product_id');
            $toRemove = array_diff($existingProductIds, $incomingProductIds);

            if (! empty($toRemove)) {
                ReplenishmentPlanItem::where('replenishment_plan_id', $plan->id)
                    ->whereIn('product_id', $toRemove)
                    ->delete();
            }

            foreach ($signals as $signal) {
                $productId = (int) $signal['product_id'];
                $reorderQuantity = max(0, (int) ($signal['reorder_quantity'] ?? 0));
                $status = $signal['status'] ?? 'ok';
                $supplierId = $signal['supplier_id'] ?? null;

                $dataSufficiency = 'sufficient';
                if ($status === 'insufficient-data' || $supplierId === null) {
                    $dataSufficiency = 'insufficient';
                    $reorderQuantity = 0;
                } elseif ($status === 'ok') {
                    $dataSufficiency = 'sufficient';
                    $reorderQuantity = 0;
                }

                ReplenishmentPlanItem::updateOrCreate(
                    ['replenishment_plan_id' => $plan->id, 'product_id' => $productId],
                    [
                        'reorder_quantity' => $reorderQuantity,
                        'data_sufficiency' => $dataSufficiency,
                        'metadata' => ['status' => $status, 'source' => 'stock_planning'],
                    ]
                );
            }
        });
    }
}
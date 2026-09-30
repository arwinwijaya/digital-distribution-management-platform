<?php

namespace App\Services;

use App\Models\ReplenishmentPlan;
use App\Models\ReplenishmentPlanItem;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Converts stock-planning signals into draft replenishment plans and manages
 * approval/execution. Execution records draft PO metadata only; it never mutates
 * stock and never calls an external supplier/order service.
 */
class ReplenishmentService
{
    public function __construct(
        private ?StockPlanningService $stockPlanning = null,
        private ?ActiveDataSnapshotReader $reader = null,
        private ?OperationalEventService $events = null,
    ) {
        $this->stockPlanning ??= app(StockPlanningService::class);
        $this->reader ??= app(ActiveDataSnapshotReader::class);
        $this->events ??= app(OperationalEventService::class);
    }

    /** @return Collection<int, ReplenishmentPlan> */
    public function generate(array $window, ?User $actor = null): Collection
    {
        $signals = $this->replenishmentSignals($this->readSignals($window));
        if (empty($signals)) {
            return new Collection();
        }

        $plans = new Collection();
        foreach ($this->groupSignalsBySupplier($signals) as $key => $supplierSignals) {
            $supplierId = $key === '__null__' ? null : (int) $key;
            $plan = $this->createOrFindPlan($window['start'], $window['end'], $supplierId, $actor);
            $this->syncItems($plan, $supplierSignals);
            $plans->push($plan->load('items'));
        }

        return $plans;
    }

    /** @return array{plan: ReplenishmentPlan, replay: bool} */
    public function approve(int $id, User $actor): array
    {
        return $this->withLockedPlan($id, function (ReplenishmentPlan $plan) use ($actor): array {
            if ($plan->status === 'approved') {
                return ['plan' => $plan->load('items'), 'replay' => true];
            }
            if ($plan->status !== 'draft') {
                throw ValidationException::withMessages(['status' => "Cannot approve plan with status '{$plan->status}'."]);
            }

            $plan->forceFill(['status' => 'approved', 'approved_by' => $actor->id, 'approved_at' => now()])->save();
            $this->audit($plan, 'replenishment.approved', $actor->id, ['logical_key' => null]);

            return ['plan' => $plan->load('items'), 'replay' => false];
        });
    }

    /** @return array{plan: ReplenishmentPlan, replay: bool} */
    public function execute(int $id, User $actor, array $payload): array
    {
        $logicalKey = trim((string) ($payload['logical_key'] ?? ''));
        if ($logicalKey === '') {
            throw ValidationException::withMessages(['logical_key' => 'The logical_key field is required.']);
        }

        return $this->withLockedPlan($id, function (ReplenishmentPlan $plan) use ($actor, $logicalKey): array {
            $plan->load(['items.product', 'supplier']);
            $hash = $this->payloadHash($plan);
            if ($plan->status === 'executed') {
                return $this->executionReplay($plan, $logicalKey, $hash);
            }
            if ($plan->status !== 'approved') {
                throw ValidationException::withMessages(['status' => "Cannot execute plan with status '{$plan->status}'. Only approved plans may be executed."]);
            }

            $invalidItems = $plan->items->filter(fn ($item) => $item->data_sufficiency !== 'sufficient' || (float) $item->reorder_quantity <= 0);
            if ($invalidItems->isNotEmpty()) {
                $invalidProductIds = $invalidItems->pluck('product_id')->map(fn ($id) => (int) $id)->values()->all();
                $plan->forceFill([
                    'status' => 'failed',
                    'executed_at' => now(),
                    'execution_result' => [
                        'logical_key' => $logicalKey,
                        'payload_hash' => $hash,
                        'executed_by' => $actor->id,
                        'error' => 'Insufficient or invalid items for execution.',
                        'invalid_items' => $invalidProductIds,
                    ],
                ])->save();
                $this->audit($plan, 'replenishment.failed', $actor->id, [
                    'logical_key' => $logicalKey,
                    'invalid_items' => $invalidProductIds,
                ]);

                return ['plan' => $plan->load('items'), 'replay' => false];
            }

            $result = $this->executionResult($plan, $logicalKey, $hash, $actor->id);
            $plan->forceFill(['status' => 'executed', 'executed_at' => now(), 'execution_result' => $result])->save();
            $this->audit($plan, 'replenishment.executed', $actor->id, ['logical_key' => $logicalKey]);

            return ['plan' => $plan->load('items'), 'replay' => false];
        });
    }

    /** @return array<string, array<string, mixed>> */
    private function readSignals(array $window): array
    {
        $section = $this->reader->section('stock');
        if (! empty($section)) {
            return array_map(fn (array $row) => array_merge($row['payload'] ?? [], [
                'supplier_id' => $row['payload']['supplier_id'] ?? null,
                'reorder_quantity' => $row['payload']['reorder_quantity'] ?? 0,
                'status' => $row['payload']['status'] ?? 'ok',
            ]), $section);
        }

        return array_map(fn (array $s) => array_merge($s, [
            'supplier_id' => $s['supplier_id'] ?? null,
            'reorder_quantity' => $s['reorder_quantity'] ?? 0,
            'status' => $s['status'] ?? 'ok',
        ]), $this->stockPlanning->produce($window));
    }

    private function createOrFindPlan(string $start, string $end, ?int $supplierId, ?User $actor): ReplenishmentPlan
    {
        return DB::transaction(function () use ($start, $end, $supplierId, $actor) {
            $query = ReplenishmentPlan::whereDate('window_start', $start)->whereDate('window_end', $end);
            $query->where(fn ($q) => $supplierId === null ? $q->whereNull('supplier_id') : $q->where('supplier_id', $supplierId));
            $existing = $query->first();
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
        DB::transaction(function () use ($plan, $signals): void {
            $this->removeMissingItems($plan, array_column($signals, 'product_id'));
            foreach ($signals as $signal) {
                $this->upsertItemFromSignal($plan, $signal);
            }
        });
    }

    private function removeMissingItems(ReplenishmentPlan $plan, array $incomingProductIds): void
    {
        $toRemove = array_diff($plan->items->pluck('product_id')->all(), $incomingProductIds);
        if (! empty($toRemove)) {
            ReplenishmentPlanItem::where('replenishment_plan_id', $plan->id)->whereIn('product_id', $toRemove)->delete();
        }
    }

    private function upsertItemFromSignal(ReplenishmentPlan $plan, array $signal): void
    {
        [$quantity, $sufficiency] = $this->quantityAndSufficiency($signal);
        ReplenishmentPlanItem::updateOrCreate(
            ['replenishment_plan_id' => $plan->id, 'product_id' => (int) $signal['product_id']],
            ['reorder_quantity' => $quantity, 'data_sufficiency' => $sufficiency, 'metadata' => ['status' => $signal['status'] ?? 'ok', 'source' => 'stock_planning']]
        );
    }

    /** @return array{0: int, 1: string} */
    private function quantityAndSufficiency(array $signal): array
    {
        $quantity = max(0, (int) ($signal['reorder_quantity'] ?? 0));
        if (($signal['status'] ?? 'ok') === 'insufficient-data' || ($signal['supplier_id'] ?? null) === null) {
            return [0, 'insufficient'];
        }
        return [($signal['status'] ?? 'ok') === 'ok' ? 0 : $quantity, 'sufficient'];
    }

    /** @return array<string, array<int, array<string, mixed>>> */
    private function groupSignalsBySupplier(array $signals): array
    {
        $grouped = [];
        foreach ($signals as $signal) {
            $key = ($signal['supplier_id'] ?? null) === null ? '__null__' : (string) (int) $signal['supplier_id'];
            $grouped[$key][] = $signal;
        }
        return $grouped;
    }

    private function replenishmentSignals(array $signals): array
    {
        return array_filter($signals, fn (array $s) => (($s['status'] ?? '') === 'reorder' && (($s['reorder_quantity'] ?? 0) > 0)) || ($s['status'] ?? '') === 'insufficient-data' || ($s['supplier_id'] ?? null) === null);
    }

    /** @return array{plan: ReplenishmentPlan, replay: bool} */
    private function withLockedPlan(int $id, callable $callback): array
    {
        for ($attempt = 0; $attempt < 3; $attempt++) {
            try {
                return DB::transaction(function () use ($id, $callback): array {
                    $plan = ReplenishmentPlan::whereKey($id)->lockForUpdate()->first();
                    if (! $plan) {
                        throw (new ModelNotFoundException())->setModel(ReplenishmentPlan::class, [$id]);
                    }
                    return $callback($plan);
                });
            } catch (QueryException $e) {
                if ($attempt === 2) {
                    throw $e;
                }
                usleep(10000 * ($attempt + 1));
            }
        }
        throw new \LogicException('Unable to lock replenishment plan.');
    }

    /** @return array{plan: ReplenishmentPlan, replay: bool} */
    private function executionReplay(ReplenishmentPlan $plan, string $logicalKey, string $hash): array
    {
        $result = $plan->execution_result ?? [];
        if (($result['logical_key'] ?? null) === $logicalKey && ($result['payload_hash'] ?? null) === $hash) {
            return ['plan' => $plan->load('items'), 'replay' => true];
        }
        throw ValidationException::withMessages(['logical_key' => 'This idempotency key was already used with a different payload.']);
    }

    private function payloadHash(ReplenishmentPlan $plan): string
    {
        $items = $plan->items->map(fn ($item) => [
            'data_sufficiency' => $item->data_sufficiency,
            'product_id' => (int) $item->product_id,
            'reorder_quantity' => (float) $item->reorder_quantity,
        ])->sortBy('product_id')->values()->all();

        return hash('sha256', json_encode(['supplier_id' => $plan->supplier_id, 'items' => $items], JSON_THROW_ON_ERROR));
    }

    private function executionResult(ReplenishmentPlan $plan, string $logicalKey, string $hash, int $actorId): array
    {
        return ['logical_key' => $logicalKey, 'payload_hash' => $hash, 'executed_by' => $actorId, 'purchase_orders' => [$this->po($plan)]];
    }

    private function po(ReplenishmentPlan $plan): array
    {
        return [
            'reference' => 'PO-REPL-'.$plan->id.'-'.substr(hash('sha256', $plan->id.':'.($plan->updated_at?->timestamp ?? time())), 0, 8),
            'supplier_id' => $plan->supplier_id,
            'supplier_name' => $plan->supplier?->name,
            'items' => $plan->items->map(fn ($item) => [
                'product_id' => (int) $item->product_id,
                'product_name' => $item->product?->name,
                'reorder_quantity' => (float) $item->reorder_quantity,
            ])->values()->all(),
        ];
    }

    private function audit(ReplenishmentPlan $plan, string $action, ?int $actorId, array $extra = []): void
    {
        $this->events->record('replenishment:'.$plan->id.':'.$action.':'.uniqid(), 'replenishment-plans/'.$plan->id, $action, $actorId, 200, null, null, array_merge(['plan_id' => $plan->id], $extra));
    }
}

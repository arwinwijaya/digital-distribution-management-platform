<?php

namespace Tests\Feature;

use App\Models\AbExperiment;
use App\Models\AbExperimentAssignment;
use App\Models\ForecastCalibration;
use App\Models\Outlet;
use App\Models\Product;
use App\Models\RecommendationAction;
use App\Models\RecommendationActionEvent;
use App\Models\ReplenishmentPlan;
use App\Models\ReplenishmentPlanItem;
use App\Models\RevenueLiftSnapshot;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class Phase9MigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_phase9_schema_supports_models_and_relations(): void
    {
        foreach ($this->phase9Tables() as $table) {
            $this->assertTrue(Schema::hasTable($table), "Missing {$table} table");
        }

        $this->assertTrue(Schema::hasColumns('recommendation_actions', [
            'idempotency_key', 'status', 'type', 'approved_by', 'approved_at',
            'execution_result', 'rejection_reason',
        ]));
        $this->assertTrue(Schema::hasColumns('recommendation_action_events', [
            'recommendation_action_id', 'event_type', 'actor_id', 'metadata',
        ]));
        $this->assertTrue(Schema::hasColumns('replenishment_plans', [
            'window_start', 'window_end', 'supplier_id', 'status',
        ]));
        $this->assertTrue(Schema::hasColumns('replenishment_plan_items', [
            'replenishment_plan_id', 'product_id', 'reorder_quantity',
        ]));
        $this->assertTrue(Schema::hasColumns('forecast_calibrations', [
            'dimension_key', 'bias_factor', 'seasonality_factor', 'method_version',
        ]));
        $this->assertTrue(Schema::hasColumns('ab_experiments', ['experiment_key', 'status']));
        $this->assertTrue(Schema::hasColumns('ab_experiment_assignments', [
            'experiment_id', 'subject_key', 'bucket',
        ]));
        $this->assertTrue(Schema::hasColumns('revenue_lift_snapshots', [
            'experiment_id', 'uplift', 'status', 'method_version',
        ]));

        $actor = User::factory()->admin()->create();
        $outlet = Outlet::factory()->create();
        $product = Product::factory()->create();
        $supplier = Supplier::factory()->active()->create();

        $action = RecommendationAction::factory()->create([
            'outlet_id' => $outlet->id,
            'created_by' => $actor->id,
            'approved_by' => $actor->id,
            'status' => 'approved',
        ]);
        $event = RecommendationActionEvent::factory()->create([
            'recommendation_action_id' => $action->id,
            'actor_id' => $actor->id,
        ]);
        $this->assertTrue($action->events->contains($event));
        $this->assertTrue($event->action->is($action));
        $this->assertTrue($action->outlet->is($outlet));
        $this->assertTrue($action->creator->is($actor));
        $this->assertTrue($action->approver->is($actor));

        $plan = ReplenishmentPlan::factory()->create(['supplier_id' => $supplier->id]);
        $item = ReplenishmentPlanItem::factory()->create([
            'replenishment_plan_id' => $plan->id,
            'product_id' => $product->id,
        ]);
        $this->assertTrue($plan->items->contains($item));
        $this->assertTrue($item->plan->is($plan));
        $this->assertTrue($item->product->is($product));
        $this->assertTrue($plan->supplier->is($supplier));

        $calibration = ForecastCalibration::factory()->create();
        $experiment = AbExperiment::factory()->create();
        $assignment = AbExperimentAssignment::factory()->create([
            'experiment_id' => $experiment->id,
        ]);
        $snapshot = RevenueLiftSnapshot::factory()->create([
            'experiment_id' => $experiment->id,
            'uplift' => '0.125000',
        ]);

        $this->assertSame('1.000000', (string) $calibration->fresh()->bias_factor);
        $this->assertTrue($experiment->assignments->contains($assignment));
        $this->assertTrue($assignment->experiment->is($experiment));
        $this->assertTrue($snapshot->experiment->is($experiment));
        $this->assertSame('0.125000', (string) $snapshot->fresh()->uplift);
    }

    public function test_phase9_unique_keys_are_enforced(): void
    {
        RecommendationAction::factory()->create(['idempotency_key' => 'phase9-dup-key']);
        $this->assertUniqueViolation(
            fn () => RecommendationAction::factory()->create(['idempotency_key' => 'phase9-dup-key']),
            'Duplicate recommendation_actions.idempotency_key was accepted.',
        );

        $supplier = Supplier::factory()->create();
        $window = ['window_start' => '2026-09-01', 'window_end' => '2026-09-30'];
        ReplenishmentPlan::factory()->create(['supplier_id' => $supplier->id] + $window);
        $this->assertUniqueViolation(
            fn () => ReplenishmentPlan::factory()->create(['supplier_id' => $supplier->id] + $window),
            'Duplicate replenishment plan window+supplier was accepted.',
        );

        ForecastCalibration::factory()->create(['dimension_key' => 'phase9-dup-dim']);
        $this->assertUniqueViolation(
            fn () => ForecastCalibration::factory()->create(['dimension_key' => 'phase9-dup-dim']),
            'Duplicate forecast_calibrations.dimension_key was accepted.',
        );

        $experiment = AbExperiment::factory()->create(['experiment_key' => 'phase9-dup-exp']);
        $this->assertUniqueViolation(
            fn () => AbExperiment::factory()->create(['experiment_key' => 'phase9-dup-exp']),
            'Duplicate ab_experiments.experiment_key was accepted.',
        );

        $assignment = ['experiment_id' => $experiment->id, 'subject_key' => 'subject-dup'];
        AbExperimentAssignment::factory()->create($assignment);
        $this->assertUniqueViolation(
            fn () => AbExperimentAssignment::factory()->create($assignment),
            'Duplicate ab_experiment_assignments (experiment, subject) was accepted.',
        );
    }

    public function test_phase9_migrations_are_reversible(): void
    {
        // Proves the Phase 9 migrations created these tables (fails pre-implementation).
        foreach ($this->phase9Tables() as $table) {
            $this->assertTrue(Schema::hasTable($table), "Missing {$table} table");
        }

        $this->assertSame(0, Artisan::call('migrate:rollback', ['--step' => 8, '--force' => true]));

        foreach ($this->phase9Tables() as $table) {
            $this->assertFalse(Schema::hasTable($table), "{$table} was not rolled back");
        }

        // Rollback must not touch pre-existing tables.
        $this->assertTrue(Schema::hasTable('outlets'));
        $this->assertTrue(Schema::hasTable('products'));
        $this->assertTrue(Schema::hasTable('suppliers'));
        $this->assertTrue(Schema::hasTable('orders'));
        $this->assertTrue(Schema::hasTable('recommendation_events'));
        $this->assertTrue(Schema::hasTable('forecast_actuals'));
    }

    private function assertUniqueViolation(\Closure $callback, string $message): void
    {
        try {
            $callback();
            $this->fail($message);
        } catch (QueryException) {
            $this->addToAssertionCount(1);
        }
    }

    /**
     * @return list<string>
     */
    private function phase9Tables(): array
    {
        return [
            'recommendation_actions',
            'recommendation_action_events',
            'replenishment_plans',
            'replenishment_plan_items',
            'forecast_calibrations',
            'ab_experiments',
            'ab_experiment_assignments',
            'revenue_lift_snapshots',
        ];
    }
}

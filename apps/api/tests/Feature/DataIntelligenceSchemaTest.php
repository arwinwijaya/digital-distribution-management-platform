<?php

namespace Tests\Feature;

use App\Models\DataMetricDefinition;
use App\Models\DataPipelineRun;
use App\Models\DataSnapshot;
use App\Models\DataSnapshotValue;
use App\Models\Outlet;
use App\Models\RecommendationEvent;
use App\Models\Supplier;
use App\Models\Territory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DataIntelligenceSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_schema_exposes_data_intelligence_constraints(): void
    {
        $this->assertTrue(Schema::hasTable('territories'));
        $this->assertTrue(Schema::hasColumn('outlets', 'territory_id'));
        $this->assertTrue(Schema::hasColumn('suppliers', 'lead_time_days'));
        $this->assertTrue(Schema::hasTable('data_pipeline_runs'));
        $this->assertTrue(Schema::hasTable('data_metric_definitions'));
        $this->assertTrue(Schema::hasTable('data_snapshots'));
        $this->assertTrue(Schema::hasTable('data_snapshot_values'));
        $this->assertTrue(Schema::hasTable('recommendation_events'));

        $territory = Territory::create(['name' => 'Jakarta Selatan', 'code' => 'JKS']);
        $outlet = Outlet::factory()->create(['territory_id' => $territory->id]);
        $supplier = Supplier::factory()->create(['lead_time_days' => 5]);
        $run = DataPipelineRun::create([
            'run_uuid' => '11111111-1111-4111-8111-111111111111',
            'status' => 'completed',
            'pipeline_version' => 'v1',
            'window_start' => '2026-09-01',
            'window_end' => '2026-09-14',
        ]);
        $definition = DataMetricDefinition::create([
            'key' => 'sales_total',
            'name' => 'Sales Total',
            'method_version' => 'v1',
        ]);
        $snapshot = DataSnapshot::create([
            'run_id' => $run->id,
            'snapshot_uuid' => '22222222-2222-4222-8222-222222222222',
            'version' => 1,
            'status' => 'staged',
            'window_start' => '2026-09-01',
            'window_end' => '2026-09-14',
            'lineage' => ['source' => 'orders'],
        ]);
        $value = DataSnapshotValue::create([
            'snapshot_id' => $snapshot->id,
            'metric_definition_id' => $definition->id,
            'section' => 'geographic',
            'dimension' => ['territory_id' => $territory->id],
            'value' => ['amount' => '100.00'],
        ]);
        $event = RecommendationEvent::create([
            'event_uuid' => '55555555-5555-4555-8555-555555555555',
            'event_type' => 'displayed',
            'outlet_id' => $outlet->id,
            'product_id' => null,
            'occurred_at' => now(),
            'metadata' => ['snapshot_id' => $snapshot->id],
        ]);

        $this->assertSame($territory->id, $outlet->fresh()->territory->id);
        $this->assertSame(5, $supplier->fresh()->lead_time_days);
        $this->assertSame($run->id, $snapshot->fresh()->run->id);
        $this->assertSame($snapshot->id, $value->fresh()->snapshot->id);
        $this->assertSame($definition->id, $value->fresh()->metricDefinition->id);
        $this->assertSame($event->event_uuid, RecommendationEvent::query()->value('event_uuid'));

        $this->expectException(\Throwable::class);
        RecommendationEvent::create([
            'event_uuid' => '55555555-5555-4555-8555-555555555555',
            'event_type' => 'clicked',
            'outlet_id' => $outlet->id,
            'occurred_at' => now(),
        ]);
    }

    public function test_snapshot_immutability_and_one_active_run_publication_uniqueness_are_database_enforced(): void
    {
        $run = DataPipelineRun::create([
            'run_uuid' => '11111111-1111-4111-8111-111111111111',
            'status' => 'running',
            'pipeline_version' => 'v1',
            'window_start' => '2026-09-01',
            'window_end' => '2026-09-14',
        ]);
        $snapshot = DataSnapshot::create([
            'run_id' => $run->id,
            'snapshot_uuid' => '22222222-2222-4222-8222-222222222222',
            'version' => 1,
            'status' => 'published',
            'is_active' => true,
            'window_start' => '2026-09-01',
            'window_end' => '2026-09-14',
        ]);
        $definition = DataMetricDefinition::create([
            'key' => 'immutable_metric',
            'name' => 'Immutable Metric',
            'method_version' => 'v1',
        ]);

        $this->assertDatabaseRejects(fn () => DB::table('data_snapshots')
            ->where('id', $snapshot->id)
            ->update(['window_start' => '2026-09-02']));
        $this->assertSame('2026-09-01', $snapshot->fresh()->window_start->toDateString());

        $this->assertDatabaseRejects(fn () => DB::table('data_snapshots')
            ->where('id', $snapshot->id)
            ->delete());
        $this->assertDatabaseHas('data_snapshots', ['id' => $snapshot->id]);

        $this->assertDatabaseRejects(fn () => DB::table('data_pipeline_runs')->insert([
            'run_uuid' => '33333333-3333-4333-8333-333333333333',
            'status' => 'running',
            'pipeline_version' => 'v1',
            'window_start' => '2026-09-01',
            'window_end' => '2026-09-14',
            'timezone' => 'Asia/Jakarta',
            'created_at' => now(),
            'updated_at' => now(),
        ]));

        $publicationRun = DataPipelineRun::create([
            'run_uuid' => '88888888-8888-4888-8888-888888888888',
            'status' => 'completed',
            'pipeline_version' => 'v1',
            'window_start' => '2026-09-01',
            'window_end' => '2026-09-14',
        ]);
        $this->assertDatabaseRejects(fn () => DB::table('data_snapshots')->insert([
            'run_id' => $publicationRun->id,
            'snapshot_uuid' => '44444444-4444-4444-8444-444444444444',
            'version' => 2,
            'status' => 'published',
            'is_active' => true,
            'window_start' => '2026-09-01',
            'window_end' => '2026-09-14',
            'timezone' => 'Asia/Jakarta',
            'created_at' => now(),
            'updated_at' => now(),
        ]));

        $stagedSnapshot = DataSnapshot::create([
            'run_id' => $run->id,
            'snapshot_uuid' => '66666666-6666-4666-8666-666666666666',
            'version' => 2,
            'status' => 'staged',
            'window_start' => '2026-09-01',
            'window_end' => '2026-09-14',
        ]);
        $publishedValue = DataSnapshotValue::create([
            'snapshot_id' => $stagedSnapshot->id,
            'metric_definition_id' => $definition->id,
            'section' => 'scalar',
            'value' => ['amount' => '1.00'],
        ]);
        DB::table('data_snapshots')->where('id', $stagedSnapshot->id)->update(['status' => 'published']);

        $this->assertDatabaseRejects(fn () => DB::table('data_snapshot_values')->insert([
            'snapshot_id' => $stagedSnapshot->id,
            'metric_definition_id' => $definition->id,
            'section' => 'new',
            'value' => json_encode(['amount' => '2.00']),
            'created_at' => now(),
            'updated_at' => now(),
        ]));
        $this->assertDatabaseRejects(fn () => DB::table('data_snapshot_values')
            ->where('id', $publishedValue->id)
            ->update(['value' => json_encode(['amount' => '3.00'])]));
        $this->assertDatabaseRejects(fn () => DB::table('data_snapshot_values')
            ->where('id', $publishedValue->id)
            ->delete());
        $this->assertDatabaseHas('data_snapshot_values', ['id' => $publishedValue->id]);

        $unpublishedSnapshot = DataSnapshot::create([
            'run_id' => $run->id,
            'snapshot_uuid' => '77777777-7777-4777-8777-777777777777',
            'version' => 3,
            'status' => 'staged',
            'window_start' => '2026-09-01',
            'window_end' => '2026-09-14',
        ]);
        $unpublishedValue = DataSnapshotValue::create([
            'snapshot_id' => $unpublishedSnapshot->id,
            'metric_definition_id' => $definition->id,
            'section' => 'scalar',
            'value' => ['amount' => '4.00'],
        ]);
        $this->assertDatabaseRejects(fn () => DataSnapshotValue::create([
            'snapshot_id' => $unpublishedSnapshot->id,
            'metric_definition_id' => $definition->id,
            'section' => 'scalar',
            'value' => ['amount' => '5.00'],
        ]));
        $this->assertTrue(DB::table('data_snapshot_values')->where('id', $unpublishedValue->id)->delete() === 1);
        $this->assertDatabaseMissing('data_snapshot_values', ['id' => $unpublishedValue->id]);
        $this->assertSame(1, DataPipelineRun::where('status', 'running')->count());
        $this->assertSame(1, DataSnapshot::where('is_active', true)->count());
    }

    private function assertDatabaseRejects(\Closure $operation): void
    {
        $rejected = false;
        try {
            DB::transaction($operation);
        } catch (\Throwable) {
            $rejected = true;
        }

        $this->assertTrue($rejected, 'Expected the database operation to be rejected.');
    }
}

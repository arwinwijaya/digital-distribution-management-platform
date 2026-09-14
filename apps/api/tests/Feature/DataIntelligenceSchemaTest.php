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
            'run_uuid' => 'run-0001',
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
            'snapshot_uuid' => 'snapshot-0001',
            'version' => 1,
            'status' => 'published',
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
            'event_uuid' => 'event-0001',
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
            'event_uuid' => 'event-0001',
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

        $updateSucceeded = false;
        try {
            DB::table('data_snapshots')->where('id', $snapshot->id)->update(['window_start' => '2026-09-02']);
            $updateSucceeded = true;
        } catch (\Throwable) {
        }
        $this->assertFalse($updateSucceeded, 'Published snapshot update must be rejected by the database.');
        $this->assertSame('2026-09-01', $snapshot->fresh()->window_start->toDateString());

        $deleteSucceeded = false;
        try {
            DB::table('data_snapshots')->where('id', $snapshot->id)->delete();
            $deleteSucceeded = true;
        } catch (\Throwable) {
        }
        $this->assertFalse($deleteSucceeded, 'Published snapshot deletion must be rejected by the database.');
        $this->assertDatabaseHas('data_snapshots', ['id' => $snapshot->id]);

        $duplicateRunSucceeded = false;
        try {
            DB::table('data_pipeline_runs')->insert([
                'run_uuid' => '33333333-3333-4333-8333-333333333333',
                'status' => 'running',
                'pipeline_version' => 'v1',
                'window_start' => '2026-09-01',
                'window_end' => '2026-09-14',
                'timezone' => 'Asia/Jakarta',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $duplicateRunSucceeded = true;
        } catch (\Throwable) {
        }
        $this->assertFalse($duplicateRunSucceeded, 'Only one active pipeline run may exist.');

        $duplicatePublicationSucceeded = false;
        try {
            DB::table('data_snapshots')->insert([
                'run_id' => $run->id,
                'snapshot_uuid' => '44444444-4444-4444-8444-444444444444',
                'version' => 2,
                'status' => 'published',
                'is_active' => true,
                'window_start' => '2026-09-01',
                'window_end' => '2026-09-14',
                'timezone' => 'Asia/Jakarta',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $duplicatePublicationSucceeded = true;
        } catch (\Throwable) {
        }
        $this->assertFalse($duplicatePublicationSucceeded, 'Only one active publication may exist.');
        $this->assertSame(1, DataPipelineRun::where('status', 'running')->count());
        $this->assertSame(1, DataSnapshot::where('is_active', true)->count());
    }
}

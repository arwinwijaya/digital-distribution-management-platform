<?php

namespace App\Services;

use App\Models\DataMetricDefinition;
use App\Models\DataPipelineRun;
use App\Models\DataSnapshot;
use App\Models\DataSnapshotValue;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

/**
 * Deterministic data-intelligence pipeline.
 *
 * - Windows use Asia/Jakarta calendar boundaries (prior day + 30-day rolling).
 * - Stages are named section producers (geographic, supplier, stock, measurement).
 * - Failed runs never replace the last successful active snapshot.
 * - Publication is a single transaction with a lock-protected active-snapshot
 *   promotion; only a complete result from all registered stages is published.
 */
class DataPipelineService
{
    public const PIPELINE_VERSION = 'v1';

    public const TIMEZONE = 'Asia/Jakarta';

    public const SECTIONS = ['geographic', 'supplier', 'stock', 'measurement'];

    /** @var array<string, callable> */
    private array $stages = [];

    /**
     * Register a stage callback for a named section.
     *
     * The callback receives the computed window `['start','end','timezone']` and
     * must return an array keyed by `dimension_key` with an array value payload.
     * An empty array is a valid (empty) output. Throwing from a callback fails
     * the run without publishing.
     */
    public function registerStage(string $section, callable $callback): void
    {
        $this->stages[$section] = $callback;
    }

    /**
     * Compute the prior-day and rolling 30-day window in Asia/Jakarta.
     *
     * @return array{start: string, end: string, timezone: string}
     */
    public function computeWindow(?CarbonInterface $now = null): array
    {
        $nowJakarta = $now !== null
            ? Carbon::parse($now)->setTimezone(self::TIMEZONE)
            : Carbon::now(self::TIMEZONE);

        // Jakarta calendar day of the run, then prior calendar day as window end.
        $jakartaMidnight = $nowJakarta->copy()->startOfDay();
        $windowEnd = $jakartaMidnight->copy()->subDay();
        // 30-day inclusive window: end minus 29 days.
        $windowStart = $windowEnd->copy()->subDays(29);

        return [
            'start' => $windowStart->toDateString(),
            'end' => $windowEnd->toDateString(),
            'timezone' => self::TIMEZONE,
        ];
    }

    /**
     * Run the pipeline synchronously.
     *
     * Creates a `DataPipelineRun` row, executes all registered stages, and if
     * every stage succeeds, atomically publishes exactly one immutable snapshot
     * carrying each section's payload plus `snapshot_version` and window
     * metadata. Failed stages mark the run as `failed`, persist `error_message`
     * and lineage, and leave any prior active snapshot untouched.
     */
    public function run(?CarbonInterface $now = null): DataPipelineRun
    {
        $window = $this->computeWindow($now);

        $run = DataPipelineRun::create([
            'run_uuid' => (string) Str::uuid(),
            'status' => 'running',
            'pipeline_version' => self::PIPELINE_VERSION,
            'window_start' => $window['start'],
            'window_end' => $window['end'],
            'timezone' => self::TIMEZONE,
            'lineage' => [
                'pipeline_version' => self::PIPELINE_VERSION,
                'window' => $window,
                'sections' => array_keys($this->effectiveStages()),
            ],
        ]);

        // Execute all stages outside the publish transaction so a failure does
        // not need to be unwound; publication only starts after every stage
        // has produced a value.
        $stageOutputs = [];
        $errors = [];

        foreach ($this->effectiveStages() as $section => $callback) {
            try {
                $result = $callback($window);
                // Empty/missing results are valid safe outputs (empty-data flow).
                $stageOutputs[$section] = is_array($result) ? $result : [];
            } catch (Throwable $e) {
                $errors[$section] = $e->getMessage() ?: get_class($e);
                $stageOutputs[$section] = null;
                // Mark the first failure; keep collecting attempted sections for lineage.
                break;
            }
        }

        if ($errors !== []) {
            $firstSection = array_key_first($errors);
            $errorMessage = sprintf('stage [%s] failed: %s', $firstSection, $errors[$firstSection]);

            $run->update([
                'status' => 'failed',
                'error_message' => $errorMessage,
                'lineage' => array_merge($run->lineage ?? [], [
                    'error' => $errorMessage,
                    'failed_section' => $firstSection,
                ]),
            ]);

            // No snapshot is created or published for failed runs.
            return $run->fresh();
        }

        // All stages succeeded — stage definitions/values and atomically promote.
        try {
            $snapshot = $this->publishSnapshot($run, $window, $stageOutputs);

            $run->update([
                'status' => 'completed',
                'lineage' => array_merge($run->lineage ?? [], [
                    'snapshot_version' => $snapshot->version,
                    'snapshot_uuid' => $snapshot->snapshot_uuid,
                ]),
            ]);
        } catch (Throwable $e) {
            // Publication itself failed (e.g. concurrent active-snapshot race).
            // Retain the previous active snapshot; record the error.
            $run->update([
                'status' => 'failed',
                'error_message' => 'publication failed: '.$e->getMessage(),
                'lineage' => array_merge($run->lineage ?? [], [
                    'error' => 'publication failed: '.$e->getMessage(),
                ]),
            ]);

            return $run->fresh();
        }

        return $run->fresh();
    }

    /**
     * Return the effective stage map (user-registered or safe defaults).
     *
     * When no stages have been registered, four default stages returning an
     * empty payload are supplied so the empty-data run still publishes a valid
     * completed snapshot without deleting audit history.
     *
     * @return array<string, callable>
     */
    private function effectiveStages(): array
    {
        if ($this->stages !== []) {
            return $this->stages;
        }

        $defaults = [];
        foreach (self::SECTIONS as $section) {
            $defaults[$section] = static fn (array $window): array => [];
        }

        return $defaults;
    }

    /**
     * Atomically stage and promote one immutable snapshot inside a transaction.
     *
     * The transaction (a) obtains a lock on the single active-snapshot row if
     * one exists, (b) deactivates the previous active row, (c) creates the new
     * snapshot as staged with metric definitions/values, and (d) flips it to
     * published+active so the immutable trigger only guards post-publication
     * mutation. Partial/failed rows are never eligible for publication.
     */
    private function publishSnapshot(DataPipelineRun $run, array $window, array $stageOutputs): DataSnapshot
    {
        return DB::transaction(function () use ($run, $window, $stageOutputs): DataSnapshot {
            // Lock-protect competing active publications.
            // SQLite ignores FOR UPDATE but the transaction plus the partial
            // unique index still serialises; PostgreSQL honours the row lock.
            $activeSnapshots = DataSnapshot::where('is_active', true)->lockForUpdate()->get();

            $nextVersion = (int) (DataSnapshot::max('version') ?? 0) + 1;

            // Create the snapshot as staged so value rows can be inserted before
            // the published-status flip (published snapshot/values are immutable).
            $snapshot = DataSnapshot::create([
                'run_id' => $run->id,
                'snapshot_uuid' => (string) Str::uuid(),
                'version' => $nextVersion,
                'status' => 'staged',
                'is_active' => false,
                'window_start' => $window['start'],
                'window_end' => $window['end'],
                'timezone' => self::TIMEZONE,
                'lineage' => [
                    'pipeline_version' => self::PIPELINE_VERSION,
                    'window' => $window,
                    'run_uuid' => $run->run_uuid,
                    'sections' => array_keys($stageOutputs),
                ],
            ]);

            // Persist each registered section with its typed payload plus snapshot
            // version/window metadata, as required by the reader contract.
            // Refactor: written while green — empty sections record one
            // placeholder row carrying version/window so the reader can expose
            // explicit window metadata even for empty snapshots.
            foreach ($stageOutputs as $section => $payloadMap) {
                if (! is_array($payloadMap) || $payloadMap === []) {
                    $this->storeEmptySectionPlaceholder($snapshot, $window, $section);
                    continue;
                }

                $definition = $this->ensureMetricDefinition($section);
                foreach ($payloadMap as $dimensionKey => $payload) {
                    DataSnapshotValue::create([
                        'snapshot_id' => $snapshot->id,
                        'metric_definition_id' => $definition->id,
                        'section' => $section,
                        'dimension_key' => (string) $dimensionKey,
                        'dimension' => is_array($payload) ? $payload : ['value' => $payload],
                        'value' => [
                            'payload' => $payload,
                            'snapshot_version' => $snapshot->version,
                            'window' => $window,
                        ],
                    ]);
                }
            }

            // Deactivate the previous active publication(s) and promote this one.
            if ($activeSnapshots->isNotEmpty()) {
                foreach ($activeSnapshots as $previousActive) {
                    DB::table('data_snapshots')
                        ->where('id', $previousActive->id)
                        ->update(['is_active' => false]);
                }
            }

            DB::table('data_snapshots')->where('id', $snapshot->id)->update([
                'status' => 'published',
                'is_active' => true,
                'published_at' => now(),
            ]);

            return $snapshot->fresh();
        });
    }

    /**
     * Serialise identical lineage/metric creation only when it would actually
     * repeat three times; the publication path already branches per section.
     */
    private function ensureMetricDefinition(string $section): DataMetricDefinition
    {
        $key = 'section_'.$section;

        return DataMetricDefinition::firstOrCreate(
            ['key' => $key],
            [
                'name' => ucfirst($section).' metrics',
                'method_version' => self::PIPELINE_VERSION,
                'definition' => ['section' => $section, 'pipeline_version' => self::PIPELINE_VERSION],
                'is_active' => true,
            ]
        );
    }

    private function storeEmptySectionPlaceholder(DataSnapshot $snapshot, array $window, string $section): void
    {
        DataSnapshotValue::create([
            'snapshot_id' => $snapshot->id,
            'metric_definition_id' => $this->ensureMetricDefinition($section)->id,
            'section' => $section,
            'dimension_key' => null,
            'dimension' => null,
            'value' => [
                'payload' => [],
                'snapshot_version' => $snapshot->version,
                'window' => $window,
            ],
        ]);
    }
}

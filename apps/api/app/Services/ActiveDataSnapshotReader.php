<?php

namespace App\Services;

use App\Models\DataSnapshot;
use App\Models\DataSnapshotValue;

/**
 * Read-only view over the single active immutable snapshot.
 *
 * Each value row's `value.snapshot_version` and `value.window` are populated
 * at publish time; the reader never mutates snapshots and never returns rows
 * whose snapshot status is not `published` with `is_active = true`.
 */
class ActiveDataSnapshotReader
{
    public function snapshot(): ?DataSnapshot
    {
        return DataSnapshot::where('is_active', true)
            ->where('status', 'published')
            ->first();
    }

    public function version(): ?int
    {
        return $this->snapshot()?->version;
    }

    /**
     * @return array{start: string, end: string, timezone: string}|null
     */
    public function window(): ?array
    {
        $snapshot = $this->snapshot();
        if ($snapshot === null) {
            return null;
        }

        return [
            'start' => $snapshot->window_start instanceof \Carbon\CarbonInterface
                ? $snapshot->window_start->toDateString()
                : (string) $snapshot->window_start,
            'end' => $snapshot->window_end instanceof \Carbon\CarbonInterface
                ? $snapshot->window_end->toDateString()
                : (string) $snapshot->window_end,
            'timezone' => $snapshot->timezone ?? DataPipelineService::TIMEZONE,
        ];
    }

    /**
     * Return decoded section payloads from the active snapshot only.
     *
     * Each entry carries its typed `payload` plus `snapshot_version` and
     * `window` (`{start,end,timezone}`) exactly as written by the pipeline.
     *
     * @return array<int, array{dimension_key: ?string, dimension: mixed, payload: mixed, snapshot_version: int, window: array}>
     */
    public function section(string $section): array
    {
        $snapshot = $this->snapshot();
        if ($snapshot === null) {
            return [];
        }

        $rows = DataSnapshotValue::where('snapshot_id', $snapshot->id)
            ->where('section', $section)
            ->get();

        return $rows->map(function (DataSnapshotValue $row): array {
            $decoded = is_array($row->value) ? $row->value : [];

            return [
                'dimension_key' => $row->dimension_key,
                'dimension' => $row->dimension,
                'payload' => $decoded['payload'] ?? null,
                'snapshot_version' => $decoded['snapshot_version'] ?? $this->snapshot()?->version,
                'window' => $decoded['window'] ?? $this->window(),
            ];
        })->all();
    }
}

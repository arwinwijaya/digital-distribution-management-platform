<?php

namespace App\Services;

use App\Models\AbExperiment;
use App\Models\AbExperimentAssignment;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/** Deterministic experiment assignment. This service never routes or mutates users. */
class AbExperimentService
{
    public const METHOD_VERSION = 'sha256-bucket-v1';

    /**
     * Assign a subject to control or treatment using SHA-256 of
     * `experiment_key|subject_key`. Existing assignments are always replayed.
     *
     * @param  AbExperiment|string  $experiment Experiment model or experiment_key.
     */
    public function assign(AbExperiment|string $experiment, string $subjectKey): AbExperimentAssignment
    {
        $model = $experiment instanceof AbExperiment
            ? $experiment
            : AbExperiment::where('experiment_key', $experiment)->firstOrFail();

        $existing = AbExperimentAssignment::where('experiment_id', $model->id)
            ->where('subject_key', $subjectKey)
            ->first();
        if ($existing !== null) {
            return $existing;
        }

        $digest = hash('sha256', $model->experiment_key.'|'.$subjectKey);
        // The first 8 hex digits are a stable unsigned 32-bit value. No RNG.
        $value = hexdec(substr($digest, 0, 8));
        $bucket = ($value % 100) < 50 ? 'control' : 'treatment';

        try {
            return DB::transaction(fn () => AbExperimentAssignment::create([
                'experiment_id' => $model->id,
                'subject_key' => $subjectKey,
                'bucket' => $bucket,
                'assigned_at' => now(),
            ]));
        } catch (QueryException $exception) {
            // Concurrent callers converge on the unique (experiment, subject) row.
            $existing = AbExperimentAssignment::where('experiment_id', $model->id)
                ->where('subject_key', $subjectKey)
                ->first();
            if ($existing !== null) {
                return $existing;
            }
            throw $exception;
        }
    }

    /** Return the stable bucket without persisting or assigning a user. */
    public function bucket(string $experimentKey, string $subjectKey): string
    {
        $digest = hash('sha256', $experimentKey.'|'.$subjectKey);
        return (hexdec(substr($digest, 0, 8)) % 100) < 50 ? 'control' : 'treatment';
    }
}

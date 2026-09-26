<?php

namespace App\Services;

use App\Models\AbExperiment;
use App\Models\RevenueLiftSnapshot;

/**
 * Decimal-safe revenue lift from control/treatment samples.
 *
 * Lift = (treatment_total - control_total) / control_total computed in cents.
 * Any condition that blocks honest measurement (missing samples, small sample,
 * or zero control total) persists an `insufficient-data` snapshot with a NULL
 * uplift -- never 0 or a perfect score. Snapshots are append-only; none of the
 * business records that produced the samples are mutated.
 */
class RevenueLiftService
{
    public const METHOD_VERSION = 'lift-mean-v1';

    /**
     * Compute revenue lift and persist a snapshot for the given experiment.
     *
     * @param  array{control?: array<int, array{revenue?: mixed}|mixed>, treatment?: array<int, array{revenue?: mixed}|mixed>}  $samples
     */
    public function computeLift(AbExperiment $experiment, array $samples = []): RevenueLiftSnapshot
    {
        $control = $samples['control'] ?? [];
        $treatment = $samples['treatment'] ?? [];
        $control = is_array($control) ? $control : [];
        $treatment = is_array($treatment) ? $treatment : [];

        $controlCount = count($control);
        $treatmentCount = count($treatment);

        $controlCents = 0;
        $treatmentCents = 0;
        foreach ($control as $row) {
            $controlCents += $this->decimalToCents($row['revenue'] ?? 0);
        }
        foreach ($treatment as $row) {
            $treatmentCents += $this->decimalToCents($row['revenue'] ?? 0);
        }

        $minimum = max(1, (int) ($experiment->minimum_sample_size ?? 0));
        $small = $controlCount < $minimum || $treatmentCount < $minimum;
        $zeroBaseline = $controlCents <= 0;

        if ($small || $zeroBaseline) {
            $note = $small
                ? "Samples below the {$minimum}-observation minimum; uplift is unknown, not zero."
                : 'Control revenue is all zero; uplift is undefined and is never treated as a perfect score.';
        } else {
            $note = 'Uplift = (treatment_total - control_total) / control_total over provided samples; deterministic, non-probabilistic.';
        }

        $uplift = ($small || $zeroBaseline)
            ? null
            : round(($treatmentCents - $controlCents) / $controlCents, 6);

        return RevenueLiftSnapshot::create([
            'experiment_id' => $experiment->id,
            'uplift' => $uplift === null ? null : number_format($uplift, 6, '.', ''),
            'status' => $uplift === null ? 'insufficient-data' : 'computed',
            'method_version' => self::METHOD_VERSION,
            'control_sample_size' => $controlCount,
            'treatment_sample_size' => $treatmentCount,
            'control_revenue' => $this->moneyFromCents($controlCents),
            'treatment_revenue' => $this->moneyFromCents($treatmentCents),
            'metadata' => [
                'minimum_sample_size' => $minimum,
                'sufficient' => $uplift !== null,
                'note' => $note,
            ],
        ]);
    }

    private function decimalToCents(mixed $amount): int
    {
        $value = trim((string) ($amount ?? '0'));
        $negative = str_starts_with($value, '-');
        $value = ltrim($value, '+-');
        [$whole, $fraction] = array_pad(explode('.', $value, 2), 2, '0');
        $fraction = str_pad(substr($fraction, 0, 2), 2, '0');
        $cents = ((int) ($whole ?: 0) * 100) + (int) $fraction;

        return $negative ? -$cents : $cents;
    }

    private function moneyFromCents(int $cents): string
    {
        $negative = $cents < 0;
        $cents = abs($cents);

        return ($negative ? '-' : '').intdiv($cents, 100).'.'.str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);
    }
}

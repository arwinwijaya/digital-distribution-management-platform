<?php

namespace App\Services\Recommendation;

use App\Services\RecommendationService;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

/**
 * Resolves the configured model adapter and guards callers from external failure.
 *
 * The resolver is the only place where an external adapter may fail. Every
 * failure (exception or schema violation) is converted into a deterministic
 * fallback result with explicit flags. It never mutates business state.
 */
class RecommendationModelAdapterResolver implements RecommendationModelAdapter
{
    public function __construct(
        private readonly RecommendationService $service,
        private readonly ModelOutputValidator $validator,
        private readonly ?RecommendationModelAdapter $external = null,
    ) {
    }

    public function resolve(): RecommendationModelAdapter
    {
        if (! $this->useExternal()) {
            return new DeterministicRecommendationAdapter($this->service);
        }

        // Return the guarded resolver rather than exposing the raw provider.
        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function predict(?int $outletId, int $limit = RecommendationService::DEFAULT_LIMIT): array
    {
        if (! $this->useExternal()) {
            return $this->deterministic()->predict($outletId, $limit);
        }

        try {
            // Run untrusted adapter code in a savepoint and always roll it back.
            // This prevents an adapter from persisting Orders, Promotions, POs,
            // or any other business state even if it attempts a write.
            $output = $this->invokeExternalReadOnly($outletId, $limit);
        } catch (Throwable) {
            return $this->fallback($outletId, $limit, ['adapter_error' => true]);
        }

        if (! $this->validator->isValid($output)) {
            return $this->fallback($outletId, $limit, ['invalid_output' => true]);
        }

        return $output;
    }

    /**
     * @return array<string, mixed>
     */
    public function explain(?int $outletId, int $limit = RecommendationService::DEFAULT_LIMIT): array
    {
        return $this->predict($outletId, $limit);
    }

    private function useExternal(): bool
    {
        if (! config('ai_actions.enabled', true) || config('ai_actions.kill_switch', false)) {
            return false;
        }

        if (config('ai_actions.ml_adapter.driver', 'deterministic') !== 'external') {
            return false;
        }

        return $this->external !== null;
    }

    private function deterministic(): DeterministicRecommendationAdapter
    {
        return new DeterministicRecommendationAdapter($this->service);
    }

    /**
     * Execute external adapter code in a transaction that is guaranteed to roll back.
     * A private sentinel carries the output out of the transaction without committing.
     *
     * @return array<string, mixed>
     */
    private function invokeExternalReadOnly(?int $outletId, int $limit): array
    {
        try {
            DB::transaction(function () use ($outletId, $limit): never {
                $output = $this->external->predict($outletId, $limit);
                throw new AdapterReadOnlyResult($output);
            });
        } catch (AdapterReadOnlyResult $result) {
            return $result->output;
        }

        throw new RuntimeException('External adapter transaction completed unexpectedly.');
    }

    /**
     * @param  array<string, mixed>  $flags
     * @return array<string, mixed>
     */
    private function fallback(?int $outletId, int $limit, array $flags): array
    {
        $result = $this->deterministic()->predict($outletId, $limit);

        return array_merge($result, ['fallback' => true], $flags);
    }
}

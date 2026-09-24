<?php

namespace App\Services\Recommendation;

use App\Services\RecommendationService;
use Throwable;

/**
 * Resolves the configured model adapter and guards callers from external failure.
 *
 * The resolver is the only place where an external adapter may fail. Every
 * failure (exception or schema violation) is converted into a deterministic
 * fallback result with explicit flags. It never mutates business state.
 */
class RecommendationModelAdapterResolver
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

        return $this->external;
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
            $output = $this->external->predict($outletId, $limit);
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
     * @param  array<string, mixed>  $flags
     * @return array<string, mixed>
     */
    private function fallback(?int $outletId, int $limit, array $flags): array
    {
        $result = $this->deterministic()->predict($outletId, $limit);

        return array_merge($result, ['fallback' => true], $flags);
    }
}

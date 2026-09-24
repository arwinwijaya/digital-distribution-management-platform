<?php

namespace App\Services\Recommendation;

use App\Services\RecommendationService;

/**
 * Deterministic adapter that wraps the built-in RecommendationService.
 * This is the default, production-ready implementation with no external dependencies.
 */
class DeterministicRecommendationAdapter implements RecommendationModelAdapter
{
    public function __construct(
        private readonly RecommendationService $service,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function predict(?int $outletId, int $limit = RecommendationService::DEFAULT_LIMIT): array
    {
        return $this->service->recommend($outletId, $limit);
    }

    /**
     * @return array<string, mixed>
     */
    public function explain(?int $outletId, int $limit = RecommendationService::DEFAULT_LIMIT): array
    {
        return $this->predict($outletId, $limit);
    }
}
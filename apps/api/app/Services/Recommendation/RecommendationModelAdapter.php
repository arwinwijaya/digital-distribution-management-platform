<?php

namespace App\Services\Recommendation;

use App\Services\RecommendationService;

/**
 * Seam for optional ML/LLM recommendation providers.
 *
 * Implementations MUST NOT mutate business state. They return suggestions only.
 */
interface RecommendationModelAdapter
{
    /**
     * @return array<string, mixed>
     */
    public function predict(?int $outletId, int $limit = RecommendationService::DEFAULT_LIMIT): array;

    /**
     * @return array<string, mixed>
     */
    public function explain(?int $outletId, int $limit = RecommendationService::DEFAULT_LIMIT): array;
}

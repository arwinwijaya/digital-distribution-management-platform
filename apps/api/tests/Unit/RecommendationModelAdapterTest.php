<?php

namespace Tests\Unit;

use App\Services\Recommendation\DeterministicRecommendationAdapter;
use App\Services\Recommendation\ModelOutputValidator;
use App\Services\Recommendation\RecommendationModelAdapter;
use App\Services\Recommendation\RecommendationModelAdapterResolver;
use App\Services\RecommendationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class RecommendationModelAdapterTest extends TestCase
{
    use RefreshDatabase;

    public function test_default_config_resolves_deterministic_adapter(): void
    {
        config([
            'ai_actions.enabled' => true,
            'ai_actions.ml_adapter.driver' => 'deterministic',
        ]);

        $resolver = new RecommendationModelAdapterResolver(
            app(RecommendationService::class),
            new ModelOutputValidator(),
        );

        $this->assertInstanceOf(DeterministicRecommendationAdapter::class, $resolver->resolve());
    }

    public function test_external_adapter_failure_falls_back_without_leaking_exception(): void
    {
        config([
            'ai_actions.enabled' => true,
            'ai_actions.ml_adapter.driver' => 'external',
        ]);

        $resolver = new RecommendationModelAdapterResolver(
            app(RecommendationService::class),
            new ModelOutputValidator(),
            new FakeRecommendationModelAdapter(throwable: new RuntimeException('provider timeout')),
        );

        $result = $resolver->predict(null);

        $this->assertTrue($result['fallback']);
        $this->assertTrue($result['adapter_error']);
        $this->assertSame('purchase_frequency_v1', $result['method']);
        $this->assertSame('1.0.0', $result['method_version']);
    }

    public function test_invalid_external_output_is_rejected_and_falls_back(): void
    {
        config([
            'ai_actions.enabled' => true,
            'ai_actions.ml_adapter.driver' => 'external',
        ]);

        $resolver = new RecommendationModelAdapterResolver(
            app(RecommendationService::class),
            new ModelOutputValidator(),
            new FakeRecommendationModelAdapter(output: ['unexpected' => true]),
        );

        $result = $resolver->predict(null);

        $this->assertTrue($result['fallback']);
        $this->assertTrue($result['invalid_output']);
        $this->assertSame('purchase_frequency_v1', $result['method']);
    }
}

final class FakeRecommendationModelAdapter implements RecommendationModelAdapter
{
    public function __construct(
        private readonly ?array $output = null,
        private readonly ?\Throwable $throwable = null,
    ) {
    }

    public function predict(?int $outletId, int $limit = RecommendationService::DEFAULT_LIMIT): array
    {
        if ($this->throwable !== null) {
            throw $this->throwable;
        }

        return $this->output ?? [];
    }

    public function explain(?int $outletId, int $limit = RecommendationService::DEFAULT_LIMIT): array
    {
        return $this->predict($outletId, $limit);
    }
}

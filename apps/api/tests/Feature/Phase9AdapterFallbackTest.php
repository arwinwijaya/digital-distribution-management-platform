<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Outlet;
use App\Models\Product;
use App\Models\User;
use App\Services\AuthService;
use App\Services\Recommendation\RecommendationModelAdapter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Cross-unit fallback/guardrail test (T17).
 *
 * Exercises the full HTTP stack: route → controller → resolver → validator → service.
 * Fake adapters are bound at the adapter seam via the container.
 */
class Phase9AdapterFallbackTest extends TestCase
{
    use RefreshDatabase;

    private function adminHeaders(): array
    {
        $admin = User::factory()->admin()->create();
        $token = app(AuthService::class)->createToken($admin)['token'];

        return ['Authorization' => "Bearer {$token}"];
    }

    /** @test Scenario A: External adapter throws → fallback, no 500, no Order mutation */
    public function test_external_adapter_exception_falls_back_safely_via_http(): void
    {
        // Bind a failing external adapter at the seam (tests only; production has no external).
        $this->app->bind('recommendation.external', function () {
            return new class implements RecommendationModelAdapter {
                public function predict(?int $outletId, int $limit = 10): array
                {
                    throw new \RuntimeException('Provider unavailable');
                }
                public function explain(?int $outletId, int $limit = 10): array
                {
                    return $this->predict($outletId, $limit);
                }
            };
        });

        // Enable external driver for this test
        config(['ai_actions.enabled' => true, 'ai_actions.ml_adapter.driver' => 'external']);

        $headers = $this->adminHeaders();

        // Execute real HTTP request through the recommendation endpoint
        $response = $this->withHeaders($headers)->getJson('/api/ai/recommendations');

        // Assertions: no 500, fallback flag present, deterministic method used
        $response->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.fallback', true)
            ->assertJsonPath('data.adapter_error', true)
            ->assertJsonPath('data.method', 'purchase_frequency_v1')
            ->assertJsonPath('data.method_version', '1.0.0');

        // Business state must not be mutated by adapter fallback path
        $this->assertSame(0, Order::count(), 'Adapter fallback must not create Orders');
    }

    /** @test Scenario B: External adapter returns invalid output → rejected, fallback, no Order */
    public function test_external_adapter_invalid_output_rejected_and_falls_back_via_http(): void
    {
        $this->app->bind('recommendation.external', function () {
            return new class implements RecommendationModelAdapter {
                public function predict(?int $outletId, int $limit = 10): array
                {
                    // Missing required fields: recommendations, method_version, fallback
                    return ['unexpected' => 'structure'];
                }
                public function explain(?int $outletId, int $limit = 10): array
                {
                    return $this->predict($outletId, $limit);
                }
            };
        });

        config(['ai_actions.enabled' => true, 'ai_actions.ml_adapter.driver' => 'external']);

        $headers = $this->adminHeaders();
        $response = $this->withHeaders($headers)->getJson('/api/ai/recommendations');

        $response->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.fallback', true)
            ->assertJsonPath('data.invalid_output', true)
            ->assertJsonPath('data.method', 'purchase_frequency_v1');

        $this->assertSame(0, Order::count(), 'Invalid adapter output must not create Orders');
    }

    /** @test Scenario C: External adapter attempts mutation → detected, rolled back, fallback, no Order */
    public function test_external_adapter_mutation_attempt_detected_and_rolled_back_via_http(): void
    {
        $product = Product::factory()->create(['stock_quantity' => 100, 'price' => 1000]);

        $this->app->bind('recommendation.external', function () use ($product) {
            return new class($product->id) implements RecommendationModelAdapter {
                public function __construct(private readonly int $productId) {}
                public function predict(?int $outletId, int $limit = 10): array
                {
                    // Side effect: attempt to create an Order (malicious/buggy adapter)
                    Order::create([
                        'order_id' => 'ADAPTER-MUTATE-'.bin2hex(random_bytes(4)),
                        'outlet_id' => $outletId ?? 1,
                        'status' => 'New',
                        'total_amount' => 100,
                        'idempotency_key' => 'adapter-mutate-'.bin2hex(random_bytes(8)),
                    ]);

                    // Return valid-looking output so validator would pass without mutation guard
                    return [
                        'recommendations' => [],
                        'limit' => $limit,
                        'data_points' => 0,
                        'data_sufficiency' => [],
                        'fallback' => false,
                        'method' => 'fake',
                        'method_version' => '1.0.0',
                        'measurement' => [],
                        'low_confidence' => false,
                    ];
                }
                public function explain(?int $outletId, int $limit = 10): array
                {
                    return $this->predict($outletId, $limit);
                }
            };
        });

        config(['ai_actions.enabled' => true, 'ai_actions.ml_adapter.driver' => 'external']);

        $outlet = Outlet::factory()->create();
        $headers = $this->adminHeaders();
        $response = $this->withHeaders($headers)->getJson('/api/ai/recommendations?outlet_id='.$outlet->id);

        $response->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.fallback', true)
            ->assertJsonPath('data.mutation_attempt', true)
            ->assertJsonPath('data.method', 'purchase_frequency_v1');

        $this->assertSame(0, Order::count(), 'Mutation attempt must be rolled back, zero Orders');
    }

    /** @test Scenario D: Slow adapter exceeds timeout → timeout fallback, no Order */
    public function test_external_adapter_timeout_falls_back_via_http(): void
    {
        $this->app->bind('recommendation.external', function () {
            return new class implements RecommendationModelAdapter {
                public function predict(?int $outletId, int $limit = 10): array
                {
                    usleep(20000); // 20ms > configured 1ms timeout
                    return [
                        'recommendations' => [],
                        'limit' => $limit,
                        'data_points' => 0,
                        'data_sufficiency' => [],
                        'fallback' => false,
                        'method' => 'fake',
                        'method_version' => '1.0.0',
                        'measurement' => [],
                        'low_confidence' => false,
                    ];
                }
                public function explain(?int $outletId, int $limit = 10): array
                {
                    return $this->predict($outletId, $limit);
                }
            };
        });

        config([
            'ai_actions.enabled' => true,
            'ai_actions.ml_adapter.driver' => 'external',
            'ai_actions.ml_adapter.timeout_ms' => 1,
            'ai_actions.ml_adapter.failure_threshold' => 10,
            'ai_actions.ml_adapter.cooldown_seconds' => 60,
        ]);

        $headers = $this->adminHeaders();
        $response = $this->withHeaders($headers)->getJson('/api/ai/recommendations');

        $response->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.fallback', true)
            ->assertJsonPath('data.timeout', true)
            ->assertJsonPath('data.method', 'purchase_frequency_v1');

        $this->assertSame(0, Order::count(), 'Timeout fallback must not create Orders');
    }
}
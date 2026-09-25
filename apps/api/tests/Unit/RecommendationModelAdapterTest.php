<?php

namespace Tests\Unit;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Outlet;
use App\Models\Product;
use App\Services\Recommendation\DeterministicRecommendationAdapter;
use App\Services\Recommendation\ModelOutputValidator;
use App\Services\Recommendation\RecommendationModelAdapter;
use App\Services\Recommendation\RecommendationModelAdapterResolver;
use App\Services\RecommendationService;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

class RecommendationModelAdapterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Deterministic circuit-breaker state for every test.
        config(['cache.default' => 'array']);
        Cache::flush();
    }

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

    public function test_deterministic_adapter_output_passes_validator(): void
    {
        $product = Product::factory()->create([
            'price' => 1250,
            'stock_quantity' => 100,
            'is_active' => true,
        ]);
        $outletId = Outlet::factory()->create()->id;
        $order = Order::create([
            'order_id' => 'ORD-VALIDATOR-'.uniqid(),
            'outlet_id' => $outletId,
            'status' => 'Delivered',
            'total_amount' => 1250,
            'paid_amount' => 0,
            'idempotency_key' => 'validator-'.uniqid(),
        ]);
        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 2,
            'unit_price' => 1250,
            'subtotal' => 2500,
        ]);

        $output = (new DeterministicRecommendationAdapter(app(RecommendationService::class)))
            ->predict($outletId);

        (new ModelOutputValidator())->assertValid($output);
        $this->assertTrue($output['low_confidence']);
        $this->assertSame('1250.00', $output['recommendations'][0]['price']);
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

    public function test_mutating_external_adapter_cannot_persist_business_state(): void
    {
        config([
            'ai_actions.enabled' => true,
            'ai_actions.ml_adapter.driver' => 'external',
        ]);

        $product = Product::factory()->create();
        $outletId = Outlet::factory()->create()->id;

        $resolver = new RecommendationModelAdapterResolver(
            app(RecommendationService::class),
            new ModelOutputValidator(),
            new MutatingFakeRecommendationModelAdapter($product->id),
        );

        $result = $resolver->predict($outletId);

        $this->assertSame(0, Order::count(), 'Adapter must not persist business state (Order created)');
        $this->assertTrue($result['fallback']);
        $this->assertTrue($result['mutation_attempt']);
    }

    public function test_validator_rejects_unknown_item_fields(): void
    {
        $validator = new ModelOutputValidator();

        $this->assertTrue($validator->isValid(validPriceAwareOutput(10)));

        $tampered = validPriceAwareOutput(10);
        $tampered['recommendations'][] = [
            'rank' => 999,
            'product_id' => 1,
            'name' => 'X',
            'sku' => 'X',
            'category' => 'X',
            'price' => 12.5,
            'purchased_quantity' => 1,
            'order_count' => 1,
            'reason' => 'x',
            'evil' => 'extra',
        ];
        $this->assertFalse($validator->isValid($tampered));
    }

    public function test_validator_rejects_wrong_item_field_types(): void
    {
        $validator = new ModelOutputValidator();

        $tampered = validPriceAwareOutput(10);
        $tampered['recommendations'][] = [
            'rank' => 'not-an-int',
            'product_id' => 1.5,
            'name' => '',
            'sku' => '',
            'category' => '',
            'price' => 'not-a-number',
            'purchased_quantity' => -1,
            'order_count' => [],
            'reason' => 123,
        ];
        $this->assertFalse($validator->isValid($tampered));
    }

    public function test_write_detection_listener_does_not_leak_between_predictions(): void
    {
        config([
            'ai_actions.enabled' => true,
            'ai_actions.ml_adapter.driver' => 'external',
            'ai_actions.ml_adapter.failure_threshold' => 10,
        ]);

        $external = new CallCountingFakeRecommendationModelAdapter;
        $resolver = new RecommendationModelAdapterResolver(
            app(RecommendationService::class),
            new ModelOutputValidator(),
            $external,
        );
        $dispatcher = DB::connection()->getEventDispatcher();
        $before = count($dispatcher->getListeners(QueryExecuted::class));

        $first = $resolver->predict(null);
        $afterFirst = count($dispatcher->getListeners(QueryExecuted::class));
        $second = $resolver->predict(null);
        $afterSecond = count($dispatcher->getListeners(QueryExecuted::class));

        $this->assertTrue($first['adapter_error']);
        $this->assertTrue($second['adapter_error']);
        $this->assertSame(0, $afterFirst - $before);
        $this->assertSame($afterFirst, $afterSecond);
        $this->assertArrayNotHasKey('mutation_attempt', $second);
    }

    public function test_circuit_breaker_state_is_shared_between_resolver_instances(): void
    {
        config([
            'ai_actions.enabled' => true,
            'ai_actions.ml_adapter.driver' => 'external',
            'ai_actions.ml_adapter.failure_threshold' => 1,
            'ai_actions.ml_adapter.cooldown_seconds' => 3600,
        ]);
        Cache::flush();

        $externalA = new CallCountingFakeRecommendationModelAdapter;
        $resolverA = new RecommendationModelAdapterResolver(
            app(RecommendationService::class), new ModelOutputValidator(), $externalA,
        );
        $opened = $resolverA->predict(null);
        $this->assertTrue($opened['adapter_error']);
        $this->assertSame(1, $externalA->calls);

        $externalB = new CallCountingFakeRecommendationModelAdapter;
        $resolverB = new RecommendationModelAdapterResolver(
            app(RecommendationService::class), new ModelOutputValidator(), $externalB,
        );
        $shortCircuited = $resolverB->predict(null);

        $this->assertTrue($shortCircuited['circuit_open']);
        $this->assertSame(0, $externalB->calls);
    }

    public function test_slow_adapter_returns_timeout_fallback(): void
    {
        config([
            'ai_actions.enabled' => true,
            'ai_actions.ml_adapter.driver' => 'external',
            'ai_actions.ml_adapter.timeout_ms' => 1,
            'ai_actions.ml_adapter.failure_threshold' => 10,
            'ai_actions.ml_adapter.cooldown_seconds' => 60,
        ]);

        $resolver = new RecommendationModelAdapterResolver(
            app(RecommendationService::class),
            new ModelOutputValidator(),
            new SlowFakeRecommendationModelAdapter,
        );

        $result = $resolver->predict(null);

        $this->assertTrue($result['fallback']);
        $this->assertTrue($result['timeout']);
    }

    public function test_circuit_breaker_opens_after_consecutive_failures(): void
    {
        config([
            'ai_actions.enabled' => true,
            'ai_actions.ml_adapter.driver' => 'external',
            'ai_actions.ml_adapter.timeout_ms' => 1000,
            'ai_actions.ml_adapter.failure_threshold' => 2,
            'ai_actions.ml_adapter.cooldown_seconds' => 3600,
        ]);

        $external = new CallCountingFakeRecommendationModelAdapter;
        $resolver = new RecommendationModelAdapterResolver(
            app(RecommendationService::class),
            new ModelOutputValidator(),
            $external,
        );

        $first = $resolver->predict(null);
        $this->assertTrue($first['fallback']);
        $this->assertTrue($first['adapter_error']);
        $this->assertSame(1, $external->calls);

        $second = $resolver->predict(null);
        $this->assertTrue($second['fallback']);
        $this->assertTrue($second['adapter_error']);
        $this->assertSame(2, $external->calls);

        // Breaker is now open: no external call at all.
        $third = $resolver->predict(null);
        $this->assertTrue($third['fallback']);
        $this->assertTrue($third['circuit_open']);
        $this->assertSame(2, $external->calls);
    }

    public function test_circuit_breaker_reopens_after_cooldown(): void
    {
        config([
            'ai_actions.enabled' => true,
            'ai_actions.ml_adapter.driver' => 'external',
            'ai_actions.ml_adapter.timeout_ms' => 1000,
            'ai_actions.ml_adapter.failure_threshold' => 1,
            'ai_actions.ml_adapter.cooldown_seconds' => 0,
        ]);

        $external = new CallCountingFakeRecommendationModelAdapter;
        $resolver = new RecommendationModelAdapterResolver(
            app(RecommendationService::class),
            new ModelOutputValidator(),
            $external,
        );

        // One failure trips the breaker (threshold = 1).
        $tripped = $resolver->predict(null);
        $this->assertTrue($tripped['adapter_error']);
        $this->assertSame(1, $external->calls);

        // Cooldown of 0 seconds has already elapsed, so the breaker re-opens
        // and the external adapter is called again.
        $retried = $resolver->predict(null);
        $this->assertTrue($retried['adapter_error']);
        $this->assertSame(2, $external->calls);
    }

    public function test_alternate_connection_write_is_detected_and_rolled_back(): void
    {
        config([
            'ai_actions.enabled' => true,
            'ai_actions.ml_adapter.driver' => 'external',
            'ai_actions.ml_adapter.timeout_ms' => 1000,
            'ai_actions.ml_adapter.failure_threshold' => 10,
            'ai_actions.ml_adapter.cooldown_seconds' => 60,
            'database.connections.alternate' => config('database.connections.'.config('database.default')),
        ]);

        // Open the alternate connection before prediction so the resolver wraps it.
        // Point it at the default PDO so the test sees the same rows; SQLite
        // :memory: is per-PDO.
        $alternate = DB::connection('alternate');
        $alternate->setPdo(DB::connection()->getPdo());
        $alternate->setReadPdo(DB::connection()->getReadPdo());
        $this->assertSame(0, Order::on($alternate->getName())->count());

        $outletId = Outlet::factory()->create()->id;

        $resolver = new RecommendationModelAdapterResolver(
            app(RecommendationService::class),
            new ModelOutputValidator(),
            new AlternateConnectionMutatingFakeRecommendationModelAdapter($alternate->getName(), $outletId),
        );

        $result = $resolver->predict($outletId);

        $this->assertTrue($result['fallback']);
        $this->assertTrue($result['mutation_attempt']);
        $this->assertSame(0, Order::count(), 'Alternate-connection Order write must be rolled back');
        $this->assertSame(0, Order::on('alternate')->count());
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

/**
 * A malicious/buggy adapter that tries to create an Order.
 * The resolver's mutation guard must roll this back.
 */
final class MutatingFakeRecommendationModelAdapter implements RecommendationModelAdapter
{
    public function __construct(
        private readonly int $productId,
    ) {
    }

    public function predict(?int $outletId, int $limit = RecommendationService::DEFAULT_LIMIT): array
    {
        // Side effect: create an Order (simulating a buggy/malicious adapter)
        Order::create([
            'order_id' => 'ADAPTER-MUTATE-'.bin2hex(random_bytes(4)),
            'outlet_id' => $outletId ?? 1,
            'status' => 'New',
            'total_amount' => 100,
            'idempotency_key' => 'adapter-mutate-'.bin2hex(random_bytes(8)),
        ]);

        // Return a valid-looking output so validator passes
        return validRecommendationOutput($limit);
    }

    public function explain(?int $outletId, int $limit = RecommendationService::DEFAULT_LIMIT): array
    {
        return $this->predict($outletId, $limit);
    }
}

final class SlowFakeRecommendationModelAdapter implements RecommendationModelAdapter
{
    public function predict(?int $outletId, int $limit = RecommendationService::DEFAULT_LIMIT): array
    {
        usleep(20_000);

        return validRecommendationOutput($limit);
    }

    public function explain(?int $outletId, int $limit = RecommendationService::DEFAULT_LIMIT): array
    {
        return $this->predict($outletId, $limit);
    }
}

final class CallCountingFakeRecommendationModelAdapter implements RecommendationModelAdapter
{
    public int $calls = 0;

    public function predict(?int $outletId, int $limit = RecommendationService::DEFAULT_LIMIT): array
    {
        $this->calls++;
        throw new RuntimeException('provider unavailable');
    }

    public function explain(?int $outletId, int $limit = RecommendationService::DEFAULT_LIMIT): array
    {
        return $this->predict($outletId, $limit);
    }
}

final class AlternateConnectionMutatingFakeRecommendationModelAdapter implements RecommendationModelAdapter
{
    public function __construct(
        private readonly string $connection,
        private readonly int $outletId,
    ) {
    }

    public function predict(?int $outletId, int $limit = RecommendationService::DEFAULT_LIMIT): array
    {
        Order::on($this->connection)->create([
            'order_id' => 'ADAPTER-ALT-MUTATE-'.bin2hex(random_bytes(4)),
            'outlet_id' => $this->outletId,
            'status' => 'New',
            'total_amount' => 100,
            'idempotency_key' => 'adapter-alt-mutate-'.bin2hex(random_bytes(8)),
        ]);

        return validRecommendationOutput($limit);
    }

    public function explain(?int $outletId, int $limit = RecommendationService::DEFAULT_LIMIT): array
    {
        return $this->predict($outletId, $limit);
    }
}

/**
 * @return array<string, mixed>
 */
function validRecommendationOutput(int $limit): array
{
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

/**
 * A fully valid output including one strictly-typed recommendation item.
 *
 * @return array<string, mixed>
 */
function validPriceAwareOutput(int $limit): array
{
    $out = validRecommendationOutput($limit);
    $out['recommendations'][] = [
        'rank' => 1,
        'product_id' => 42,
        'name' => 'Widget',
        'sku' => 'WGT-1',
        'category' => 'General',
        'price' => 12.5,
        'purchased_quantity' => 3,
        'order_count' => 2,
        'reason' => 'Frequently purchased across all outlets.',
    ];

    return $out;
}
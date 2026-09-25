<?php

namespace App\Services\Recommendation;

use App\Services\RecommendationService;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

/**
 * Resolves the configured model adapter and guards callers from external failure.
 *
 * The resolver is the only place where an external adapter may fail. Every
 * failure (exception, timeout, mutation attempt, or schema violation) is
 * converted into a deterministic fallback result with explicit flags.
 * External adapters receive only plain read data through their adapter contract;
 * the resolver never passes write-capable facades or services. Business state is
 * defined as database state. The always-rollback transaction plus listeners on
 * every open connection are defense-in-depth: they detect and revert DB writes,
 * including on alternate/lazily-opened connections that are open at call time.
 *
 * Residual MVP limitation: in-process non-DB side effects (for example queue
 * dispatches, file writes, or writes on connections opened after the call begins)
 * are not interceptable by this seam. They must be addressed by adapter review
 * and the read-only contract. This is an accepted MVP limitation per Phase 9
 * spec DD-4 (the adapter is an optional seam, never an MVP blocker).
 */
class RecommendationModelAdapterResolver implements RecommendationModelAdapter
{
    private const CIRCUIT_KEY_DEFAULT = 'ai_actions:ml_adapter:circuit';

    private bool $mutationDetected = false;

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

        if ($this->isCircuitOpen()) {
            return $this->fallback($outletId, $limit, ['circuit_open' => true]);
        }

        $timeoutMs = (int) config('ai_actions.ml_adapter.timeout_ms', 1000);
        $start = hrtime(true);

        try {
            // Run untrusted adapter code in a savepoint and always roll it back.
            // This prevents an adapter from persisting Orders, Promotions, POs,
            // or any other business state even if it attempts a write.
            $output = $this->invokeExternalReadOnly($outletId, $limit);
        } catch (Throwable) {
            $this->recordExternalFailure();

            return $this->fallback($outletId, $limit, ['adapter_error' => true]);
        }

        if ($this->mutationDetected) {
            $this->recordExternalFailure();

            return $this->fallback($outletId, $limit, ['mutation_attempt' => true]);
        }

        $elapsedMs = (hrtime(true) - $start) / 1_000_000;
        if ($elapsedMs > $timeoutMs) {
            $this->recordExternalFailure();

            return $this->fallback($outletId, $limit, ['timeout' => true]);
        }

        if (! $this->validator->isValid($output)) {
            $this->recordExternalFailure();

            return $this->fallback($outletId, $limit, ['invalid_output' => true]);
        }

        $this->recordExternalSuccess();

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
        $this->mutationDetected = false;
        $connections = DB::getConnections();
        $alternateTransactions = [];
        $dispatchers = [];

        foreach ($connections as $connection) {
            // Use a per-call dispatcher clone so this listener can be removed by
            // restoring the original dispatcher without disturbing application
            // query listeners registered elsewhere.
            $dispatcher = $connection->getEventDispatcher();
            if ($dispatcher === null) {
                continue;
            }
            $dispatchers[] = [$connection, $dispatcher];
            $connection->setEventDispatcher(clone $dispatcher);
            $connection->listen(function (QueryExecuted $query): void {
                if (preg_match('/^(INSERT|UPDATE|DELETE|ALTER|DROP|TRUNCATE|CREATE)\\b/i', ltrim($query->sql)) === 1) {
                    $this->mutationDetected = true;
                }
            });
        }

        // The default connection is wrapped by DB::transaction below. Wrap every
        // other connection that was already open in its own transaction so an
        // alternate-connection write is rolled back as well. Connections whose
        // PDO is already inside a transaction (e.g. sharing the default PDO or
        // started by RefreshDatabase) are left alone — they roll back with that
        // outer transaction.
        $default = DB::connection()->getName();
        foreach ($connections as $name => $connection) {
            if ($name === $default) {
                continue;
            }
            $pdo = $connection->getPdo();
            if ($pdo !== null && ! $pdo->inTransaction()) {
                $connection->beginTransaction();
                $alternateTransactions[$name] = $connection;
            }
        }

        try {
            try {
                DB::transaction(function () use ($outletId, $limit): never {
                    $output = $this->external->predict($outletId, $limit);
                    throw new AdapterReadOnlyResult($output);
                });
            } catch (AdapterReadOnlyResult $result) {
                return $result->output;
            } finally {
                $this->rollbackAlternateTransactions($alternateTransactions);
            }
        } finally {
            foreach ($dispatchers as [$connection, $dispatcher]) {
                $connection->setEventDispatcher($dispatcher);
            }
        }

        throw new RuntimeException('External adapter transaction completed unexpectedly.');
    }

    /**
     * @param  array<string, \Illuminate\Database\Connection>  $connections
     */
    private function rollbackAlternateTransactions(array $connections): void
    {
        foreach ($connections as $connection) {
            if ($connection->transactionLevel() > 0) {
                $connection->rollBack();
            }
        }
    }

    private function isCircuitOpen(): bool
    {
        $state = $this->getCircuitState();
        $openedAt = $state['opened_at'];
        if ($openedAt === null) {
            return false;
        }

        $cooldown = max(0, (int) config('ai_actions.ml_adapter.cooldown_seconds', 60));
        if ((time() - $openedAt) < $cooldown) {
            return true;
        }

        // Cooldown elapsed: reset state
        $this->setCircuitState(['failures' => 0, 'opened_at' => null]);
        return false;
    }

    private function recordExternalFailure(): void
    {
        $state = $this->getCircuitState();
        $state['failures']++;
        $threshold = max(1, (int) config('ai_actions.ml_adapter.failure_threshold', 3));
        if ($state['failures'] >= $threshold) {
            $state['opened_at'] = time();
        }
        $this->setCircuitState($state);
    }

    private function recordExternalSuccess(): void
    {
        $this->setCircuitState(['failures' => 0, 'opened_at' => null]);
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

    private function circuitKey(): string
    {
        $key = config('ai_actions.ml_adapter.circuit_key');

        return is_string($key) && $key !== '' ? $key : self::CIRCUIT_KEY_DEFAULT;
    }

    /**
     * @return array{failures: int, opened_at: int|null}
     */
    private function getCircuitState(): array
    {
        $state = Cache::get($this->circuitKey());
        if (! is_array($state)) {
            return ['failures' => 0, 'opened_at' => null];
        }

        return [
            'failures' => (int) ($state['failures'] ?? 0),
            'opened_at' => isset($state['opened_at']) ? (int) $state['opened_at'] : null,
        ];
    }

    /**
     * @param  array{failures: int, opened_at: int|null}  $state
     */
    private function setCircuitState(array $state): void
    {
        Cache::forever($this->circuitKey(), $state);
    }
}

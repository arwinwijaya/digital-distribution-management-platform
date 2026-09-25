<?php

namespace App\Services\Recommendation;

use App\Services\RecommendationService;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

/**
 * Resolves the configured model adapter and guards callers from external failure.
 *
 * The resolver is the only place where an external adapter may fail. Every
 * failure (exception, timeout, mutation attempt, or schema violation) is
 * converted into a deterministic fallback result with explicit flags.
 * External adapters receive only the plain read data/services already exposed
 * by their adapter contract; this resolver never passes write services.
 */
class RecommendationModelAdapterResolver implements RecommendationModelAdapter
{
    private int $consecutiveFailures = 0;

    private ?int $circuitOpenedAt = null;

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

        foreach ($connections as $connection) {
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
            DB::transaction(function () use ($outletId, $limit): never {
                $output = $this->external->predict($outletId, $limit);
                throw new AdapterReadOnlyResult($output);
            });
        } catch (AdapterReadOnlyResult $result) {
            $this->rollbackAlternateTransactions($alternateTransactions);

            return $result->output;
        } catch (Throwable $throwable) {
            $this->rollbackAlternateTransactions($alternateTransactions);

            throw $throwable;
        }

        $this->rollbackAlternateTransactions($alternateTransactions);

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
        if ($this->circuitOpenedAt === null) {
            return false;
        }

        $cooldown = max(0, (int) config('ai_actions.ml_adapter.cooldown_seconds', 60));
        if ((time() - $this->circuitOpenedAt) < $cooldown) {
            return true;
        }

        $this->circuitOpenedAt = null;
        $this->consecutiveFailures = 0;

        return false;
    }

    private function recordExternalFailure(): void
    {
        $this->consecutiveFailures++;
        $threshold = max(1, (int) config('ai_actions.ml_adapter.failure_threshold', 3));
        if ($this->consecutiveFailures >= $threshold) {
            $this->circuitOpenedAt = time();
        }
    }

    private function recordExternalSuccess(): void
    {
        $this->consecutiveFailures = 0;
        $this->circuitOpenedAt = null;
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

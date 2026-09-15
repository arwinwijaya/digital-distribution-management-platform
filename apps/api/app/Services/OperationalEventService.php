<?php

namespace App\Services;

use App\Models\OperationalEvent;
use Illuminate\Support\Facades\Log;
use Throwable;

class OperationalEventService
{
    /**
     * @var array<int, string>
     */
    private const SECRET_KEYS = [
        'password',
        'password_confirmation',
        'token',
        'authorization',
        'auth',
        'phone',
        'credential',
        'payment',
        'card',
        'secret',
        'bearer',
    ];

    public function record(
        string $correlationId,
        string $route,
        ?string $action,
        ?int $actorId,
        int $statusCode,
        string $errorClass = null,
        Throwable $exception = null,
    ): void {
        $outcome = $statusCode >= 400 || $exception !== null ? OperationalEvent::OUTCOME_FAILURE : OperationalEvent::OUTCOME_SUCCESS;

        $errorClass = $exception !== null
            ? get_class($exception)
            : ($errorClass ?? ($outcome === OperationalEvent::OUTCOME_FAILURE ? 'HttpError' : null));

        $metadata = $this->buildRedactedMetadata($route, $action);

        try {
            OperationalEvent::query()->create([
                'correlation_id' => substr($correlationId, 0, 100),
                'route' => substr($route, 0, 512),
                'action' => $action !== null ? substr($action, 0, 255) : null,
                'actor_id' => $actorId,
                'status_code' => $statusCode,
                'outcome' => $outcome,
                'error_class' => $errorClass !== null ? substr($errorClass, 0, 255) : null,
                'occurred_at' => now(),
                'metadata' => $metadata,
            ]);
        } catch (Throwable $e) {
            Log::warning('operational_event journal write failed', [
                'correlation_id' => $correlationId,
                'route' => $route,
                'error' => get_class($e),
            ]);
        }
    }

    /**
     * Build redacted metadata without storing sensitive request body/header values.
     *
     * @return array<string, mixed>
     */
    private function buildRedactedMetadata(string $route, ?string $action): array
    {
        return [
            'route' => $route,
            'action' => $action,
            'recorded_at' => now()->toIso8601String(),
        ];
    }

    /**
     * Keep redaction centralized here; if extended, callers must pass values
     * through this method rather than persisting raw request inputs.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function redact(array $payload): array
    {
        $redacted = [];
        foreach ($payload as $key => $value) {
            $lower = strtolower((string) $key);
            $isSecret = false;
            foreach (self::SECRET_KEYS as $secret) {
                if (str_contains($lower, $secret)) {
                    $isSecret = true;
                    break;
                }
            }
            $redacted[$key] = $isSecret ? '[REDACTED]' : (is_array($value) ? $this->redact($value) : $value);
        }

        return $redacted;
    }
}

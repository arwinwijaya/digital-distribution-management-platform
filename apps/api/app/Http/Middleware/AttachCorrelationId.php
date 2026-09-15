<?php

namespace App\Http\Middleware;

use App\Services\OperationalEventService;
use App\Services\PrePilotFeatureGate;
use Closure;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class AttachCorrelationId
{
    public const HEADER = 'X-Correlation-ID';

    public const MAX_LENGTH = 100;

    public const PATTERN = '/\A[A-Za-z0-9._:\-]{1,100}\z/';

    private const RECORDED_ATTRIBUTE = 'pre_pilot.operational_event_recorded';

    public function __construct(
        private readonly PrePilotFeatureGate $gate,
        private readonly OperationalEventService $events,
    ) {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $correlationId = self::resolve($request->headers->get(self::HEADER));
        $request->attributes->set('correlation_id', $correlationId);
        Log::withContext(['correlation_id' => $correlationId]);

        try {
            $response = $next($request);
        } catch (Throwable $e) {
            return $this->renderFailure($request, $e, $correlationId);
        }

        $response->headers->set(self::HEADER, $correlationId);
        $this->recordOnce($request, $correlationId, $response->getStatusCode());

        return $response;
    }

    public static function resolve(?string $candidate): string
    {
        if ($candidate !== null && preg_match(self::PATTERN, $candidate) === 1) {
            return $candidate;
        }

        return Str::uuid()->toString();
    }

    private function renderFailure(Request $request, Throwable $e, string $correlationId): Response
    {
        try {
            app(ExceptionHandler::class)->report($e);
        } catch (Throwable) {
            // Reporting must never change the request outcome.
        }

        try {
            $response = app(ExceptionHandler::class)->render($request, $e);
        } catch (Throwable) {
            $this->recordOnce($request, $correlationId, 500, $e);

            throw $e;
        }

        $response->headers->set(self::HEADER, $correlationId);
        $this->recordOnce($request, $correlationId, $response->getStatusCode(), $e);

        return $response;
    }

    private function recordOnce(Request $request, string $correlationId, int $statusCode, ?Throwable $e = null): void
    {
        if ($request->attributes->get(self::RECORDED_ATTRIBUTE, false)) {
            return;
        }
        $request->attributes->set(self::RECORDED_ATTRIBUTE, true);

        if (! $this->gate->isEnabled()) {
            return;
        }

        $actorId = null;
        try {
            $actorId = $request->user()?->id;
        } catch (Throwable) {
            $actorId = null;
        }

        $route = $request->route();
        $routeName = $route !== null
            ? $request->method().' '.$route->uri()
            : $request->method().' '.$request->path();
        $action = null;
        try {
            $action = $route?->getActionName();
        } catch (Throwable) {
            $action = null;
        }

        // Journal persistence is fail-open: OperationalEventService logs safely
        // and never throws, so the original request outcome is preserved.
        $this->events->record(
            $correlationId,
            $routeName,
            $action,
            is_numeric($actorId) ? (int) $actorId : null,
            $statusCode,
            $e !== null ? get_class($e) : null,
            $e,
        );
    }
}

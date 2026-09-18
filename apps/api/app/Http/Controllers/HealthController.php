<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;

/**
 * Unauthenticated liveness/readiness endpoint.
 *
 * Implemented as an invokable controller (not a route closure) so that
 * `php artisan route:cache` works in production — route caching cannot serialize
 * closures and aborts the whole build step otherwise.
 */
class HealthController extends Controller
{
    public function __invoke(): JsonResponse
    {
        return response()->json([
            'status' => 'healthy',
            'timestamp' => now()->toIso8601String(),
        ]);
    }
}

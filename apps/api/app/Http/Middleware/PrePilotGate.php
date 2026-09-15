<?php

namespace App\Http\Middleware;

use App\Services\PrePilotFeatureGate;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PrePilotGate
{
    public function __construct(
        private readonly PrePilotFeatureGate $gate,
    ) {
    }

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->gate->isEnabled()) {
            return response()->json([
                'status' => 'error',
                'code' => 'pre_pilot_disabled',
            ], 503);
        }

        return $next($request);
    }
}

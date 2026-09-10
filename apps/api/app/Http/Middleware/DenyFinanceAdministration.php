<?php

namespace App\Http\Middleware;

use App\Services\FinanceAuthorizationService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class DenyFinanceAdministration
{
    public function handle(Request $request, Closure $next): Response
    {
        if (app(FinanceAuthorizationService::class)->isFinance($request->user())) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized.',
            ], 403);
        }

        return $next($request);
    }
}

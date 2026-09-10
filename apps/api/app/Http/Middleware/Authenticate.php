<?php

namespace App\Http\Middleware;

use App\Services\AuthService;
use App\Services\FinanceAuthorizationService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class Authenticate
{
    protected AuthService $authService;

    public function __construct(AuthService $authService)
    {
        $this->authService = $authService;
    }

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();

        if (!$token) {
            return response()->json([
                'status' => 'error',
                'message' => 'Token not provided.',
            ], 401);
        }

        $user = $this->authService->validateToken($token);

        if (!$user) {
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid token.',
            ], 401);
        }

        $request->setUserResolver(fn () => $user);

        if (
            app(FinanceAuthorizationService::class)->isFinance($user)
            && !$request->is('api/finance/access')
        ) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthorized.',
            ], 403);
        }

        return $next($request);
    }
}

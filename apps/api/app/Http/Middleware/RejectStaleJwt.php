<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Tymon\JWTAuth\Facades\JWTAuth;

/**
 * On every authenticated request, compare the JWT `jwt_version` claim
 * against the user's current `jwt_version` in the database.
 *
 * A mismatch means the token was issued before the user's role was
 * changed (or before jwt_version was incremented), so it is stale
 * and the client must re-login.
 */
class RejectStaleJwt
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            return $next($request);
        }

        try {
            $payload = JWTAuth::getPayload();
        } catch (\Exception) {
            return $next($request);
        }

        $claimVersion = $payload->get('jwt_version');

        if ($claimVersion === null) {
            // Legacy tokens issued before jwt_version was added — allow.
            return $next($request);
        }

        $dbVersion = (int) ($user->jwt_version ?? 0);

        if ((int) $claimVersion !== $dbVersion) {
            return response()->json([
                'status' => 'error',
                'message' => 'Token invalid due to role change. Please log in again.',
            ], 401);
        }

        return $next($request);
    }
}

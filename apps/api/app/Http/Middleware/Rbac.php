<?php

namespace App\Http\Middleware;

use App\Models\RoleMenuAccess;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Per-route RBAC guard.
 *
 * Usage: ->middleware('rbac:<menu_key>:<required_level>')
 * e.g.   ->middleware('rbac:products:read')
 *
 * The stored level for (user.role, menu_key) is compared against the required
 * level using the ordinal none < read < edit. A missing row means `none`.
 *
 * Must run after `auth:api` (and `reject.stale_jwt`) so that an unauthenticated
 * request is rejected with 401 before any RBAC decision is made.
 *
 * Parameter parsing note: Laravel splits middleware parameters on commas, so
 * the spec's colon form `rbac:products:read` is delivered as a single
 * "products:read" argument. Both forms are accepted:
 *   - rbac:products:read   (spec form, colon-separated)
 *   - rbac:products,read   (Laravel-idiomatic, comma-separated)
 */
class Rbac
{
    public function handle(Request $request, Closure $next, string $menuKey, ?string $requiredLevel = null): Response
    {
        if ($requiredLevel === null && str_contains($menuKey, ':')) {
            [$menuKey, $requiredLevel] = explode(':', $menuKey, 2);
        }

        $user = $request->user();

        if (! $user) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthenticated.',
            ], 401);
        }

        // Fail closed on a misconfigured route: an unknown required level must
        // never grant access (rank() would otherwise return 0 = none).
        if (! in_array($requiredLevel, RoleMenuAccess::LEVELS, true)) {
            return response()->json([
                'status' => 'error',
                'message' => "Forbidden: unknown level '{$requiredLevel}' on {$menuKey}.",
            ], 403);
        }

        $storedLevel = RoleMenuAccess::query()
            ->where('role', $user->role)
            ->where('menu_key', $menuKey)
            ->value('level') ?? RoleMenuAccess::LEVEL_NONE;

        if (RoleMenuAccess::rank($storedLevel) < RoleMenuAccess::rank((string) $requiredLevel)) {
            return response()->json([
                'status' => 'error',
                'message' => "Forbidden: requires {$requiredLevel} on {$menuKey}.",
            ], 403);
        }

        return $next($request);
    }
}

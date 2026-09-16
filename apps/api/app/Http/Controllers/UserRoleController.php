<?php

namespace App\Http\Controllers;

use App\Http\Requests\AssignRoleRequest;
use App\Models\User;
use App\Policies\UserPolicy;
use App\Services\UserRoleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * General user listing + role assignment (F1).
 *
 *  - GET /admin/users        — admin OR platform_owner
 *  - PATCH /admin/users/{id}/role — platform_owner ONLY (admin denied)
 */
class UserRoleController extends Controller
{
    public function __construct(
        private readonly UserPolicy $policy,
        private readonly UserRoleService $roles,
    ) {
    }

    /**
     * List users with optional role filter and limit+1 pagination.
     *
     * Query params:
     *   - role:  one of admin,supplier,outlet,sales,driver,finance,platform_owner
     *   - limit: 1-100, default 20
     *
     * Response contract: { status, data: [], meta: { limit, has_more } }
     */
    public function index(Request $request): JsonResponse
    {
        abort_unless($this->policy->viewAny($request->user()), 403, 'Unauthorized.');

        $request->validate([
            'role'  => ['sometimes', 'string', 'in:admin,supplier,outlet,sales,driver,finance,platform_owner'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $limit = (int) $request->input('limit', 20);

        $query = User::query()->orderBy('id');

        if ($request->filled('role')) {
            $query->where('role', $request->input('role'));
        }

        // limit+1 technique: fetch one extra row to determine has_more.
        $rows = $query->limit($limit + 1)->get();
        $hasMore = $rows->count() > $limit;
        $data = $rows->take($limit)->values();

        return response()->json([
            'status' => 'success',
            'data'   => $data,
            'meta'   => [
                'limit'    => $limit,
                'has_more' => $hasMore,
            ],
        ]);
    }

    /**
     * Assign a role to a user. platform_owner-only; audit + JWT invalidation
     * handled by {@see UserRoleService::assign()}.
     */
    public function assignRole(AssignRoleRequest $request, int $userId): JsonResponse
    {
        $user = $this->roles->assign($request->user(), $userId, $request->validated('role'));

        return response()->json([
            'status' => 'success',
            'data'   => [
                'user' => $user,
                'role' => $user->role,
            ],
        ]);
    }
}

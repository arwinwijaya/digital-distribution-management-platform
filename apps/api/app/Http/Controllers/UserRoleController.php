<?php

namespace App\Http\Controllers;

use App\Http\Requests\AssignRoleRequest;
use App\Models\User;
use App\Policies\UserPolicy;
use App\Services\UserRoleService;
use App\Support\ListQuery;
use Illuminate\Database\Eloquent\Builder;
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
     * List users with optional role filter and cursor-limit pagination (limit+1).
     *
     * Query params:
     *   - role:   one of admin,supplier,outlet,sales,driver,finance,platform_owner
     *   - limit:  1-100, default 20
     *   - cursor: offset, default 0
     *
     * Default order: created_at DESC (nulls last) + id DESC tiebreak.
     * Response contract: { status, data: [], meta: { has_more, limit, cursor, total } }
     */
    public function index(Request $request): JsonResponse
    {
        abort_unless($this->policy->viewAny($request->user()), 403, 'Unauthorized.');

        $request->validate([
            'role'  => ['sometimes', 'string', 'in:admin,supplier,outlet,sales,driver,finance,platform_owner'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $limit = (int) $request->input('limit', 20);
        $cursor = max((int) $this->scalarQueryString($request, 'cursor', '0'), 0);

        $query = $this->applyListFilters(User::query(), $request);

        // Aggregate total derives from the SAME filtered builder (before limit/offset).
        $meta = array_merge(
            [
                'limit'  => $limit,
                'cursor' => $cursor,
            ],
            $this->buildListMeta(clone $query),
        );

        // limit+1 technique: fetch one extra row to determine has_more.
        $rows = $query
            ->orderByRaw(ListQuery::rawOrder('created_at', 'desc'))
            ->limit($limit + 1)
            ->offset($cursor)
            ->get();

        $hasMore = $rows->count() > $limit;
        $data = $hasMore ? $rows->take($limit)->values() : $rows->values();

        $meta = array_merge(['has_more' => $hasMore], $meta);

        return response()->json([
            'status' => 'success',
            'data'   => $data,
            'meta'   => $meta,
        ]);
    }

    /**
     * Apply the list filters (role) to the given user query and return it.
     */
    private function applyListFilters(Builder $query, Request $request): Builder
    {
        if ($request->filled('role')) {
            $query->where('role', $request->input('role'));
        }

        return $query;
    }

    /**
     * Read a query param only when it is a scalar string, otherwise return the
     * default. Guards against array input (`?cursor[]=x`) which would otherwise
     * raise an "Array to string conversion" error and yield HTTP 500.
     */
    private function scalarQueryString(Request $request, string $key, string $default): string
    {
        $value = $request->query($key, $default);

        return is_string($value) ? $value : $default;
    }

    /**
     * Build the aggregate meta payload from the SAME filtered builder (never
     * counts the limited rows).
     *
     * @return array<string, mixed>
     */
    private function buildListMeta(Builder $query): array
    {
        return ListQuery::meta((clone $query)->count());
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

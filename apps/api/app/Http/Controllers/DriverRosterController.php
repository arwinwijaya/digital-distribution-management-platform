<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreDriverProfileRequest;
use App\Http\Requests\UpdateDriverProfileRequest;
use App\Models\DriverProfile;
use App\Support\ListQuery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Admin driver roster CRUD (Phase 8, T5).
 *
 * All routes are guarded by `rbac:driver_roster:<level>`; the list follows the
 * shared admin-table contract (offset cursor + `meta.total`/`meta.summary`).
 */
class DriverRosterController extends Controller
{
    /**
     * Sortable columns allowlist (invalid values silently fall back to default).
     */
    private const SORT_ALLOWLIST = ['created_at', 'plate_number', 'id'];

    private const RELATIONS = ['user:id,name,email,role,is_active', 'serviceTerritory:id,name,code'];

    /**
     * Admin roster listing with filters and offset-cursor pagination (limit+1).
     */
    public function index(Request $request): JsonResponse
    {
        $query = $this->applyListFilters(DriverProfile::query()->with(self::RELATIONS), $request);

        $limit = min(max((int) $request->query('limit', 15), 1), 100);
        $cursor = ListQuery::offset((int) ListQuery::scalarString($request, 'cursor', '0'), $limit);

        [$sortColumn, $sortOrder] = ListQuery::resolveSort(
            self::SORT_ALLOWLIST,
            ListQuery::scalarString($request, 'sort', ''),
            ListQuery::scalarString($request, 'order', 'desc'),
            'created_at',
            'desc',
        );

        $meta = array_merge(
            ['limit' => $limit, 'cursor' => $cursor],
            $this->buildListMeta(clone $query),
        );

        $rows = $query
            ->orderByRaw(ListQuery::rawOrder($sortColumn, $sortOrder))
            ->limit($limit + 1)
            ->offset($cursor)
            ->get();

        $hasMore = $rows->count() > $limit;
        $data = $hasMore ? $rows->take($limit)->values() : $rows->values();

        $meta = array_merge(['has_more' => $hasMore], $meta);

        return response()->json([
            'status' => 'success',
            'data' => $data,
            'meta' => $meta,
        ]);
    }

    public function store(StoreDriverProfileRequest $request): JsonResponse
    {
        $profile = DriverProfile::create([
            ...$request->validated(),
            'is_available' => $request->boolean('is_available', true),
        ]);

        return response()->json([
            'status' => 'success',
            'data' => $profile->load(self::RELATIONS),
        ], 201);
    }

    public function update(UpdateDriverProfileRequest $request, int $id): JsonResponse
    {
        $profile = DriverProfile::findOrFail($id);
        $profile->update($request->validated());

        return response()->json([
            'status' => 'success',
            'data' => $profile->fresh(self::RELATIONS),
        ]);
    }

    /**
     * Remove the roster profile only; the underlying user is never deleted.
     */
    public function destroy(int $id): JsonResponse
    {
        $profile = DriverProfile::findOrFail($id);
        $profile->delete();

        return response()->json(['status' => 'success', 'data' => null]);
    }

    /**
     * Apply the list filters (search / is_available / service_territory_id).
     */
    private function applyListFilters(Builder $query, Request $request): Builder
    {
        if (($search = $request->query('search')) !== null && $search !== '') {
            $term = '%'.str_replace(['%', '_'], ['\\%', '\\_'], (string) $search).'%';
            $query->where(function (Builder $inner) use ($term): void {
                $inner->where('plate_number', 'like', $term)
                    ->orWhereHas('user', fn (Builder $user) => $user->where('name', 'like', $term));
            });
        }

        if (($isAvailable = $request->query('is_available')) !== null && $isAvailable !== '') {
            $flag = filter_var($isAvailable, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($flag !== null) {
                $query->where('is_available', $flag);
            }
        }

        if (($territoryId = $request->query('service_territory_id')) !== null && $territoryId !== '') {
            $query->where('service_territory_id', (int) $territoryId);
        }

        return $query;
    }

    /**
     * Build the aggregate meta payload from the SAME filtered builder.
     *
     * @return array<string, mixed>
     */
    private function buildListMeta(Builder $query): array
    {
        $total = (clone $query)->count();
        $available = (clone $query)->where('is_available', true)->count();
        $unavailable = (clone $query)->where('is_available', false)->count();

        return ListQuery::meta($total, [
            'available' => $available,
            'unavailable' => $unavailable,
        ]);
    }
}

/**
 * Admin table helpers — PURE module (no React, no component imports).
 *
 * Shared by the 6 admin pages, their `api.ts` dummy branches and `Table.tsx`:
 * sort allowlist mirror, sort/order normalization, nulls-last comparator,
 * offset pagination, date formatting and paging count labels.
 */

/** Sort direction shared by every table helper. */
export type SortOrder = 'asc' | 'desc';

/** Row density options for the admin table. */
export type TableDensity = 'compact' | 'default' | 'comfortable';

/**
 * Frontend MIRROR of the backend sort allowlist (one source of truth:
 * `App\Support\ListQuery` consumers + the per-controller allowlists documented
 * in `docs/pocket/spec/2026-09-17-admin-table-ux/admin-table-readability.md`).
 *
 * Keep this in sync with the backend controllers; an out-of-sync entry means a
 * column shown as sortable would silently fall back on the server.
 */
export const SORT_ALLOWLISTS = {
  outlets: ['created_at', 'updated_at', 'name', 'id', 'category', 'score'],
  products: ['created_at', 'updated_at', 'name', 'sku', 'price', 'stock_quantity', 'id'],
  users: ['created_at', 'updated_at', 'name', 'email', 'role', 'id'],
  promotions: ['created_at', 'updated_at', 'start_date', 'end_date', 'id'],
  orders: ['created_at', 'updated_at', 'order_id', 'status', 'total_amount', 'id'],
  'sales-performance': ['name', 'id'],
} as const;

export type SortAllowlistKey = keyof typeof SORT_ALLOWLISTS;

/** Default sort column when a requested column is not allowed. */
const DEFAULT_SORT_COLUMN = 'created_at';

/** Normalize an order string to `asc`/`desc` (case-insensitive); default desc. */
function normalizeOrder(order: string | null | undefined): SortOrder {
  return String(order ?? '').toLowerCase() === 'asc' ? 'asc' : 'desc';
}

/**
 * Resolve a requested `sort`/`order` against an allowlist, mirroring the
 * backend `ListQuery::resolveSort` fallback: unknown columns fall back to
 * `created_at`, invalid orders fall back to `desc` (never throws).
 */
export function normalizeSort(
  allowlist: readonly string[],
  sort: string,
  order: string | null | undefined = 'desc',
): { sort: string; order: SortOrder } {
  const normalizedOrder = normalizeOrder(order);
  if (allowlist.includes(sort)) {
    return { sort, order: normalizedOrder };
  }
  return { sort: DEFAULT_SORT_COLUMN, order: normalizedOrder };
}

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

function isNullish(value: unknown): boolean {
  return value === null || value === undefined || value === '';
}

/** Deterministic tiebreaker: higher id first (id DESC), regardless of sort direction. */
function idDesc(a: unknown, b: unknown): number {
  const aId = Number(a ?? 0) || 0;
  const bId = Number(b ?? 0) || 0;
  return bId - aId;
}

/** Compare two non-null sort values (numbers numerically, otherwise as strings). */
function compareValues(a: unknown, b: unknown): number {
  if (typeof a === 'number' && typeof b === 'number') {
    return a - b;
  }
  return String(a).localeCompare(String(b), undefined, { numeric: true, sensitivity: 'base' });
}

/**
 * Comparator for admin table rows. Nulls (null/undefined/empty string) always
 * sort LAST on BOTH `asc` and `desc`; equal values tiebreak on `id` DESC.
 * Use with `Array.prototype.sort`.
 */
export function compareRows<T extends Record<string, unknown>>(
  a: T,
  b: T,
  sort: string,
  order: SortOrder = 'desc',
): number {
  const aValue = a[sort];
  const bValue = b[sort];
  const aNull = isNullish(aValue);
  const bNull = isNullish(bValue);

  if (aNull && bNull) return idDesc(a.id, b.id);
  if (aNull) return 1; // nulls always last
  if (bNull) return -1;

  let cmp = compareValues(aValue, bValue);
  if (order === 'desc') cmp = -cmp;
  return cmp !== 0 ? cmp : idDesc(a.id, b.id);
}

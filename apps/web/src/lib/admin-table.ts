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

/** Offset-paginated page + metadata (cursor = offset, mirrors the backend contract). */
export interface PaginateResult<T> {
  page: T[];
  total: number;
  hasMore: boolean;
  nextCursor: number;
}

/**
 * Slice a page out of a fully-loaded list using an offset `cursor` + `limit`,
 * matching the backend's offset pagination (cursor 0, limit, 2*limit, ...).
 */
export function paginate<T>(items: readonly T[], cursor: number, limit: number): PaginateResult<T> {
  const size = limit > 0 ? limit : 1;
  const start = Math.max(0, cursor);
  const page = items.slice(start, start + size);
  const nextCursor = start + size;
  return {
    page,
    total: items.length,
    hasMore: nextCursor < items.length,
    nextCursor,
  };
}

const EM_DASH = '\u2014';

const dateTimeFormatter = new Intl.DateTimeFormat('id-ID', {
  dateStyle: 'medium',
  timeStyle: 'short',
});

/**
 * Format a timestamp as a local `id-ID` date+time. Null/undefined/empty and
 * malformed values render as an em dash so cells never show "Invalid Date".
 */
export function formatDateTime(
  value: string | number | Date | null | undefined,
): string {
  if (value === null || value === undefined || value === '') return EM_DASH;
  const date = value instanceof Date ? value : new Date(value);
  if (Number.isNaN(date.getTime())) return EM_DASH;
  return dateTimeFormatter.format(date);
}

/** Inputs for the paging position label. */
export interface CountLabelInput {
  total?: number | null;
  cursor: number;
  limit: number;
  hasMore?: boolean;
}

/**
 * Build the paging position label. When `total` is known the label claims a
 * page count and total; when it is absent we fall back to an ESTIMATE that
 * never invents a total ("Halaman 2 · ada data lain").
 */
export function buildCountLabel({ total, cursor, limit, hasMore }: CountLabelInput): string {
  const size = limit > 0 ? limit : 1;
  const page = Math.floor(Math.max(0, cursor) / size) + 1;

  if (typeof total === 'number' && Number.isFinite(total)) {
    const totalPages = Math.max(1, Math.ceil(total / size));
    return `Halaman ${page} dari ${totalPages} · ${total} data`;
  }

  if (hasMore) {
    return `Halaman ${page} · ada data lain`;
  }

  return `Halaman ${page}`;
}

/** Component-level sort state (`column` naming) used by `Table` header toggling. */
export interface ColumnSort {
  column: string;
  order: SortOrder;
}

/**
 * The SINGLE source of sort-direction logic for table headers: clicking the
 * active column flips its order (desc → asc → desc); clicking a different
 * column starts it at `desc` (newest-first default).
 */
export function toggleSort(current: ColumnSort, column: string): ColumnSort {
  if (current.column === column) {
    return { column, order: current.order === 'asc' ? 'desc' : 'asc' };
  }
  return { column, order: 'desc' };
}

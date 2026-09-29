/** Pure, deterministic filtering helpers for the geographic snapshot. */

export interface SnapshotWindow {
  start: string;
  end: string;
  timezone: string;
}

export interface DateRange {
  start: string;
  end: string;
}

export type Period = 'today' | '7d' | '30d';
export type EligibleStatus = 'New' | 'Confirmed' | 'Delivered' | 'Partially Paid';

export const ELIGIBLE_STATUSES: readonly EligibleStatus[] = [
  'New',
  'Confirmed',
  'Delivered',
  'Partially Paid',
];

const DEFAULT_STATUSES: readonly EligibleStatus[] = ['New', 'Confirmed'];
const SEMUA = 'Semua';

function daysInMonth(year: number, month: number): number {
  if (month === 2) {
    return year % 4 === 0 && (year % 100 !== 0 || year % 400 === 0) ? 29 : 28;
  }
  return [4, 6, 9, 11].includes(month) ? 30 : 31;
}

/** Shift an ISO calendar date without timezone conversion or Date arithmetic. */
function shiftDate(date: string, deltaDays: number): string {
  const [yearPart, monthPart, dayPart] = date.split('-');
  let year = Number(yearPart);
  let month = Number(monthPart);
  let day = Number(dayPart);
  const direction = deltaDays < 0 ? -1 : 1;

  for (let remaining = Math.abs(deltaDays); remaining > 0; remaining -= 1) {
    day += direction;
    if (direction < 0 && day < 1) {
      month -= 1;
      if (month < 1) {
        month = 12;
        year -= 1;
      }
      day = daysInMonth(year, month);
    } else if (direction > 0 && day > daysInMonth(year, month)) {
      day = 1;
      month += 1;
      if (month > 12) {
        month = 1;
        year += 1;
      }
    }
  }

  return `${String(year).padStart(4, '0')}-${String(month).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
}

/** Map a supported period to an inclusive range inside the snapshot window. */
export function periodToRange(window: SnapshotWindow, period: Period | string): DateRange {
  switch (period) {
    case 'today':
      return { start: window.end, end: window.end };
    case '7d':
      return { start: shiftDate(window.end, -6), end: window.end };
    case '30d':
    default:
      return { start: window.start, end: window.end };
  }
}

/** Expand the Semua selection; named selections retain their input order. */
export function expandSemua(statuses: readonly string[]): string[] {
  if (statuses.includes(SEMUA)) return [...ELIGIBLE_STATUSES];
  return [...statuses];
}

/** Per-outlet filtered counts derived from status maps or daily buckets. */
export interface FilteredCounts {
  filteredOrders: number;
  statusCounts: Record<string, number>;
  salesCents: number;
  legacyOnly: boolean;
  hasDailyDetail: boolean;
}

interface DailyBucket {
  date: string;
  counts?: Record<string, number>;
  sales?: Record<string, string>;
}

/** A geographic map point row; v1 rows simply omit the v2 fields. */
export interface GeographicPoint {
  orders?: number;
  sales?: number | string;
  orders_by_status?: Record<string, number> | null;
  sales_by_status?: Record<string, string> | null;
  daily_by_status?: DailyBucket[] | null;
}

/** Normalize an arbitrary period value; unknown periods fall back to the full 30d window. */
function normalizePeriod(period: string): Period {
  if (period === 'today' || period === '7d') return period;
  return '30d';
}

function toCount(value: number | string | null | undefined): number {
  const count = Number(value);
  return Number.isFinite(count) ? count : 0;
}

/** Parse a money value into integer cents (mirrors the dummy aggregates `toCents` convention). */
function toCents(value: number | string | null | undefined): number {
  if (value === null || value === undefined) return 0;
  const cleaned = String(value).replace(/[^0-9.-]/g, '');
  if (cleaned === '' || cleaned === '-' || cleaned === '.') return 0;
  const numeric = Number(cleaned);
  return Number.isFinite(numeric) ? Math.round(numeric * 100) : 0;
}

/**
 * Compose the active status selection with the active period for one outlet row.
 *
 * `30d` (the default/unknown-period range) uses the snapshot status-map totals; `7d` and
 * `today` slice `daily_by_status` to the inclusive in-range dates. Money is summed as integer
 * cents. v1 rows without status/day maps fall back to a safe empty result.
 */
export function computeFilteredCounts(
  point: GeographicPoint,
  statuses: readonly string[],
  period: Period | string,
  window: SnapshotWindow,
): FilteredCounts {
  const selected = normalizeStatuses(statuses);
  const effectivePeriod = normalizePeriod(period);
  const range = periodToRange(window, effectivePeriod);
  const statusCounts: Record<string, number> = {};
  let filteredOrders = 0;
  let salesCents = 0;

  if (effectivePeriod === '30d') {
    const orders = point.orders_by_status ?? {};
    const sales = point.sales_by_status ?? {};
    for (const status of selected) {
      const count = toCount(orders[status]);
      statusCounts[status] = count;
      filteredOrders += count;
      salesCents += toCents(sales[status]);
    }
  } else {
    for (const status of selected) statusCounts[status] = 0;
    const buckets = Array.isArray(point.daily_by_status) ? point.daily_by_status : [];
    for (const bucket of buckets) {
      if (bucket.date < range.start || bucket.date > range.end) continue;
      for (const status of selected) {
        const count = toCount(bucket.counts?.[status]);
        statusCounts[status] += count;
        filteredOrders += count;
        salesCents += toCents(bucket.sales?.[status]);
      }
    }
  }

  const hasStatusMap = point.orders_by_status != null;
  const hasDailyDetail = Array.isArray(point.daily_by_status);
  const legacyOnly = !hasStatusMap && !hasDailyDetail;

  // Legacy v1 rows carry only full-window totals: surface them for 30d so the
  // marker stays visible with its legacy badge, while narrow periods (7d/today)
  // keep the zero result because no daily detail exists to slice. An empty
  // status selection still means zero selected orders — the legacy total is only
  // the badge for the selected statuses, which v1 rows cannot attribute.
  if (legacyOnly && effectivePeriod === '30d' && selected.length > 0) {
    filteredOrders = toCount(point.orders);
    salesCents = toCents(point.sales);
  }

  return {
    filteredOrders,
    statusCounts,
    salesCents,
    legacyOnly,
    hasDailyDetail,
  };
}

/** Normalize status selections, preserving an intentional empty selection. */
export function normalizeStatuses(statuses: readonly string[]): EligibleStatus[] {
  if (statuses.length === 0) return [];

  const expanded = expandSemua(statuses);
  if (expanded.includes(SEMUA)) return [...ELIGIBLE_STATUSES];

  const recognized = expanded.filter((status): status is EligibleStatus =>
    (ELIGIBLE_STATUSES as readonly string[]).includes(status),
  );
  const unique = recognized.filter((status, index) => recognized.indexOf(status) === index);
  return unique.length > 0 ? unique : [...DEFAULT_STATUSES];
}

/**
 * Delivery list search + filter — PURE module (no React, no component imports).
 *
 * Client-side on purpose: the driver/sales delivery list is small and already
 * loaded in one request (`GET /deliveries` exposes no search/filter params), so
 * filtering in memory avoids a round-trip per keystroke. Mirrors the shape of
 * `lib/product-filter.ts`.
 *
 * Dates are compared as LOCAL `YYYY-MM-DD` strings (`row.assigned_date`), which
 * is both lexicographic == chronological and timezone-safe: `assigned_at` is
 * UTC-serialized, so slicing it would misfile 00:00–06:59 WIB deliveries.
 */
import type { Delivery } from '@/app/delivery/api';

export interface DeliveryFilterInput {
  /** Case-insensitive substring over order code / outlet name / recipient / driver. */
  search?: string;
  /** Exact status match (accepts both real and dummy vocabularies). */
  status?: string;
  /** Inclusive lower bound, `YYYY-MM-DD`. */
  dateFrom?: string;
  /** Inclusive upper bound, `YYYY-MM-DD`. */
  dateTo?: string;
}

const DATE_PATTERN = /^\d{4}-\d{2}-\d{2}$/;

/** A `YYYY-MM-DD` string, or `null` when blank/malformed (i.e. not applied). */
function normalizeDate(value: string | undefined): string | null {
  if (typeof value !== 'string') return null;
  const trimmed = value.trim();
  return DATE_PATTERN.test(trimmed) ? trimmed : null;
}

/** Case-insensitive substring match; `false` when the haystack is absent. */
function contains(haystack: string | null | undefined, needle: string): boolean {
  return typeof haystack === 'string' && haystack.toLowerCase().includes(needle);
}

/**
 * Filter delivery rows by free-text search, exact status and an inclusive local
 * date range. Blank/whitespace/invalid inputs are ignored (unbounded), so `{}`
 * returns every row. A row with a null `assigned_date` is dropped only when a
 * date bound is actually applied.
 */
export function filterDeliveries(
  rows: readonly Delivery[],
  filters: DeliveryFilterInput = {},
): Delivery[] {
  const search = (filters.search ?? '').trim().toLowerCase();
  const status = (filters.status ?? '').trim();
  const dateFrom = normalizeDate(filters.dateFrom);
  const dateTo = normalizeDate(filters.dateTo);

  // A reversed range can never match — short-circuit to zero rows.
  if (dateFrom && dateTo && dateFrom > dateTo) return [];

  return rows.filter((row) => {
    if (search) {
      const hit =
        contains(row.order?.order_id, search) ||
        contains(row.order?.outlet?.name, search) ||
        contains(row.recipient_name, search) ||
        contains(row.driver?.name, search);
      if (!hit) return false;
    }

    if (status && row.status !== status) return false;

    if (dateFrom || dateTo) {
      const date = row.assigned_date;
      if (!date) return false;
      if (dateFrom && date < dateFrom) return false;
      if (dateTo && date > dateTo) return false;
    }

    return true;
  });
}

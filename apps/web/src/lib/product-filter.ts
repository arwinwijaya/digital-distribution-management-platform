/**
 * Product view + filter helpers — PURE module (no React, no component imports).
 *
 * Shared by the order form product picker and the product catalog:
 * the persisted view-mode union and the in-memory name/price filter.
 *
 * Price filtering is CLIENT-SIDE on purpose: `GET /products` exposes no
 * price-range query param (see `ProductController::applyCatalogFilters`), and
 * both surfaces already load the full catalog (≤100 rows) in one request.
 */

import { parseMoney } from '@/lib/format';

/** Display mode for a product list: card grid (default) or table. */
export type ViewMode = 'card' | 'table';

/** Filter inputs as typed by the user; empty/blank bounds mean "unbounded". */
export interface ProductFilterInput {
  name?: string;
  minPrice?: string | number | null;
  maxPrice?: string | number | null;
}

/**
 * Parse a user-entered price bound into a number, or `null` when it is
 * empty/blank/invalid/negative (i.e. the bound is not applied).
 */
function toBound(value: string | number | null | undefined): number | null {
  if (value === null || value === undefined) return null;
  if (typeof value === 'string' && value.trim() === '') return null;
  const raw = typeof value === 'number' ? value : Number(value.trim());
  if (!Number.isFinite(raw) || raw < 0) return null;
  return raw;
}

/**
 * Filter product rows by case-insensitive name substring and an inclusive
 * price range. Empty/blank/invalid/negative bounds are ignored (unbounded),
 * so `{}` returns every row. Row prices reuse `parseMoney` so fixed-2-decimal
 * API strings (`"20000.00"`) and preformatted values both compare numerically.
 */
export function filterProducts<T extends { name: string; price: string | number }>(
  rows: readonly T[],
  filters: ProductFilterInput = {},
): T[] {
  const name = (filters.name ?? '').trim().toLowerCase();
  const min = toBound(filters.minPrice);
  const max = toBound(filters.maxPrice);

  return rows.filter((row) => {
    if (name && !row.name.toLowerCase().includes(name)) return false;
    const price = parseMoney(row.price);
    if (min !== null && price < min) return false;
    if (max !== null && price > max) return false;
    return true;
  });
}

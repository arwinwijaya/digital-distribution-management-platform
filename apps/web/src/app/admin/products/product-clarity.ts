/**
 * Pure product clarity helpers — display-only module for normalized stock,
 * health classification, and status/category presentation.
 *
 * Consumed by T3–T5; no side effects, no React, no fetch.
 */

/** Stock health classification returned by the backend and mirrored here. */
export type StockHealth = 'out' | 'low' | 'ok';

/** Display status values used in the admin table. */
export type DisplayStatus = 'Aktif' | 'Tidak bisa dibeli' | 'Nonaktif';

/** Normalize a raw stock value: floor fractional, coalesce null/undefined to 0. */
export function normalizeStock(value: number | null | undefined): number {
  if (value === null || value === undefined) return 0;
  return Math.floor(value);
}

/** Classify normalized stock into health buckets. */
export function classifyStockHealth(value: number | null | undefined): StockHealth {
  const n = normalizeStock(value);
  if (n <= 0) return 'out';
  if (n <= 10) return 'low';
  return 'ok';
}

/** Human-readable Indonesian label for stock health. */
export function stockHealthLabel(health: StockHealth): string {
  switch (health) {
    case 'out':
      return 'Habis';
    case 'low':
      return 'Rendah';
    case 'ok':
      return 'Aman';
  }
}

/** Explicit header label for the stock column. */
export const STOCK_UNIT_LABEL = 'Stok (unit)';

/** Explicit label for the stock value in expanded detail. */
export const STOCK_VALUE_LABEL = 'Nilai stok';

/** Static unit note for expanded detail (no UoM in system yet). */
export const STOCK_UNIT_NOTE = 'Satuan belum terdefinisi di sistem';
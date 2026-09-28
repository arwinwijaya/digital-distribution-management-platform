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

/** Em dash used for absent/unknown display values. */
export const EM_DASH = '\u2014';

/** Nested supplier shape returned by `GET /products` (admin contract). */
export interface ProductSupplier {
  id: number;
  name: string;
  subscription_status: string;
}

/** Minimal product shape needed to derive display status. */
export interface ProductClarityInput {
  is_active?: boolean | null;
  supplier?: ProductSupplier | null;
}

/** True when the product should be treated as active (undefined/null ⇒ active). */
function isProductActive(product: ProductClarityInput): boolean {
  return product.is_active !== false;
}

/** True when a supplier exists and is explicitly not active. */
function isSupplierNonActive(supplier: ProductSupplier | null | undefined): boolean {
  return supplier !== null && supplier !== undefined && supplier.subscription_status !== 'active';
}

/**
 * Derive the single display status with precedence
 * `Nonaktif` > `Tidak bisa dibeli` > `Aktif`.
 *
 * - `is_active=false` always wins (Nonaktif).
 * - A non-active supplier marks an active product Tidak bisa dibeli.
 * - An orphan/null supplier is never Tidak bisa dibeli.
 */
export function deriveDisplayStatus(product: ProductClarityInput): DisplayStatus {
  if (!isProductActive(product)) return 'Nonaktif';
  if (isSupplierNonActive(product.supplier)) return 'Tidak bisa dibeli';
  return 'Aktif';
}

/** Facts surfaced in the expanded detail panel (both product + supplier state). */
export interface ProductDetailFacts {
  status: DisplayStatus;
  productActive: boolean;
  supplierName: string;
  supplierStatus: string;
}

/** Build the expanded-detail facts without losing either product or supplier state. */
export function deriveProductDetailFacts(product: ProductClarityInput): ProductDetailFacts {
  const supplier = product.supplier ?? null;
  return {
    status: deriveDisplayStatus(product),
    productActive: isProductActive(product),
    supplierName: supplier ? supplier.name : EM_DASH,
    supplierStatus: supplier ? (supplier.subscription_status === 'active' ? 'Aktif' : 'Tidak aktif') : EM_DASH,
  };
}

/** Category display: trim only (case-sensitive), empty/null ⇒ em dash. */
export function categoryDisplay(category: string | null | undefined): string {
  if (category === null || category === undefined) return EM_DASH;
  const trimmed = category.trim();
  return trimmed === '' ? EM_DASH : trimmed;
}
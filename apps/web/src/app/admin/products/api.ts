import { apiUrl, authHeaders } from '@/lib/api';
import { withDummyRead } from '@/dummy/guards';
import { useDummyStore } from '@/dummy/store';
import { updateDummyProductPrice } from '@/dummy/mutations';
import { compareRows, paginate } from '@/lib/admin-table';
import { SUPPLIER_NAMES } from '@/dummy/seed';
import { normalizeStock, deriveDisplayStatus } from './product-clarity';
import type { ProductSupplier } from './product-clarity';

export type { ProductSupplier } from './product-clarity';

// ── Dummy helpers ───────────────────────────────────────────────────────────
interface DummyProductEntity { sku: string; name: string; category: string; price: number }
interface DummyAllProducts {
  products: DummyProductEntity[];
  orders: Array<{ created_at: string }>;
}

function productsDummy(): DummyAllProducts {
  const entities = useDummyStore.getState().dummyEntities as Partial<DummyAllProducts> | null;
  return { products: entities?.products ?? [], orders: entities?.orders ?? [] };
}

/** Deterministic stock sequence covering all health boundaries. */
function dummyStock(i: number): number {
  const cycle = [0, 5, 10, 11, 40, 120, 3, 25, 90, 7];
  return cycle[i % cycle.length];
}

/** Deterministic supplier projection (null = orphan/none). */
function dummySupplier(i: number): ProductSupplier | null {
  if (i % 4 === 3) return null; // orphan/none
  const idx = i % SUPPLIER_NAMES.length;
  // Mostly active; some expired/suspended for 'unpurchasable' filter parity.
  const status = i % 5 === 1 ? 'expired' : i % 5 === 2 ? 'suspended' : 'active';
  return { id: idx + 1, name: SUPPLIER_NAMES[idx], subscription_status: status };
}

/** Deterministic is_active: 90% active (i % 10 !== 0). */
function dummyActive(i: number): boolean {
  return i % 10 !== 0;
}

function baseDummyProducts(): AdminProduct[] {
  return productsDummy().products.map((p, i) => ({
    id: i + 1,
    name: p.name,
    price: p.price.toFixed(2),
    sku: p.sku,
    stock_quantity: dummyStock(i),
    category: p.category,
    is_active: dummyActive(i),
    supplier: dummySupplier(i),
    description: `Dummy product — ${p.category}`,
  } as AdminProduct));
}

function listDummyProducts(search?: string): AdminProduct[] {
  let list = baseDummyProducts();
  if (search) {
    const q = search.toLowerCase();
    list = list.filter((p) => p.name.toLowerCase().includes(q) || (p.sku ?? '').toLowerCase().includes(q));
  }
  return list;
}

/**
 * Dummy parity for the admin products table: same sort/pagination/summary
 * contract as the real `GET /products` list. Dummy products carry no
 * `created_at`, so the default `created_at DESC` falls back to `id DESC`
 * (nulls-last) through the shared `compareRows` comparator.
 */
function listDummyAdminProducts(filters: ProductFilters): ProductsListResult {
  let list = baseDummyProducts();

  if (filters.search) {
    const q = filters.search.toLowerCase();
    list = list.filter((p) => p.name.toLowerCase().includes(q) || (p.sku ?? '').toLowerCase().includes(q));
  }

  // Category filter: exact match after trim (case-sensitive); the explicit
  // no-category sentinel matches only null/empty-category rows.
  if (typeof filters.category === 'string' && filters.category !== '') {
    if (filters.category === NO_CATEGORY_FILTER) {
      list = list.filter((p) => (p.category ?? '').trim() === '');
    } else {
      list = list.filter((p) => (p.category ?? '').trim() === filters.category);
    }
  }

  // Status filter: 'active', 'inactive', 'unpurchasable'.
  if (filters.status) {
    if (filters.status === 'active') {
      list = list.filter((p) => p.is_active !== false);
    } else if (filters.status === 'inactive') {
      list = list.filter((p) => p.is_active === false);
    } else if (filters.status === 'unpurchasable') {
      list = list.filter((p) => p.supplier !== null && p.supplier !== undefined && p.supplier.subscription_status !== 'active');
    }
  }

  // Stock health filter: floor semantics matching backend.
  if (filters.stockHealth) {
    if (filters.stockHealth === 'out') {
      list = list.filter((p) => normalizeStock(p.stock_quantity) <= 0);
    } else if (filters.stockHealth === 'low') {
      list = list.filter((p) => { const n = normalizeStock(p.stock_quantity); return n >= 1 && n <= 10; });
    } else if (filters.stockHealth === 'ok') {
      list = list.filter((p) => normalizeStock(p.stock_quantity) >= 11);
    }
  }

  // Sorting: custom for category (nulls-last alpha + id DESC tie) and status (derived priority + id DESC tie).
  const sortCol = filters.sort || 'created_at';
  const sortOrder = filters.order === 'asc' ? 'asc' : 'desc';
  const isDesc = sortOrder === 'desc';

  const idDescTie = (a: number, b: number) => b - a;

  if (sortCol === 'category') {
    list = [...list].sort((a, b) => {
      const aVal = (a.category ?? '').trim();
      const bVal = (b.category ?? '').trim();
      const aNull = aVal === '';
      const bNull = bVal === '';
      if (aNull && bNull) return idDescTie(a.id, b.id);
      if (aNull) return 1;
      if (bNull) return -1;
      let cmp = aVal.localeCompare(bVal, undefined, { numeric: true, sensitivity: 'base' });
      if (isDesc) cmp = -cmp;
      return cmp !== 0 ? cmp : idDescTie(a.id, b.id);
    });
  } else if (sortCol === 'status') {
    list = [...list].sort((a, b) => {
      const aPri = deriveDisplayStatus({ is_active: a.is_active, supplier: a.supplier ?? undefined }) === 'Nonaktif' ? 2
        : deriveDisplayStatus({ is_active: a.is_active, supplier: a.supplier ?? undefined }) === 'Tidak bisa dibeli' ? 1 : 0;
      const bPri = deriveDisplayStatus({ is_active: b.is_active, supplier: b.supplier ?? undefined }) === 'Nonaktif' ? 2
        : deriveDisplayStatus({ is_active: b.is_active, supplier: b.supplier ?? undefined }) === 'Tidak bisa dibeli' ? 1 : 0;
      let cmp = aPri - bPri;
      if (isDesc) cmp = -cmp;
      return cmp !== 0 ? cmp : idDescTie(a.id, b.id);
    });
  } else {
    list = [...list].sort((a, b) =>
      compareRows(a as unknown as Record<string, unknown>, b as unknown as Record<string, unknown>, sortCol, sortOrder),
    );
  }

  const total = list.length;
  const outOfStock = list.filter((p) => normalizeStock(p.stock_quantity) <= 0).length;

  const limit = filters.limit ?? 15;
  const cursor = filters.cursor ?? 0;
  const paginated = paginate(list, cursor, limit);

  // Categories from the full filtered set (before pagination), distinct, sorted.
  const seen = new Set<string>();
  for (const p of list) {
    if (!p.category) continue;
    const trimmed = p.category.trim();
    if (trimmed !== '') seen.add(trimmed);
  }
  const categories = Array.from(seen).sort((a, b) =>
    a.localeCompare(b, undefined, { numeric: true, sensitivity: 'base' }),
  );

  return {
    products: paginated.page,
    hasMore: paginated.hasMore,
    limit,
    cursor,
    total,
    summary: { total, out_of_stock: outOfStock },
    categories,
  };
}

function dummyPriceHistory(productId: number, opts?: { limit?: number; cursor?: number }): PriceHistoryResult {
  const product = productsDummy().products[productId - 1];
  const limit = opts?.limit ?? 15;
  const cursor = opts?.cursor ?? 0;
  if (!product) return { data: [], hasMore: false, limit, cursor: 0, nextCursor: null };
  const base = Math.round(product.price / 500) * 500;
  const entries: PriceHistoryEntry[] = Array.from({ length: 5 }, (_, k) => {
    const delta = ((k - 2) * 500 + k * 250);
    const old = Math.max(500, base + delta - 500);
    const newer = Math.max(500, base + delta);
    return {
      id: productId * 100 + k + 1,
      product_id: productId,
      old_price: old.toFixed(2),
      new_price: newer.toFixed(2),
      changed_by: 1,
      changed_at: `2026-02-${String(10 + k).padStart(2, '0')}T10:00:00+07:00`,
    };
  });
  const page = entries.slice(cursor, cursor + limit);
  return {
    data: page,
    hasMore: cursor + limit < entries.length,
    limit,
    cursor,
    nextCursor: cursor + limit < entries.length ? cursor + limit : null,
  };
}

export interface AdminProduct {
  id: number;
  name: string;
  price?: string | number;
  sku?: string;
  stock_quantity?: number;
  category?: string;
  is_active?: boolean;
  supplier_id?: number | null;
  supplier?: ProductSupplier | null;
  description?: string | null;
  created_at?: string | null;
  updated_at?: string | null;
}

/** Explicit server-side category value for products whose category is NULL/empty. */
export const NO_CATEGORY_FILTER = '__none__';

export type ProductStatusFilter = 'active' | 'inactive' | 'unpurchasable';
export type ProductStockHealthFilter = 'out' | 'low' | 'ok';

export interface ProductFilters {
  search?: string;
  category?: string;
  status?: ProductStatusFilter | string;
  stockHealth?: ProductStockHealthFilter | string;
  limit?: number;
  cursor?: number;
  sort?: string;
  order?: string;
  /** Request cancellation is intentionally not serialized into the URL. */
  signal?: AbortSignal;
}

export interface ProductsListResult {
  products: AdminProduct[];
  hasMore: boolean;
  limit: number;
  cursor: number;
  total?: number;
  summary?: { total: number; out_of_stock: number };
  categories?: string[];
}

export interface PriceHistoryEntry {
  id: number;
  product_id: number;
  old_price: string;
  new_price: string;
  changed_by?: number | null;
  changed_at?: string | null;
}

export interface PriceHistoryResult {
  data: PriceHistoryEntry[];
  hasMore: boolean;
  limit: number;
  cursor: number;
  nextCursor: number | null;
}

function parseError(data: unknown, fallback: string): string {
  if (data && typeof data === 'object' && data !== null && 'message' in data) {
    const v = (data as { message?: unknown }).message;
    if (typeof v === 'string') return v;
  }
  return fallback;
}

export async function fetchProducts(token: string, search?: string): Promise<AdminProduct[]> {
  return withDummyRead(
    useDummyStore.getState().isDummy,
    listDummyProducts(search),
    () => fetchProductsReal(token, search),
  );
}

async function fetchProductsReal(token: string, search?: string): Promise<AdminProduct[]> {
  const query = search ? `?search=${encodeURIComponent(search)}` : '';
  const response = await fetch(apiUrl(`/products${query}`), { headers: authHeaders(token) });
  const data = await response.json();
  if (!response.ok) throw new Error(parseError(data, 'Daftar produk tidak dapat dimuat.'));
  return Array.isArray(data.data) ? (data.data as AdminProduct[]) : [];
}

export async function fetchAdminProducts(token: string, filters: ProductFilters = {}): Promise<ProductsListResult> {
  return withDummyRead(
    useDummyStore.getState().isDummy,
    listDummyAdminProducts(filters),
    () => fetchAdminProductsReal(token, filters),
  );
}

async function fetchAdminProductsReal(token: string, filters: ProductFilters): Promise<ProductsListResult> {
  const query = new URLSearchParams();
  if (filters.search) query.set('search', filters.search);
  if (typeof filters.category === 'string' && filters.category !== '') query.set('category', filters.category);
  if (filters.status && ['active', 'inactive', 'unpurchasable'].includes(filters.status)) query.set('status', filters.status);
  if (filters.stockHealth && ['out', 'low', 'ok'].includes(filters.stockHealth)) query.set('stock_health', filters.stockHealth);
  query.set('limit', String(filters.limit ?? 15));
  query.set('cursor', String(filters.cursor ?? 0));
  query.set('sort', filters.sort || 'created_at');
  query.set('order', filters.order || 'desc');
  // Admin context always sends include_unpurchasable=1 to bypass supplier eligibility clause.
  query.set('include_unpurchasable', '1');

  const response = await fetch(apiUrl(`/products?${query.toString()}`), { headers: authHeaders(token), signal: filters.signal });
  const data = await response.json();
  if (!response.ok) throw new Error(parseError(data, 'Daftar produk tidak dapat dimuat.'));

  const products = Array.isArray(data.data) ? (data.data as AdminProduct[]) : [];
  const meta = (data.meta ?? {}) as {
    has_more?: boolean;
    limit?: number;
    cursor?: number;
    total?: number;
    summary?: { total: number; out_of_stock: number };
    categories?: string[];
  };

  return {
    products,
    hasMore: Boolean(meta.has_more),
    limit: Number(meta.limit ?? filters.limit ?? 15),
    cursor: Number(meta.cursor ?? filters.cursor ?? 0),
    total: meta.total !== undefined ? Number(meta.total) : undefined,
    summary: meta.summary,
    categories: Array.isArray(meta.categories)
      ? Array.from(new Set(meta.categories
        .filter((c): c is string => typeof c === 'string')
        .map((c) => c.trim())
        .filter(Boolean)))
      : undefined,
  };
}

export async function updateProductPrice(token: string, productId: number, price: number): Promise<AdminProduct> {
  if (useDummyStore.getState().isDummy) {
    return updateDummyProductPrice(productId, price) as unknown as AdminProduct;
  }
  const response = await fetch(apiUrl(`/admin/products/${productId}`), {
    method: 'PATCH',
    headers: authHeaders(token),
    body: JSON.stringify({ price }),
  });
  const data = await response.json();
  if (!response.ok) throw new Error(parseError(data, 'Harga produk tidak dapat diperbarui.'));
  return (data.data ?? data) as AdminProduct;
}

export async function fetchPriceHistory(token: string, productId: number, opts?: { limit?: number; cursor?: number }): Promise<PriceHistoryResult> {
  return withDummyRead(
    useDummyStore.getState().isDummy,
    dummyPriceHistory(productId, opts),
    () => fetchPriceHistoryReal(token, productId, opts),
  );
}

async function fetchPriceHistoryReal(token: string, productId: number, opts?: { limit?: number; cursor?: number }): Promise<PriceHistoryResult> {
  const query = new URLSearchParams();
  query.set('limit', String(opts?.limit ?? 15));
  if (opts?.cursor !== undefined && opts.cursor !== null) query.set('cursor', String(opts.cursor));
  const response = await fetch(apiUrl(`/admin/products/${productId}/prices?${query.toString()}`), { headers: authHeaders(token) });
  const data = await response.json();
  if (!response.ok) throw new Error(parseError(data, 'Riwayat harga tidak dapat dimuat.'));
  const inner = data.data as { data: PriceHistoryEntry[]; meta: { limit: number; cursor: number; has_more: boolean; next_cursor: number | null } } | PriceHistoryEntry[];
  if (Array.isArray(inner)) return { data: inner, hasMore: false, limit: opts?.limit ?? 15, cursor: 0, nextCursor: null };
  return { data: inner.data ?? [], hasMore: Boolean(inner.meta?.has_more), limit: Number(inner.meta?.limit ?? 15), cursor: Number(inner.meta?.cursor ?? 0), nextCursor: inner.meta?.next_cursor ?? null };
}
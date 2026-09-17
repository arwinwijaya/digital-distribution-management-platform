import { apiUrl, authHeaders } from '@/lib/api';
import { withDummyRead } from '@/dummy/guards';
import { useDummyStore } from '@/dummy/store';
import { updateDummyProductPrice } from '@/dummy/mutations';
import { compareRows, paginate } from '@/lib/admin-table';

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

function baseDummyProducts(): AdminProduct[] {
  return productsDummy().products.map((p, i) => ({
    id: i + 1,
    name: p.name,
    price: p.price.toFixed(2),
    sku: p.sku,
    stock_quantity: 40 + ((i * 11 + 7) % 160),
    category: p.category,
    is_active: i % 10 !== 0,
    supplier_id: (i % 8) + 1,
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

  const total = list.length;
  const outOfStock = list.filter((p) => typeof p.stock_quantity === 'number' && p.stock_quantity <= 0).length;

  const sortCol = filters.sort || 'created_at';
  const sortOrder = filters.order === 'asc' ? 'asc' : 'desc';
  list = [...list].sort((a, b) =>
    compareRows(a as unknown as Record<string, unknown>, b as unknown as Record<string, unknown>, sortCol, sortOrder),
  );

  const limit = filters.limit ?? 15;
  const cursor = filters.cursor ?? 0;
  const paginated = paginate(list, cursor, limit);
  return {
    products: paginated.page,
    hasMore: paginated.hasMore,
    limit,
    cursor,
    total,
    summary: { total, out_of_stock: outOfStock },
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
  description?: string | null;
  created_at?: string | null;
  updated_at?: string | null;
}

export interface ProductFilters {
  search?: string;
  limit?: number;
  cursor?: number;
  sort?: string;
  order?: string;
}

export interface ProductsListResult {
  products: AdminProduct[];
  hasMore: boolean;
  limit: number;
  cursor: number;
  total?: number;
  summary?: { total: number; out_of_stock: number };
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
  query.set('limit', String(filters.limit ?? 15));
  query.set('cursor', String(filters.cursor ?? 0));
  query.set('sort', filters.sort || 'created_at');
  query.set('order', filters.order || 'desc');
  const response = await fetch(apiUrl(`/products?${query.toString()}`), { headers: authHeaders(token) });
  const data = await response.json();
  if (!response.ok) throw new Error(parseError(data, 'Daftar produk tidak dapat dimuat.'));
  const products = Array.isArray(data.data) ? (data.data as AdminProduct[]) : [];
  const meta = (data.meta ?? {}) as { has_more?: boolean; limit?: number; cursor?: number; total?: number; summary?: { total: number; out_of_stock: number } };
  return {
    products,
    hasMore: Boolean(meta.has_more),
    limit: Number(meta.limit ?? filters.limit ?? 15),
    cursor: Number(meta.cursor ?? filters.cursor ?? 0),
    total: meta.total !== undefined ? Number(meta.total) : undefined,
    summary: meta.summary,
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

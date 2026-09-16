import { apiUrl, authHeaders } from '@/lib/api';
import { withDummyRead } from '@/dummy/guards';
import { useDummyStore } from '@/dummy/store';
import { updateDummyProductPrice } from '@/dummy/mutations';

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

function listDummyProducts(search?: string): AdminProduct[] {
  let list = productsDummy().products.map((p, i) => ({
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
  if (search) {
    const q = search.toLowerCase();
    list = list.filter((p) => p.name.toLowerCase().includes(q) || (p.sku ?? '').toLowerCase().includes(q));
  }
  return list;
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

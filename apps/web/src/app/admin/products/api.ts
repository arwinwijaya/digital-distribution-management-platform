import { apiUrl, authHeaders } from '@/lib/api';

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
  const query = search ? `?search=${encodeURIComponent(search)}` : '';
  const response = await fetch(apiUrl(`/products${query}`), { headers: authHeaders(token) });
  const data = await response.json();
  if (!response.ok) throw new Error(parseError(data, 'Daftar produk tidak dapat dimuat.'));
  return Array.isArray(data.data) ? (data.data as AdminProduct[]) : [];
}

export async function updateProductPrice(token: string, productId: number, price: number): Promise<AdminProduct> {
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

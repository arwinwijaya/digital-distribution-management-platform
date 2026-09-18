import { apiUrl, authHeaders } from '@/lib/api';
import { withDummyRead } from '@/dummy/guards';
import { useDummyStore } from '@/dummy/store';
import {
  broadcastDummyPromotion,
  createDummyPromotion,
  deleteDummyPromotion,
  updateDummyPromotion,
} from '@/dummy/mutations';
import { compareRows, paginate } from '@/lib/admin-table';

// ── Dummy helpers ───────────────────────────────────────────────────────────
interface DummyProductEntity { sku: string; name: string; category: string; price: number }

/** Local (not UTC) `YYYY-MM-DD` — mirrors the backend's date-granularity buckets. */
function localDateOnly(date: Date): string {
  return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
}

/**
 * Derive a deterministic promotion catalogue from the dummy product list.
 * Promotions are 1:1 with the first N products so the list is relational
 * (each `product_id` resolves to a real dummy product).
 *
 * Dummy parity for the admin promotions table: same offset-cursor /
 * sort / summary contract as the real `GET /admin/promotions` list. Default
 * `created_at DESC` with the shared `compareRows` comparator (id DESC fallback),
 * `total = filtered.length` and a 4-state `{ total, active, scheduled, ended }`
 * breakdown computed from the filtered full list (zero network).
 */
function listDummyPromotions(filters: PromotionFilters = {}): PromotionsListResult {
  const entities = useDummyStore.getState().dummyEntities as Partial<{ products: DummyProductEntity[] }> | null;
  const products = entities?.products ?? [];
  const count = Math.min(10, Math.max(6, Math.floor(products.length / 3)));
  const all: AdminPromotion[] = Array.from({ length: count }, (_, i) => {
    const product = products[i % Math.max(1, products.length)];
    const percentage = i % 3 !== 2;
    const startDay = (i % 20) + 1;
    const startDate = `2026-01-${String(startDay).padStart(2, '0')}`;
    const endDate = `2026-02-${String(startDay).padStart(2, '0')}`;
    return {
      id: -(i + 1),
      name: `Promo ${product?.name ?? `Paket ${i + 1}`}`,
      description: `Promo dummy untuk ${product?.category ?? 'semua kategori'}.`,
      discount_type: percentage ? 'percentage' : 'fixed',
      discount_value: percentage ? (10 + i).toFixed(2) : (10000 + i * 5000).toFixed(2),
      max_discount: percentage ? '25000.00' : null,
      product_id: products.length > 0 ? i + 1 : null,
      product: product ? { id: i + 1, name: product.name } : null,
      min_order: '100000.00',
      start_date: `${startDate}T00:00:00+07:00`,
      end_date: `${endDate}T23:59:59+07:00`,
      is_active: i % 4 !== 0,
      broadcast_at: i % 5 === 0 ? '2026-02-01T08:00:00+07:00' : null,
      created_by: 1,
      created_at: `${startDate}T09:00:00+07:00`,
      updated_at: '2026-02-01T09:00:00+07:00',
    };
  });

  const total = all.length;

  // 4-state buckets at DATE granularity (backend compares `toDateString()`).
  const today = localDateOnly(new Date());
  const day = (value: string) => (typeof value === 'string' ? value.slice(0, 10) : value);
  const active = all.filter((p) => day(p.start_date) <= today && day(p.end_date) >= today).length;
  const scheduled = all.filter((p) => day(p.start_date) > today).length;
  const ended = all.filter((p) => day(p.end_date) < today).length;

  const sortCol = filters.sort || 'created_at';
  const sortOrder = filters.order === 'asc' ? 'asc' : 'desc';
  const sorted = [...all].sort((a, b) =>
    compareRows(a as unknown as Record<string, unknown>, b as unknown as Record<string, unknown>, sortCol, sortOrder),
  );

  const limit = filters.limit ?? 15;
  const cursor = filters.cursor ?? 0;
  const paginated = paginate(sorted, cursor, limit);
  return {
    promotions: paginated.page,
    hasMore: paginated.hasMore,
    limit,
    cursor,
    total,
    summary: { total, active, scheduled, ended },
    nextCursor: paginated.nextCursor,
  };
}

export interface AdminPromotion {
  id: number;
  name: string;
  description?: string | null;
  discount_type: 'percentage' | 'fixed';
  discount_value: string;
  max_discount?: string | null;
  product_id?: number | null;
  product?: { id: number; name: string } | null;
  min_order?: string | null;
  start_date: string;
  end_date: string;
  is_active: boolean;
  broadcast_at?: string | null;
  created_by?: number | null;
  created_at?: string | null;
  updated_at?: string | null;
}

export interface PromotionFilters {
  limit?: number;
  cursor?: number;
  sort?: string;
  order?: string;
}

export interface PromotionsListResult {
  promotions: AdminPromotion[];
  hasMore: boolean;
  limit: number;
  cursor: number;
  total?: number;
  summary?: { total: number; active: number; scheduled: number; ended: number };
  /** Offset-derived next cursor (cursor + limit) — NEVER an id. */
  nextCursor: number;
}

/** Payload shape accepted by POST /admin/promotions and PATCH /admin/promotions/{id}. */
export interface PromotionInput {
  name: string;
  description?: string | null;
  discount_type: 'percentage' | 'fixed';
  discount_value: number;
  max_discount?: number | null;
  product_id?: number | null;
  min_order: number;
  start_date: string;
  end_date: string;
  is_active: boolean;
}

function parseError(data: unknown, fallback: string): string {
  if (data && typeof data === 'object' && data !== null && 'message' in data) {
    const v = (data as { message?: unknown }).message;
    if (typeof v === 'string') return v;
  }
  return fallback;
}

export async function fetchPromotions(token: string, filters: PromotionFilters = {}): Promise<PromotionsListResult> {
  return withDummyRead(
    useDummyStore.getState().isDummy,
    listDummyPromotions(filters),
    () => fetchPromotionsReal(token, filters),
  );
}

async function fetchPromotionsReal(token: string, filters: PromotionFilters): Promise<PromotionsListResult> {
  const query = new URLSearchParams();
  query.set('limit', String(filters.limit ?? 15));
  query.set('cursor', String(filters.cursor ?? 0));
  // Sort defaults: created_at DESC (always sent, matching the backend allowlist).
  query.set('sort', filters.sort || 'created_at');
  query.set('order', filters.order || 'desc');
  const response = await fetch(apiUrl(`/admin/promotions?${query.toString()}`), { headers: authHeaders(token) });
  const data = await response.json();
  if (!response.ok) throw new Error(parseError(data, 'Daftar promosi tidak dapat dimuat.'));
  const promotions = Array.isArray(data.data) ? (data.data as AdminPromotion[]) : [];
  const meta = (data.meta ?? {}) as {
    has_more?: boolean;
    limit?: number;
    cursor?: number;
    total?: number;
    summary?: { total: number; active: number; scheduled: number; ended: number };
  };
  const limit = Number(meta.limit ?? filters.limit ?? 15);
  const cursor = Number(meta.cursor ?? filters.cursor ?? 0);
  return {
    promotions,
    hasMore: Boolean(meta.has_more),
    limit,
    cursor,
    total: meta.total !== undefined ? Number(meta.total) : undefined,
    summary: meta.summary,
    // Offset-derived — never the backend's (removed) id-based next_cursor.
    nextCursor: cursor + limit,
  };
}

export async function createPromotion(token: string, payload: PromotionInput): Promise<AdminPromotion> {
  if (useDummyStore.getState().isDummy) {
    return createDummyPromotion(payload) as unknown as AdminPromotion;
  }
  const response = await fetch(apiUrl('/admin/promotions'), {
    method: 'POST',
    headers: authHeaders(token),
    body: JSON.stringify(payload),
  });
  const data = await response.json();
  if (!response.ok) throw new Error(parseError(data, 'Promosi tidak dapat dibuat.'));
  return (data.data ?? data) as AdminPromotion;
}

export async function updatePromotion(token: string, promotionId: number, payload: Partial<PromotionInput>): Promise<AdminPromotion> {
  if (useDummyStore.getState().isDummy) {
    return updateDummyPromotion(promotionId, payload) as unknown as AdminPromotion;
  }
  const response = await fetch(apiUrl(`/admin/promotions/${promotionId}`), {
    method: 'PATCH',
    headers: authHeaders(token),
    body: JSON.stringify(payload),
  });
  const data = await response.json();
  if (!response.ok) throw new Error(parseError(data, 'Promosi tidak dapat diperbarui.'));
  return (data.data ?? data) as AdminPromotion;
}

export async function deletePromotion(token: string, promotionId: number): Promise<void> {
  if (useDummyStore.getState().isDummy) {
    deleteDummyPromotion(promotionId);
    return;
  }
  const response = await fetch(apiUrl(`/admin/promotions/${promotionId}`), {
    method: 'DELETE',
    headers: authHeaders(token),
  });
  const data = await response.json().catch(() => ({}));
  if (!response.ok) throw new Error(parseError(data, 'Promosi tidak dapat dihapus.'));
}

export async function broadcastPromotion(token: string, promotionId: number): Promise<{ status: string; message?: string }> {
  if (useDummyStore.getState().isDummy) {
    return broadcastDummyPromotion(promotionId);
  }
  const response = await fetch(apiUrl(`/admin/promotions/${promotionId}/broadcast`), {
    method: 'POST',
    headers: authHeaders(token),
  });
  const data = await response.json().catch(() => ({}));
  if (response.status === 404) return { status: 'unavailable', message: 'Broadcast endpoint belum tersedia' };
  if (!response.ok) throw new Error(parseError(data, 'Broadcast gagal.'));
  return { status: 'ok', message: data.message ?? 'Berhasil menyiarkan promosi.' };
}

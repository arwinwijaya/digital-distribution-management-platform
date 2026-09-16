import { apiUrl, authHeaders } from '@/lib/api';

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
  created_at?: string;
  updated_at?: string;
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

export async function fetchPromotions(token: string, opts?: { limit?: number; cursor?: number }): Promise<{ promotions: AdminPromotion[]; hasMore: boolean; nextCursor: number | null }> {
  const query = new URLSearchParams();
  query.set('limit', String(opts?.limit ?? 15));
  if (opts?.cursor) query.set('cursor', String(opts.cursor));
  const response = await fetch(apiUrl(`/admin/promotions?${query.toString()}`), { headers: authHeaders(token) });
  const data = await response.json();
  if (!response.ok) throw new Error(parseError(data, 'Daftar promosi tidak dapat dimuat.'));
  return { promotions: Array.isArray(data.data) ? (data.data as AdminPromotion[]) : [], hasMore: Boolean(data.has_more), nextCursor: data.next_cursor ?? null };
}

export async function createPromotion(token: string, payload: PromotionInput): Promise<AdminPromotion> {
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
  const response = await fetch(apiUrl(`/admin/promotions/${promotionId}`), {
    method: 'DELETE',
    headers: authHeaders(token),
  });
  const data = await response.json().catch(() => ({}));
  if (!response.ok) throw new Error(parseError(data, 'Promosi tidak dapat dihapus.'));
}

export async function broadcastPromotion(token: string, promotionId: number): Promise<{ status: string; message?: string }> {
  const response = await fetch(apiUrl(`/admin/promotions/${promotionId}/broadcast`), {
    method: 'POST',
    headers: authHeaders(token),
  });
  const data = await response.json().catch(() => ({}));
  if (response.status === 404) return { status: 'unavailable', message: 'Broadcast endpoint belum tersedia' };
  if (!response.ok) throw new Error(parseError(data, 'Broadcast gagal.'));
  return { status: 'ok', message: data.message ?? 'Berhasil menyiarkan promosi.' };
}

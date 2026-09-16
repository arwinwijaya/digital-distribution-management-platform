import { apiUrl, authHeaders } from '@/lib/api';
import { useDummyStore } from '@/dummy/store';
import { updateDummyOutlet } from '@/dummy/mutations';

export interface AdminOutlet {
  id: number;
  name: string;
  category?: string | null;
  territory_id?: number | null;
  territory?: { id: number; name: string } | null;
  is_active?: boolean;
  score?: number;
  city?: string;
  district?: string;
  address?: string;
  latitude?: number | null;
  longitude?: number | null;
  phone?: string | null;
}

export interface OutletFilters {
  search?: string;
  category?: string;
  territory_id?: string;
  is_active?: string;
  limit?: number;
  cursor?: number;
}

export interface OutletsListResult {
  outlets: AdminOutlet[];
  hasMore: boolean;
  limit: number;
  cursor: number;
}

export interface OutletOrder {
  id: number;
  order_id: string;
  status: string;
  total_amount: string;
  created_at?: string;
}

export interface OutletSummary {
  outlet_id: number;
  outlet_name: string;
  total_orders: number;
  total_amount: string;
  total_spend?: string;
  last_order_date?: string | null;
}

function parseError(data: unknown, fallback: string): string {
  if (data && typeof data === 'object' && data !== null && 'message' in data) {
    const v = (data as { message?: unknown }).message;
    if (typeof v === 'string') return v;
  }
  return fallback;
}

export async function fetchAdminOutlets(token: string, filters: OutletFilters = {}): Promise<OutletsListResult> {
  const query = new URLSearchParams();
  if (filters.search) query.set('search', filters.search);
  if (filters.category) query.set('category', filters.category);
  if (filters.territory_id) query.set('territory_id', filters.territory_id);
  if (filters.is_active) query.set('is_active', filters.is_active);
  query.set('limit', String(filters.limit ?? 15));
  if (filters.cursor) query.set('cursor', String(filters.cursor));
  const response = await fetch(apiUrl(`/admin/outlets?${query.toString()}`), { headers: authHeaders(token) });
  const data = await response.json();
  if (!response.ok) throw new Error(parseError(data, 'Daftar outlet tidak dapat dimuat.'));
  const inner = data.data as { data: AdminOutlet[]; has_more: boolean; limit: number; cursor: number } | AdminOutlet[];
  const outlets = Array.isArray(inner) ? inner : (inner.data ?? []);
  const hasMore = Array.isArray(inner) ? Boolean(data.has_more ?? data.meta?.has_more) : Boolean(inner.has_more);
  const limit = Array.isArray(inner) ? Number(data.meta?.limit ?? 15) : Number(inner.limit ?? 15);
  const cursor = Array.isArray(inner) ? Number(data.meta?.cursor ?? 0) : Number(inner.cursor ?? 0);
  return { outlets, hasMore, limit, cursor };
}

export async function updateOutlet(token: string, outletId: number, payload: Partial<AdminOutlet> & { name?: string; category?: string; address?: string; city?: string; district?: string; is_active?: boolean }): Promise<AdminOutlet> {
  if (useDummyStore.getState().isDummy) {
    return updateDummyOutlet(outletId, payload) as unknown as AdminOutlet;
  }
  const response = await fetch(apiUrl(`/admin/outlets/${outletId}`), {
    method: 'PATCH',
    headers: authHeaders(token),
    body: JSON.stringify(payload),
  });
  const data = await response.json();
  if (!response.ok) throw new Error(parseError(data, 'Outlet tidak dapat diperbarui.'));
  return (data.data ?? data) as AdminOutlet;
}

export async function fetchOutletOrders(token: string, outletId: number, opts?: { limit?: number; cursor?: number }): Promise<{ orders: OutletOrder[]; hasMore: boolean }> {
  const query = new URLSearchParams();
  query.set('limit', String(opts?.limit ?? 15));
  if (opts?.cursor) query.set('cursor', String(opts.cursor));
  const response = await fetch(apiUrl(`/admin/outlets/${outletId}/orders?${query.toString()}`), { headers: authHeaders(token) });
  const data = await response.json();
  if (!response.ok) throw new Error(parseError(data, 'Riwayat pesanan tidak dapat dimuat.'));
  const orders: OutletOrder[] = Array.isArray(data.data) ? data.data : [];
  const hasMore = Boolean(data.meta?.has_more);
  return { orders, hasMore };
}

export async function fetchOutletSummary(token: string, outletId: number): Promise<OutletSummary> {
  const response = await fetch(apiUrl(`/admin/outlets/${outletId}/summary`), { headers: authHeaders(token) });
  const data = await response.json();
  if (!response.ok) throw new Error(parseError(data, 'Ringkasan outlet tidak dapat dimuat.'));
  return (data.data ?? data) as OutletSummary;
}

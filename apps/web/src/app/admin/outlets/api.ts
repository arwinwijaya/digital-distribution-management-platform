import { apiUrl, authHeaders } from '@/lib/api';
import { withDummyRead } from '@/dummy/guards';
import { useDummyStore } from '@/dummy/store';
import { JABODETABEK_TERRITORIES } from '@/dummy/seed';
import { updateDummyOutlet } from '@/dummy/mutations';
import { compareRows, paginate } from '@/lib/admin-table';

// ── Dummy-mode entity shapes (subset of the T5/T6 relational graph) ─────────
interface DummyOutletEntity {
  id: string;
  name: string;
  territoryId: string;
  city: string;
  lat: number;
  lon: number;
}

interface DummyOrderEntity {
  id: number;
  order_id: string;
  outlet_id: number;
  outlet_code: string;
  status: string;
  total_amount: string;
  created_at?: string;
}

const DUMMY_CATEGORIES = [
  'warung',
  'minimarket',
  'supermarket',
  'grosir',
  'restoran',
  'kafe',
  'toko_kelontong',
  'lainnya',
];

function dummyState(): { outlets: DummyOutletEntity[]; orders: DummyOrderEntity[] } {
  const entities = useDummyStore.getState().dummyEntities as
    | Partial<{ outlets: DummyOutletEntity[]; orders: DummyOrderEntity[] }>
    | null;
  return { outlets: entities?.outlets ?? [], orders: entities?.orders ?? [] };
}

function numericOutletId(id: string): number {
  const n = Number(String(id).replace(/^dummy-/, ''));
  return Number.isFinite(n) ? n : 0;
}

function territoryById(territoryId: string): { id: number; name: string } | undefined {
  const idx = JABODETABEK_TERRITORIES.findIndex((t) => t.id === territoryId);
  if (idx < 0) return undefined;
  return { id: idx + 1, name: JABODETABEK_TERRITORIES[idx].name };
}

function toAdminOutlet(entity: DummyOutletEntity, index: number): AdminOutlet {
  const territory = territoryById(entity.territoryId) ?? { id: index + 1, name: entity.city };
  return {
    id: numericOutletId(entity.id),
    name: entity.name,
    category: DUMMY_CATEGORIES[index % DUMMY_CATEGORIES.length],
    territory_id: territory.id,
    territory,
    is_active: index % 7 !== 0,
    score: (index * 7) % 100,
    city: entity.city,
    district: `${entity.city} ${(index % 5) + 1}`,
    address: `Jl. Dummy ${entity.name.replace(/\s+/g, ' ')} No. ${index + 1}`,
    latitude: entity.lat,
    longitude: entity.lon,
    phone: `+62 8${String(10000000 + index).padStart(9, '0')}`,
    created_at: new Date(Date.UTC(2026, 0, 31) - index * 86_400_000).toISOString(),
    updated_at: new Date(Date.UTC(2026, 1, 10) - index * 43_200_000).toISOString(),
  };
}

function listDummyOutlets(filters: OutletFilters): OutletsListResult {
  let outlets = dummyState().outlets.map((o, i) => toAdminOutlet(o, i));

  if (filters.search) {
    const q = filters.search.toLowerCase();
    outlets = outlets.filter((o) => o.name.toLowerCase().includes(q));
  }
  if (filters.category) outlets = outlets.filter((o) => o.category === filters.category);
  if (filters.territory_id) {
    outlets = outlets.filter((o) => String(o.territory_id) === String(filters.territory_id));
  }
  if (filters.is_active !== undefined && filters.is_active !== '') {
    const want = filters.is_active === 'true';
    outlets = outlets.filter((o) => Boolean(o.is_active) === want);
  }

  // Sort using compareRows (default: created_at DESC, fallback to id DESC)
  const sortCol = filters.sort || 'created_at';
  const sortOrder = filters.order === 'asc' ? 'asc' : 'desc';
  outlets = [...outlets].sort((a, b) =>
    compareRows(a as unknown as Record<string, unknown>, b as unknown as Record<string, unknown>, sortCol, sortOrder),
  );

  // Compute summary
  const active = outlets.filter((o) => o.is_active).length;
  const inactive = outlets.filter((o) => !o.is_active).length;

  const limit = filters.limit ?? 15;
  const cursor = filters.cursor ?? 0;
  const paginated = paginate(outlets, cursor, limit);
  return {
    outlets: paginated.page,
    hasMore: paginated.hasMore,
    limit,
    cursor,
    total: paginated.total,
    summary: { active, inactive },
  };
}

function resolveOutletId(outletId: number): number {
  const { outlets } = dummyState();
  const found = outlets.find((o) => numericOutletId(o.id) === outletId);
  if (found) return outletId;
  return outlets.length > 0 ? numericOutletId(outlets[0].id) : outletId;
}

function dummyOutletOrders(
  outletId: number,
  opts?: { limit?: number; cursor?: number },
): { orders: OutletOrder[]; hasMore: boolean } {
  const resolvedId = resolveOutletId(outletId);
  const matched = dummyState().orders.filter((o) => o.outlet_id === resolvedId);
  const limit = opts?.limit ?? 15;
  const cursor = opts?.cursor ?? 0;
  const page = matched.slice(cursor, cursor + limit).map((o) => ({
    id: o.id,
    order_id: o.order_id,
    status: o.status,
    total_amount: o.total_amount,
    created_at: o.created_at,
  }));
  return { orders: page, hasMore: cursor + limit < matched.length };
}

function dummyOutletSummary(outletId: number): OutletSummary {
  const resolvedId = resolveOutletId(outletId);
  const { outlets, orders } = dummyState();
  const outlet = outlets.find((o) => numericOutletId(o.id) === resolvedId);
  const outletOrders = orders.filter((o) => o.outlet_id === resolvedId);
  const total = outletOrders.reduce(
    (sum, o) => sum + (Number(String(o.total_amount).replace(/[^0-9.]/g, '')) || 0),
    0,
  );
  const lastOrder = outletOrders
    .map((o) => o.created_at)
    .filter((d): d is string => Boolean(d))
    .sort()
    .pop() ?? null;
  return {
    outlet_id: resolvedId,
    outlet_name: outlet?.name ?? `Outlet ${resolvedId}`,
    total_orders: outletOrders.length,
    total_amount: total.toFixed(2),
    total_spend: total.toFixed(2),
    last_order_date: lastOrder,
  };
}

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
  created_at?: string | null;
  updated_at?: string | null;
}

export interface OutletFilters {
  search?: string;
  category?: string;
  territory_id?: string;
  is_active?: string;
  limit?: number;
  cursor?: number;
  sort?: string;
  order?: string;
}

export interface OutletsListResult {
  outlets: AdminOutlet[];
  hasMore: boolean;
  limit: number;
  cursor: number;
  total?: number;
  summary?: { active: number; inactive: number };
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
  return withDummyRead(
    useDummyStore.getState().isDummy,
    listDummyOutlets(filters),
    () => fetchAdminOutletsReal(token, filters),
  );
}

async function fetchAdminOutletsReal(token: string, filters: OutletFilters): Promise<OutletsListResult> {
  const query = new URLSearchParams();
  if (filters.search) query.set('search', filters.search);
  if (filters.category) query.set('category', filters.category);
  if (filters.territory_id) query.set('territory_id', filters.territory_id);
  if (filters.is_active) query.set('is_active', filters.is_active);
  query.set('limit', String(filters.limit ?? 15));
  if (filters.cursor) query.set('cursor', String(filters.cursor));
  // Sort defaults: created_at DESC
  query.set('sort', filters.sort || 'created_at');
  query.set('order', filters.order || 'desc');
  const response = await fetch(apiUrl(`/admin/outlets?${query.toString()}`), { headers: authHeaders(token) });
  const data = await response.json();
  if (!response.ok) throw new Error(parseError(data, 'Daftar outlet tidak dapat dimuat.'));
  const inner = data.data as { data: AdminOutlet[]; has_more: boolean; limit: number; cursor: number } | AdminOutlet[];
  const outlets = Array.isArray(inner) ? inner : (inner.data ?? []);
  const hasMore = Array.isArray(inner) ? Boolean(data.has_more ?? data.meta?.has_more) : Boolean(inner.has_more);
  const limit = Array.isArray(inner) ? Number(data.meta?.limit ?? 15) : Number(inner.limit ?? 15);
  const cursor = Array.isArray(inner) ? Number(data.meta?.cursor ?? 0) : Number(inner.cursor ?? 0);
  const total = data.meta?.total !== undefined ? Number(data.meta.total) : undefined;
  const summary = data.meta?.summary as { active: number; inactive: number } | undefined;
  return { outlets, hasMore, limit, cursor, total, summary };
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
  return withDummyRead(
    useDummyStore.getState().isDummy,
    dummyOutletOrders(outletId, opts),
    () => fetchOutletOrdersReal(token, outletId, opts),
  );
}

async function fetchOutletOrdersReal(token: string, outletId: number, opts?: { limit?: number; cursor?: number }): Promise<{ orders: OutletOrder[]; hasMore: boolean }> {
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
  return withDummyRead(
    useDummyStore.getState().isDummy,
    dummyOutletSummary(outletId),
    () => fetchOutletSummaryReal(token, outletId),
  );
}

async function fetchOutletSummaryReal(token: string, outletId: number): Promise<OutletSummary> {
  const response = await fetch(apiUrl(`/admin/outlets/${outletId}/summary`), { headers: authHeaders(token) });
  const data = await response.json();
  if (!response.ok) throw new Error(parseError(data, 'Ringkasan outlet tidak dapat dimuat.'));
  return (data.data ?? data) as OutletSummary;
}

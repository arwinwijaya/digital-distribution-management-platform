import { apiUrl, authHeaders } from '@/lib/api';
import { ApiError } from '@/lib/api-error';
import { withDummyRead } from '@/dummy/guards';
import { useDummyStore } from '@/dummy/store';
import { compareRows, paginate } from '@/lib/admin-table';

export interface ReplenishmentPlanItem {
  product_id: number;
  product: { id: number; name: string; price: number } | null;
  reorder_quantity: number;
  data_sufficiency: string | null;
  metadata: Record<string, unknown> | null;
}

export interface ReplenishmentPlan {
  id: number;
  supplier_id: number;
  supplier: { id: number; name: string } | null;
  created_by: number | null;
  approved_by: number | null;
  executed_by: number | null;
  status: 'draft' | 'approved' | 'rejected' | 'executed' | 'failed' | 'cancelled';
  window_start: string | null;
  window_end: string | null;
  approved_at: string | null;
  executed_at: string | null;
  execution_result: Record<string, unknown> | null;
  metadata: Record<string, unknown> | null;
  items: ReplenishmentPlanItem[];
  idempotent_replay: boolean;
  created_at: string | null;
  updated_at: string | null;
}

export interface ReplenishmentPlansListResult {
  plans: ReplenishmentPlan[];
  hasMore: boolean;
  limit: number;
  cursor: number;
  total?: number;
  summary?: {
    total: number;
    draft: number;
    approved: number;
    executed: number;
    rejected: number;
    failed: number;
    cancelled: number;
  };
  nextCursor: number;
}

export interface ReplenishmentPlanFilters {
  limit?: number;
  cursor?: number;
  sort?: string;
  order?: string;
  status?: string;
  supplier_id?: number;
}

export interface GeneratePlanRequest {
  window_start: string;
  window_end: string;
  supplier_id?: number;
}

export interface GeneratePlanResponse {
  plan: ReplenishmentPlan;
}

export interface ExecutePlanResponse {
  plan: ReplenishmentPlan;
}

/**
 * Verify the stored actor through the same envelope contract as list calls.
 */
export async function verifyAdminAccess(token: string): Promise<string> {
  const response = await fetch(apiUrl('/auth/me'), { headers: authHeaders(token) });
  const body = await response.json();
  if (!response.ok || body.status === 'error') {
    throw new ApiError(response.status, body.message || 'Sesi tidak dapat diverifikasi.');
  }
  return (body.data?.role as string | undefined) ?? '';
}

function listDummyReplenishmentPlans(filters: ReplenishmentPlanFilters = {}): ReplenishmentPlansListResult {
  const entities = useDummyStore.getState().dummyEntities as Partial<{ replenishment_plans: ReplenishmentPlan[] }> | null;
  let list = entities?.replenishment_plans ?? [];

  if (filters.status) list = list.filter((p) => p.status === filters.status);
  if (filters.supplier_id) list = list.filter((p) => p.supplier_id === filters.supplier_id);

  const total = list.length;
  const draft = list.filter((p) => p.status === 'draft').length;
  const approved = list.filter((p) => p.status === 'approved').length;
  const executed = list.filter((p) => p.status === 'executed').length;
  const rejected = list.filter((p) => p.status === 'rejected').length;
  const failed = list.filter((p) => p.status === 'failed').length;
  const cancelled = list.filter((p) => p.status === 'cancelled').length;

  const sortCol = filters.sort || 'created_at';
  const sortOrder = filters.order === 'asc' ? 'asc' : 'desc';
  const sorted = [...list].sort((a, b) =>
    compareRows(a as unknown as Record<string, unknown>, b as unknown as Record<string, unknown>, sortCol, sortOrder),
  );

  const limit = filters.limit ?? 15;
  const cursor = filters.cursor ?? 0;
  const paginated = paginate(sorted, cursor, limit);

  return {
    plans: paginated.page,
    hasMore: paginated.hasMore,
    limit,
    cursor,
    total,
    summary: { total, draft, approved, executed, rejected, failed, cancelled },
    nextCursor: paginated.nextCursor,
  };
}

async function fetchReplenishmentPlansReal(token: string, filters: ReplenishmentPlanFilters): Promise<ReplenishmentPlansListResult> {
  const query = new URLSearchParams();
  query.set('limit', String(filters.limit ?? 15));
  query.set('cursor', String(filters.cursor ?? 0));
  query.set('sort', filters.sort || 'created_at');
  query.set('order', filters.order || 'desc');
  if (filters.status) query.set('status', filters.status);
  if (filters.supplier_id) query.set('supplier_id', String(filters.supplier_id));

  const response = await fetch(apiUrl(`/admin/replenishment-plans?${query.toString()}`), {
    headers: authHeaders(token),
  });
  const data = await response.json();
  if (!response.ok || data.status === 'error') {
    throw new Error(data.message || 'Daftar rencana replenishment tidak dapat dimuat.');
  }
  const plans = Array.isArray(data.data) ? (data.data as ReplenishmentPlan[]) : [];
  const meta = (data.meta ?? {}) as {
    has_more?: boolean;
    limit?: number;
    cursor?: number;
    total?: number;
    summary?: { total: number; draft: number; approved: number; executed: number; rejected: number; failed: number; cancelled: number };
  };
  const limit = Number(meta.limit ?? filters.limit ?? 15);
  const cursor = Number(meta.cursor ?? filters.cursor ?? 0);
  return {
    plans,
    hasMore: Boolean(meta.has_more),
    limit,
    cursor,
    total: meta.total !== undefined ? Number(meta.total) : undefined,
    summary: meta.summary,
    nextCursor: cursor + limit,
  };
}

export async function fetchReplenishmentPlans(token: string, filters: ReplenishmentPlanFilters = {}): Promise<ReplenishmentPlansListResult> {
  return withDummyRead(
    useDummyStore.getState().isDummy,
    listDummyReplenishmentPlans(filters),
    () => fetchReplenishmentPlansReal(token, filters),
  );
}

async function generatePlanReal(token: string, body: GeneratePlanRequest): Promise<GeneratePlanResponse> {
  const response = await fetch(apiUrl('/admin/replenishment-plans/generate'), {
    method: 'POST',
    headers: authHeaders(token),
    body: JSON.stringify(body),
  });
  const data = await response.json();
  if (!response.ok || data.status === 'error') {
    throw new Error(data.message || 'Gagal membuat rencana replenishment.');
  }
  return data;
}

export async function generatePlan(token: string, body: GeneratePlanRequest): Promise<GeneratePlanResponse> {
  if (useDummyStore.getState().isDummy) {
    const existing = listDummyReplenishmentPlans().plans[0];
    const fallback: ReplenishmentPlan = {
      id: Date.now(),
      supplier_id: body.supplier_id ?? 0,
      supplier: null,
      created_by: null,
      approved_by: null,
      executed_by: null,
      status: 'draft',
      window_start: body.window_start,
      window_end: body.window_end,
      approved_at: null,
      executed_at: null,
      execution_result: null,
      metadata: null,
      items: [],
      idempotent_replay: false,
      created_at: new Date().toISOString(),
      updated_at: new Date().toISOString(),
    };
    return { plan: existing ? { ...existing, id: Date.now() } : fallback };
  }
  return generatePlanReal(token, body);
}

async function approvePlanReal(token: string, planId: number): Promise<ReplenishmentPlan> {
  const response = await fetch(apiUrl(`/admin/replenishment-plans/${planId}/approve`), {
    method: 'POST',
    headers: authHeaders(token),
  });
  const data = await response.json();
  if (!response.ok || data.status === 'error') {
    throw new Error(data.message || 'Gagal menyetujui rencana.');
  }
  return data.data as ReplenishmentPlan;
}

export async function approvePlan(token: string, planId: number): Promise<ReplenishmentPlan> {
  if (useDummyStore.getState().isDummy) {
    const entities = useDummyStore.getState().dummyEntities as Partial<{ replenishment_plans: ReplenishmentPlan[] }> | null;
    const list = entities?.replenishment_plans ?? [];
    const idx = list.findIndex((p) => p.id === planId);
    if (idx >= 0) {
      const updated = { ...list[idx], status: 'approved' as const, approved_at: new Date().toISOString() };
      list[idx] = updated;
      return updated;
    }
    throw new Error('Rencana tidak ditemukan.');
  }
  return approvePlanReal(token, planId);
}

async function executePlanReal(token: string, planId: number, logicalKey: string): Promise<ExecutePlanResponse> {
  const response = await fetch(apiUrl(`/admin/replenishment-plans/${planId}/execute`), {
    method: 'POST',
    headers: authHeaders(token),
    body: JSON.stringify({ logical_key: logicalKey }),
  });
  const data = await response.json();
  if (!response.ok || data.status === 'error') {
    if (response.status === 409) {
      throw new Error(data.message || 'Konflik idempotency: kunci logis sudah digunakan dengan payload berbeda.');
    }
    throw new Error(data.message || 'Gagal mengeksekusi rencana.');
  }
  return data;
}

export async function executePlan(token: string, planId: number, logicalKey: string): Promise<ExecutePlanResponse> {
  if (useDummyStore.getState().isDummy) {
    const entities = useDummyStore.getState().dummyEntities as Partial<{ replenishment_plans: ReplenishmentPlan[] }> | null;
    const list = entities?.replenishment_plans ?? [];
    const idx = list.findIndex((p) => p.id === planId);
    if (idx >= 0) {
      const updated = {
        ...list[idx],
        status: 'executed' as const,
        executed_at: new Date().toISOString(),
        execution_result: { purchase_orders: [{ reference: `PO-${new Date().getFullYear()}-${String(Math.floor(Math.random() * 10000)).padStart(4, '0')}`, items: [] }] },
      };
      list[idx] = updated;
      return { plan: updated };
    }
    throw new Error('Rencana tidak ditemukan.');
  }
  return executePlanReal(token, planId, logicalKey);
}
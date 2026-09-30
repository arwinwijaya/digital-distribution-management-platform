import { apiUrl, authHeaders } from '@/lib/api';
import { withDummyRead } from '@/dummy/guards';
import { useDummyStore } from '@/dummy/store';
import { compareRows, paginate } from '@/lib/admin-table';

export interface RecommendationAction {
  id: number;
  source_event_id: number | null;
  outlet_id: number | null;
  created_by: number | null;
  approved_by: number | null;
  executed_by: number | null;
  type: 'draft_order' | 'draft_campaign';
  status: 'draft' | 'pending_approval' | 'approved' | 'rejected' | 'executed' | 'failed' | 'cancelled';
  payload: Record<string, unknown>;
  idempotency_key: string;
  idempotency_payload_hash: string;
  approved_at: string | null;
  executed_at: string | null;
  rejection_reason: string | null;
  execution_result: Record<string, unknown> | null;
  method: string | null;
  method_version: string | null;
  fallback: boolean;
  data_sufficiency: string | null;
  metadata: Record<string, unknown> | null;
  idempotent_replay: boolean;
  created_at: string | null;
  updated_at: string | null;
}

export interface RecommendationActionsListResult {
  actions: RecommendationAction[];
  hasMore: boolean;
  limit: number;
  cursor: number;
  total?: number;
  summary?: { total: number; draft: number; pending: number; approved: number; executed: number; rejected: number; failed: number; cancelled: number };
  nextCursor: number;
}

export interface RecommendationActionFilters {
  limit?: number;
  cursor?: number;
  sort?: string;
  order?: string;
  status?: string;
  type?: string;
  outlet_id?: number;
}

function listDummyRecommendationActions(filters: RecommendationActionFilters = {}): RecommendationActionsListResult {
  const entities = useDummyStore.getState().dummyEntities as Partial<{ recommendation_actions: RecommendationAction[] }> | null;
  let list = entities?.recommendation_actions ?? [];

  if (filters.status) list = list.filter((a) => a.status === filters.status);
  if (filters.type) list = list.filter((a) => a.type === filters.type);
  if (filters.outlet_id) list = list.filter((a) => a.outlet_id === filters.outlet_id);

  const total = list.length;
  const draft = list.filter((a) => a.status === 'draft').length;
  const pending = list.filter((a) => a.status === 'pending_approval').length;
  const approved = list.filter((a) => a.status === 'approved').length;
  const executed = list.filter((a) => a.status === 'executed').length;
  const rejected = list.filter((a) => a.status === 'rejected').length;
  const failed = list.filter((a) => a.status === 'failed').length;
  const cancelled = list.filter((a) => a.status === 'cancelled').length;

  const sortCol = filters.sort || 'created_at';
  const sortOrder = filters.order === 'asc' ? 'asc' : 'desc';
  const sorted = [...list].sort((a, b) =>
    compareRows(a as unknown as Record<string, unknown>, b as unknown as Record<string, unknown>, sortCol, sortOrder),
  );

  const limit = filters.limit ?? 15;
  const cursor = filters.cursor ?? 0;
  const paginated = paginate(sorted, cursor, limit);

  return {
    actions: paginated.page,
    hasMore: paginated.hasMore,
    limit,
    cursor,
    total,
    summary: { total, draft, pending, approved, executed, rejected, failed, cancelled },
    nextCursor: paginated.nextCursor,
  };
}

async function fetchRecommendationActionsReal(token: string, filters: RecommendationActionFilters): Promise<RecommendationActionsListResult> {
  const query = new URLSearchParams();
  query.set('limit', String(filters.limit ?? 15));
  query.set('cursor', String(filters.cursor ?? 0));
  query.set('sort', filters.sort || 'created_at');
  query.set('order', filters.order || 'desc');
  if (filters.status) query.set('status', filters.status);
  if (filters.type) query.set('type', filters.type);
  if (filters.outlet_id) query.set('outlet_id', String(filters.outlet_id));

  const response = await fetch(apiUrl(`/admin/recommendation-actions?${query.toString()}`), {
    headers: authHeaders(token),
  });
  const data = await response.json();
  if (!response.ok || data.status === 'error') {
    throw new Error(data.message || 'Daftar aksi rekomendasi tidak dapat dimuat.');
  }
  const actions = Array.isArray(data.data) ? (data.data as RecommendationAction[]) : [];
  const meta = (data.meta ?? {}) as {
    has_more?: boolean;
    limit?: number;
    cursor?: number;
    total?: number;
    summary?: { total: number; draft: number; pending: number; approved: number; executed: number; rejected: number; failed: number; cancelled: number };
  };
  const limit = Number(meta.limit ?? filters.limit ?? 15);
  const cursor = Number(meta.cursor ?? filters.cursor ?? 0);
  return {
    actions,
    hasMore: Boolean(meta.has_more),
    limit,
    cursor,
    total: meta.total !== undefined ? Number(meta.total) : undefined,
    summary: meta.summary,
    nextCursor: cursor + limit,
  };
}

export async function fetchRecommendationActions(token: string, filters: RecommendationActionFilters = {}): Promise<RecommendationActionsListResult> {
  return withDummyRead(
    useDummyStore.getState().isDummy,
    listDummyRecommendationActions(filters),
    () => fetchRecommendationActionsReal(token, filters),
  );
}

async function mutateActionReal(token: string, actionId: number, endpoint: string): Promise<RecommendationAction> {
  const response = await fetch(apiUrl(`/admin/recommendation-actions/${actionId}/${endpoint}`), {
    method: 'POST',
    headers: authHeaders(token),
  });
  const data = await response.json();
  if (!response.ok || data.status === 'error') {
    throw new Error(data.message || `Gagal ${endpoint} aksi.`);
  }
  return data.data as RecommendationAction;
}

export async function approveAction(token: string, actionId: number): Promise<RecommendationAction> {
  if (useDummyStore.getState().isDummy) {
    const entities = useDummyStore.getState().dummyEntities as Partial<{ recommendation_actions: RecommendationAction[] }> | null;
    const list = entities?.recommendation_actions ?? [];
    const idx = list.findIndex((a) => a.id === actionId);
    if (idx >= 0) {
      const updated = { ...list[idx], status: 'approved' as const, approved_at: new Date().toISOString() };
      list[idx] = updated;
      return updated;
    }
    throw new Error('Aksi tidak ditemukan.');
  }
  return mutateActionReal(token, actionId, 'approve');
}

export async function rejectAction(token: string, actionId: number, reason: string): Promise<RecommendationAction> {
  if (useDummyStore.getState().isDummy) {
    const entities = useDummyStore.getState().dummyEntities as Partial<{ recommendation_actions: RecommendationAction[] }> | null;
    const list = entities?.recommendation_actions ?? [];
    const idx = list.findIndex((a) => a.id === actionId);
    if (idx >= 0) {
      const updated = { ...list[idx], status: 'rejected' as const, rejection_reason: reason };
      list[idx] = updated;
      return updated;
    }
    throw new Error('Aksi tidak ditemukan.');
  }
  const response = await fetch(apiUrl(`/admin/recommendation-actions/${actionId}/reject`), {
    method: 'POST',
    headers: authHeaders(token),
    body: JSON.stringify({ reason }),
  });
  const data = await response.json();
  if (!response.ok || data.status === 'error') {
    throw new Error(data.message || 'Gagal menolak aksi.');
  }
  return data.data as RecommendationAction;
}

export async function executeAction(token: string, actionId: number): Promise<RecommendationAction> {
  if (useDummyStore.getState().isDummy) {
    const entities = useDummyStore.getState().dummyEntities as Partial<{ recommendation_actions: RecommendationAction[] }> | null;
    const list = entities?.recommendation_actions ?? [];
    const idx = list.findIndex((a) => a.id === actionId);
    if (idx >= 0) {
      const updated = { ...list[idx], status: 'executed' as const, executed_at: new Date().toISOString() };
      list[idx] = updated;
      return updated;
    }
    throw new Error('Aksi tidak ditemukan.');
  }
  return mutateActionReal(token, actionId, 'execute');
}

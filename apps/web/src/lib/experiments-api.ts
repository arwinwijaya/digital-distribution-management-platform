import { apiUrl, authHeaders, getStoredToken } from '@/lib/api';
import { ApiError } from '@/lib/api-error';
import { selectIsDummy, useDummyStore } from '@/dummy/store';
import { withDummyRead } from '@/dummy/guards';

export type ExperimentStatus = 'draft' | 'running' | 'completed' | 'paused' | string;
export type LiftStatus = 'computed' | 'insufficient-data' | 'pending' | string;

export interface ExperimentAssignmentSummary {
  total: number;
  control: number;
  treatment: number;
}

export interface AbExperiment {
  id: number;
  experiment_key: string;
  name: string;
  status: ExperimentStatus;
  minimum_sample_size: number;
  assignment_summary: ExperimentAssignmentSummary;
  starts_at: string | null;
  ends_at: string | null;
  created_at: string;
}

export interface RevenueLiftSnapshot {
  id: number;
  experiment_id: number;
  uplift: string | null;
  status: LiftStatus;
  method_version: string;
  control_sample_size: number;
  treatment_sample_size: number;
  control_revenue: string;
  treatment_revenue: string;
  metadata?: { sufficient?: boolean; minimum_sample_size?: number; [key: string]: unknown };
  created_at: string;
}

export interface ExperimentListMeta {
  has_more: boolean;
  limit: number;
  cursor: number;
  total?: number;
}

export interface ExperimentListData {
  experiments: AbExperiment[];
  meta: ExperimentListMeta;
}

interface Envelope<T> {
  status: 'success' | 'error';
  message?: string;
  data: T;
  meta?: ExperimentListMeta;
}

/** Verify the stored actor through the same envelope contract as experiment calls. */
export async function verifyAdminAccess(token: string): Promise<string> {
  const response = await fetch(apiUrl('/auth/me'), { headers: authHeaders(token) });
  const body = await response.json();
  if (!response.ok || body.status === 'error') {
    throw new ApiError(response.status, body.message || 'Sesi tidak dapat diverifikasi.');
  }
  return (body.data?.role as string | undefined) ?? '';
}

async function adminGet<T>(path: string): Promise<Envelope<T>> {
  const token = getStoredToken();
  if (!token) throw new ApiError(401, 'Tidak terotentikasi.');
  let response: Response;
  try {
    response = await fetch(apiUrl(path), { headers: authHeaders(token) });
  } catch {
    throw new ApiError(null, 'Tidak dapat terhubung ke server.');
  }
  let body: Envelope<T>;
  try {
    body = await response.json() as Envelope<T>;
  } catch {
    throw new ApiError(response.status, 'Respons server tidak valid.');
  }
  if (!response.ok || body.status === 'error') {
    throw new ApiError(response.status, body.message || 'Gagal memuat data.');
  }
  return body;
}

export async function fetchExperiments(cursor = 0, limit = 15, sort = 'created_at', order: 'asc' | 'desc' = 'desc'): Promise<ExperimentListData> {
  const query = new URLSearchParams({ sort, direction: order, order, cursor: String(cursor), limit: String(limit) });
  const dummy = (useDummyStore.getState().dummyEntities as Record<string, unknown>)?.experiments as ExperimentListData | undefined;
  return withDummyRead(selectIsDummy(useDummyStore.getState()), dummy ?? { experiments: [], meta: { has_more: false, cursor, limit, total: 0 } }, async () => {
    const envelope = await adminGet<AbExperiment[]>(`/admin/ai-experiments?${query.toString()}`);
    return { experiments: Array.isArray(envelope.data) ? envelope.data : [], meta: envelope.meta ?? { has_more: false, cursor, limit } };
  });
}

export async function fetchRevenueLift(experimentId: number): Promise<RevenueLiftSnapshot> {
  const dummy = (useDummyStore.getState().dummyEntities as Record<string, unknown>)?.experimentLifts as Record<number, RevenueLiftSnapshot> | undefined;
  const fallback: RevenueLiftSnapshot = {
    id: 0,
    experiment_id: experimentId,
    uplift: null,
    status: 'insufficient-data',
    method_version: 'lift-mean-v1',
    control_sample_size: 0,
    treatment_sample_size: 0,
    control_revenue: '0.00',
    treatment_revenue: '0.00',
    metadata: { sufficient: false, minimum_sample_size: 1 },
    created_at: '',
  };
  return withDummyRead(selectIsDummy(useDummyStore.getState()), dummy?.[experimentId] ?? fallback, async () => {
    const envelope = await adminGet<RevenueLiftSnapshot>(`/admin/ai-experiments/${encodeURIComponent(experimentId)}/revenue-lift`);
    return envelope.data;
  });
}

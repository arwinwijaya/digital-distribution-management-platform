/**
 * Operations API client — read-only consumption of pre-pilot diagnostics.
 * No mutation endpoints; follows apiUrl / authHeaders / getStoredToken conventions.
 */
import { apiUrl, authHeaders, getStoredToken } from '@/lib/api';
import { useDummyStore, selectIsDummy } from '@/dummy/store';
import { withDummyRead } from '@/dummy/guards';
import type {
  IssueDetailResponse,
  IssueFilters,
  IssueListResponse,
  OperationIssue,
  IssueMeta,
  ReadinessData,
  ReadinessResponse,
} from '@/lib/operations-types';

export interface FetchResult<T> {
  ok: boolean;
  data: T | null;
  error: string | null;
  code: string | null;
  status: number;
  raw: unknown;
}

function parseErrorCode(body: unknown): string | null {
  if (body && typeof body === 'object' && 'code' in body) {
    const v = (body as { code?: unknown }).code;
    return typeof v === 'string' ? v : null;
  }
  return null;
}

function parseErrorMessage(body: unknown): string | null {
  if (body && typeof body === 'object' && 'message' in body) {
    const v = (body as { message?: unknown }).message;
    return typeof v === 'string' ? v : null;
  }
  return null;
}

/* ------------------------------------------------------------------ */
/*  Dummy-mode read guards (Option A — per-function)                    */
/* ------------------------------------------------------------------ */

interface DummyOperations {
  readiness?: ReadinessData;
  issues?: OperationIssue[];
}

function dummyOperations(): DummyOperations | null {
  const entities = useDummyStore.getState().dummyEntities as
    | { operations?: DummyOperations }
    | null;
  return entities?.operations ?? null;
}

function dummyResult<T>(data: T): FetchResult<T> {
  return { ok: true, data, error: null, code: null, status: 200, raw: null };
}

/**
 * Resolve an issue detail from the in-memory dummy list. `dummy-1` normalizes
 * to numeric id 1; unknown ids fall back to the first issue so the detail view
 * is never empty while ON (demo-mode invariant).
 */
function resolveDummyIssue(id: string | number): OperationIssue | null {
  const issues = dummyOperations()?.issues ?? [];
  const normalized = String(id).replace(/^dummy-/, '');
  return issues.find((issue) => String(issue.id) === normalized) ?? issues[0] ?? null;
}

export async function fetchReadiness(token?: string): Promise<FetchResult<ReadinessData>> {
  return withDummyRead(
    selectIsDummy(useDummyStore.getState()),
    dummyResult(dummyOperations()?.readiness ?? null) as FetchResult<ReadinessData>,
    () => fetchReadinessReal(token),
  );
}

async function fetchReadinessReal(token?: string): Promise<FetchResult<ReadinessData>> {
  const t = token ?? getStoredToken();
  const res = await fetch(apiUrl('/admin/operations/readiness'), {
    headers: t ? authHeaders(t) : { Accept: 'application/json' },
  });
  let body: unknown = null;
  try {
    body = await res.json();
  } catch {
    /* ignore */
  }
  if (!res.ok) {
    const code = parseErrorCode(body);
    const msg = parseErrorMessage(body) ?? `Kesiapan tidak dapat dimuat (${res.status}).`;
    return { ok: false, data: null, error: msg, code, status: res.status, raw: body };
  }
  const data = (body as ReadinessResponse & { data?: ReadinessData })?.data ?? null;
  return { ok: true, data, error: null, code: null, status: res.status, raw: body };
}

export async function fetchIssues(
  filters: IssueFilters,
  token?: string,
): Promise<FetchResult<{ issues: OperationIssue[]; meta: IssueMeta }>> {
  return withDummyRead(
    selectIsDummy(useDummyStore.getState()),
    dummyResult({
      issues: dummyOperations()?.issues ?? [],
      meta: {
        page: filters.page ?? 1,
        limit: filters.limit ?? 20,
        total: (dummyOperations()?.issues ?? []).length,
        has_more: false,
      },
    }),
    () => fetchIssuesReal(filters, token),
  );
}

async function fetchIssuesReal(
  filters: IssueFilters,
  token?: string,
): Promise<FetchResult<{ issues: OperationIssue[]; meta: IssueMeta }>> {
  const t = token ?? getStoredToken();
  const params = new URLSearchParams();
  if (filters.source) params.set('source', filters.source);
  if (filters.status) params.set('status', filters.status);
  if (filters.severity) params.set('severity', filters.severity);
  if (filters.correlation_id) params.set('correlation_id', filters.correlation_id);
  if (filters.from) params.set('from', filters.from);
  if (filters.to) params.set('to', filters.to);
  if (filters.page != null) params.set('page', String(filters.page));
  if (filters.limit != null) params.set('limit', String(filters.limit));
  const qs = params.toString();
  const url = `${apiUrl('/admin/operations/issues')}${qs ? `?${qs}` : ''}`;
  const res = await fetch(url, { headers: t ? authHeaders(t) : { Accept: 'application/json' } });
  let body: unknown = null;
  try {
    body = await res.json();
  } catch {
    /* ignore */
  }
  if (!res.ok) {
    const code = parseErrorCode(body);
    const msg = parseErrorMessage(body) ?? `Isu tidak dapat dimuat (${res.status}).`;
    return { ok: false, data: null, error: msg, code, status: res.status, raw: body };
  }
  const issues = (body as IssueListResponse & { data?: OperationIssue[]; meta?: IssueMeta }).data ?? [];
  const meta = (body as IssueListResponse & { meta?: IssueMeta }).meta ?? { page: 1, limit: 20, total: 0, has_more: false };
  return { ok: true, data: { issues, meta }, error: null, code: null, status: res.status, raw: body };
}

export async function fetchIssueDetail(
  id: string | number,
  token?: string,
): Promise<FetchResult<OperationIssue>> {
  return withDummyRead(
    selectIsDummy(useDummyStore.getState()),
    dummyResult(resolveDummyIssue(id)) as FetchResult<OperationIssue>,
    () => fetchIssueDetailReal(id, token),
  );
}

async function fetchIssueDetailReal(
  id: string | number,
  token?: string,
): Promise<FetchResult<OperationIssue>> {
  const t = token ?? getStoredToken();
  const res = await fetch(apiUrl(`/admin/operations/issues/${id}`), {
    headers: t ? authHeaders(t) : { Accept: 'application/json' },
  });
  let body: unknown = null;
  try {
    body = await res.json();
  } catch {
    /* ignore */
  }
  if (!res.ok) {
    const code = parseErrorCode(body);
    const msg = parseErrorMessage(body) ?? `Detail isu tidak dapat dimuat (${res.status}).`;
    return { ok: false, data: null, error: msg, code, status: res.status, raw: body };
  }
  const data = (body as IssueDetailResponse & { data?: OperationIssue })?.data ?? null;
  return { ok: true, data, error: null, code: null, status: res.status, raw: body };
}

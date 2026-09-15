/**
 * Operations read-only type definitions.
 * Consumes the pre-pilot operations API contract — no client-side truth.
 */

/* ─── Readiness ─── */

export type ReadinessStatus = 'ready' | 'warning' | 'blocked';

export type CheckStatus = 'ok' | 'warn' | 'fail' | 'skip';

export interface ReadinessCheck {
  name: string;
  status: CheckStatus;
  evidence: string;
  remediation: string;
}

export interface ReadinessData {
  status: ReadinessStatus;
  checks: ReadinessCheck[];
  evaluated_at: string;
  correlation_id: string;
}

/* ─── Issues ─── */

export type IssueSource =
  | 'order_validation'
  | 'product_sync'
  | 'outlet_sync'
  | 'delivery_routing'
  | 'payment_reconciliation'
  | 'recommendation'
  | 'forecast'
  | 'inventory'
  | 'unknown';

export type IssueStatus = 'open' | 'investigating' | 'resolved' | 'dismissed' | 'retrying';

export type IssueSeverity = 'info' | 'low' | 'medium' | 'high' | 'critical';

export interface OperationIssue {
  id: number | string;
  source: IssueSource;
  reference: string | null;
  status: IssueStatus;
  severity: IssueSeverity;
  attempts: number;
  occurred_at: string;
  error_class: string | null;
  correlation_id: string | null;
  next_action: string | null;
  detail?: Record<string, unknown>;
}

export interface IssueMeta {
  page: number;
  limit: number;
  total: number;
  has_more: boolean;
}

/* ─── Filters ─── */

export interface IssueFilters {
  source?: IssueSource;
  status?: IssueStatus;
  severity?: IssueSeverity;
  correlation_id?: string;
  from?: string;
  to?: string;
  page?: number;
  limit?: number;
}

/* ─── API Responses ─── */

export interface ApiSuccessResponse<T> {
  status: 'success';
  data: T;
  message?: string;
}

export interface ApiErrorResponse {
  status: 'error';
  code?: string;
  message?: string;
}

export interface PagedIssuesResponse {
  status: 'success';
  data: OperationIssue[];
  meta: IssueMeta;
}

export type ReadinessResponse = ApiSuccessResponse<ReadinessData> | ApiErrorResponse;
export type IssueDetailResponse = ApiSuccessResponse<OperationIssue> | ApiErrorResponse;
export type IssueListResponse = PagedIssuesResponse | ApiErrorResponse;

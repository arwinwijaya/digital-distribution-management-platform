'use client';

import { FormEvent, useCallback, useEffect, useState } from 'react';
import LoginForm from '@/components/LoginForm';
import { apiUrl, authHeaders, getStoredToken } from '@/lib/api';
import { fetchReadiness, fetchIssues, fetchIssueDetail } from '@/lib/operations-api';
import type {
  IssueSeverity,
  IssueSource,
  IssueStatus,
  OperationIssue,
  ReadinessData,
  IssueMeta,
} from '@/lib/operations-types';
import { Badge, Button, Card, EmptyState, Input, Modal, PageHeader, Select } from '@/components/ui';

type OpsState = {
  readiness: ReadinessData | null;
  issues: OperationIssue[];
  meta: IssueMeta;
  detail: OperationIssue | null;
  readinessState: 'idle' | 'loading' | 'success' | 'disabled' | 'error';
  issuesState: 'idle' | 'loading' | 'success' | 'disabled' | 'error';
  detailState: 'idle' | 'loading' | 'success' | 'error';
  status: 'idle' | 'loading' | 'success' | 'disabled' | 'error';
  detailLoading: boolean;
  ready: boolean;
  error: string | null;
  disabled: boolean;
  detailId: number | string | null;
  token: string | null;
  role: string | null;
};

function readinessBadgeVariant(status: string): 'green' | 'yellow' | 'red' {
  if (status === 'ready') return 'green';
  if (status === 'warning') return 'yellow';
  return 'red';
}

function checkBadgeVariant(status: string): 'green' | 'yellow' | 'red' | 'gray' {
  if (status === 'ok') return 'green';
  if (status === 'warn') return 'yellow';
  if (status === 'fail') return 'red';
  return 'gray';
}

function severityBadgeVariant(severity: string): 'red' | 'yellow' | 'green' | 'blue' | 'gray' {
  if (severity === 'critical') return 'red';
  if (severity === 'high') return 'yellow';
  if (severity === 'medium') return 'blue';
  if (severity === 'low') return 'green';
  return 'gray';
}

const SOURCE_OPTIONS: Array<{ value: '' | IssueSource; label: string }> = [
  { value: '', label: 'Semua sumber' },
  { value: 'order_validation', label: 'order_validation' },
  { value: 'product_sync', label: 'product_sync' },
  { value: 'outlet_sync', label: 'outlet_sync' },
  { value: 'delivery_routing', label: 'delivery_routing' },
  { value: 'payment_reconciliation', label: 'payment_reconciliation' },
  { value: 'recommendation', label: 'recommendation' },
  { value: 'forecast', label: 'forecast' },
  { value: 'inventory', label: 'inventory' },
  { value: 'unknown', label: 'unknown' },
];

const STATUS_OPTIONS: Array<{ value: '' | IssueStatus; label: string }> = [
  { value: '', label: 'Semua status' },
  { value: 'open', label: 'open' },
  { value: 'investigating', label: 'investigating' },
  { value: 'resolved', label: 'resolved' },
  { value: 'dismissed', label: 'dismissed' },
  { value: 'retrying', label: 'retrying' },
];

const SEVERITY_OPTIONS: Array<{ value: '' | IssueSeverity; label: string }> = [
  { value: '', label: 'Semua severity' },
  { value: 'info', label: 'info' },
  { value: 'low', label: 'low' },
  { value: 'medium', label: 'medium' },
  { value: 'high', label: 'high' },
  { value: 'critical', label: 'critical' },
];

function DisabledNotice({ onRefresh }: { onRefresh: () => void }) {
  return (
    <Card className="p-6 text-center">
      <p className="text-lg font-semibold text-gray-900">Aktivasi diperlukan</p>
      <p className="mt-2 text-sm text-gray-500">
        Fitur pre-pilot sedang dinonaktifkan (pre_pilot_disabled). Hubungi administrator untuk mengaktifkannya.
      </p>
      <div className="mt-4">
        <Button onClick={onRefresh} variant="secondary">Muat ulang</Button>
      </div>
    </Card>
  );
}

function ReadinessCard({ data, loading }: { data: ReadinessData | null; loading: boolean }) {
  if (loading && !data) return <p className="text-sm text-gray-500">Memuat kesiapan...</p>;
  if (!data) return null;
  return (
    <Card className="mb-6 p-5">
      <div className="mb-4 flex flex-wrap items-center gap-3">
        <h2 className="text-base font-semibold text-gray-900">Kesiapan operasional</h2>
        <Badge variant={readinessBadgeVariant(data.status)}>{data.status}</Badge>
        <span className="text-xs text-gray-400">correlation: {data.correlation_id}</span>
      </div>
      <div className="overflow-x-auto">
        <table data-testid="readiness-checks-table" className="min-w-full divide-y divide-gray-200 text-sm">
          <thead>
            <tr className="text-left text-xs uppercase tracking-wide text-gray-500">
              <th className="px-3 py-2">Check</th>
              <th className="px-3 py-2">Status</th>
              <th className="px-3 py-2">Evidence</th>
              <th className="px-3 py-2">Remediation</th>
            </tr>
          </thead>
          <tbody className="divide-y divide-gray-100">
            {data.checks.map((check) => (
              <tr key={check.name}>
                <td className="px-3 py-2 font-medium text-gray-900">{check.name}</td>
                <td className="px-3 py-2">
                  <Badge variant={checkBadgeVariant(check.status)}>{check.status}</Badge>
                </td>
                <td className="px-3 py-2 text-gray-600">{check.evidence}</td>
                <td className="px-3 py-2 text-gray-600">{check.remediation}</td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </Card>
  );
}

function IssuesTable({
  issues,
  onSelect,
}: {
  issues: OperationIssue[];
  onSelect: (id: string | number) => void;
}) {
  if (issues.length === 0) {
    return <EmptyState title="Tidak ada isu" description="Belum ada isu operasional pada filter ini." />;
  }
  return (
    <div className="overflow-x-auto">
      <table data-testid="operations-issues-table" className="min-w-full divide-y divide-gray-200 text-sm">
        <thead>
          <tr className="text-left text-xs uppercase tracking-wide text-gray-500">
            <th className="px-3 py-2">Source</th>
            <th className="px-3 py-2">Reference</th>
            <th className="px-3 py-2">Status</th>
            <th className="px-3 py-2">Severity</th>
            <th className="px-3 py-2">Attempts</th>
            <th className="px-3 py-2">Occurred</th>
            <th className="px-3 py-2">Correlation</th>
            <th className="px-3 py-2">Next action</th>
          </tr>
        </thead>
        <tbody className="divide-y divide-gray-100">
          {issues.map((issue) => (
            <tr
              key={issue.id}
              className="cursor-pointer hover:bg-gray-50"
              onClick={() => onSelect(issue.id)}
            >
              <td className="px-3 py-2 font-medium text-gray-900">{issue.source}</td>
              <td className="px-3 py-2 text-gray-600">{issue.reference}</td>
              <td className="px-3 py-2">
                <Badge variant="blue">{issue.status}</Badge>
              </td>
              <td className="px-3 py-2">
                <Badge variant={severityBadgeVariant(issue.severity)}>{issue.severity}</Badge>
              </td>
              <td className="px-3 py-2 text-gray-700">{String(issue.attempts)}</td>
              <td className="px-3 py-2 text-gray-600">{issue.occurred_at}</td>
              <td className="px-3 py-2 font-mono text-xs text-gray-500">{issue.correlation_id}</td>
              <td className="px-3 py-2 text-gray-600">{issue.next_action}</td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );
}

function FiltersForm({
  source,
  status,
  severity,
  correlationId,
  from,
  to,
  loading,
  onSourceChange,
  onStatusChange,
  onSeverityChange,
  onCorrelationIdChange,
  onFromChange,
  onToChange,
  onSubmit,
}: {
  source: string;
  status: string;
  severity: string;
  correlationId: string;
  from: string;
  to: string;
  loading: boolean;
  onSourceChange: (v: string) => void;
  onStatusChange: (v: string) => void;
  onSeverityChange: (v: string) => void;
  onCorrelationIdChange: (v: string) => void;
  onFromChange: (v: string) => void;
  onToChange: (v: string) => void;
  onSubmit: (e: FormEvent<HTMLFormElement>) => void;
}) {
  return (
    <Card className="mb-6 p-4">
      <form onSubmit={onSubmit} className="grid items-end gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <Select label="Source" value={source} onChange={(e) => onSourceChange(e.target.value)}>
          {SOURCE_OPTIONS.map((o) => (
            <option key={o.value} value={o.value}>{o.label}</option>
          ))}
        </Select>
        <Select label="Status" value={status} onChange={(e) => onStatusChange(e.target.value)}>
          {STATUS_OPTIONS.map((o) => (
            <option key={o.value} value={o.value}>{o.label}</option>
          ))}
        </Select>
        <Select label="Severity" value={severity} onChange={(e) => onSeverityChange(e.target.value)}>
          {SEVERITY_OPTIONS.map((o) => (
            <option key={o.value} value={o.value}>{o.label}</option>
          ))}
        </Select>
        <Input
          label="Correlation ID"
          value={correlationId}
          onChange={(e) => onCorrelationIdChange(e.target.value)}
          placeholder="correlation_id"
        />
        <Input label="Dari tanggal" type="date" value={from} onChange={(e) => onFromChange(e.target.value)} />
        <Input label="Sampai tanggal" type="date" value={to} onChange={(e) => onToChange(e.target.value)} />
        <Button type="submit" disabled={loading}>{loading ? 'Memuat...' : 'Terapkan filter'}</Button>
      </form>
    </Card>
  );
}

export default function OperationsPage() {
  const [state, setState] = useState<OpsState>({
    readiness: null,
    issues: [],
    meta: { page: 1, limit: 20, total: 0, has_more: false },
    detail: null,
    readinessState: 'idle',
    issuesState: 'idle',
    detailState: 'idle',
    status: 'idle',
    detailLoading: false,
    ready: false,
    error: null,
    disabled: false,
    detailId: null,
    token: null,
    role: null,
  });
  const [source, setSource] = useState('');
  const [issueStatus, setIssueStatus] = useState('');
  const [severity, setSeverity] = useState('');
  const [correlationId, setCorrelationId] = useState('');
  const [from, setFrom] = useState('');
  const [to, setTo] = useState('');
  const [page, setPage] = useState(1);

  const limit = 20;

  const loadAll = useCallback(
    async (authToken: string, nextPage: number, filters?: { source?: string; status?: string; severity?: string; correlation_id?: string; from?: string; to?: string }) => {
      setState((s) => ({ ...s, readinessState: 'loading', issuesState: 'loading', error: null }));
      const applied = filters ?? { source, status: issueStatus, severity, correlation_id: correlationId, from, to };
      const readinessResult = await fetchReadiness(authToken);
      const issuesResult = await fetchIssues(
        {
          source: (applied.source || undefined) as IssueSource | undefined,
          status: (applied.status || undefined) as IssueStatus | undefined,
          severity: (applied.severity || undefined) as IssueSeverity | undefined,
          correlation_id: applied.correlation_id || undefined,
          from: applied.from || undefined,
          to: applied.to || undefined,
          page: nextPage,
          limit,
        },
        authToken,
      );

      const readinessDisabled =
        !readinessResult.ok && (readinessResult.code === 'pre_pilot_disabled' || readinessResult.status === 503);
      const issuesDisabled =
        !issuesResult.ok && (issuesResult.code === 'pre_pilot_disabled' || issuesResult.status === 503);

      if (readinessDisabled || issuesDisabled) {
        setState((s) => ({
          ...s,
          readinessState: readinessDisabled ? 'disabled' : readinessResult.ok ? 'success' : 'error',
          issuesState: issuesDisabled ? 'disabled' : issuesResult.ok ? 'success' : 'error',
          readiness: readinessResult.data ?? s.readiness,
          disabled: true,
          error: null,
        }));
        return;
      }

      const failure = !readinessResult.ok ? readinessResult : !issuesResult.ok ? issuesResult : null;
      if (failure) {
        setState((s) => ({
          ...s,
          readinessState: readinessResult.ok ? 'success' : 'error',
          issuesState: issuesResult.ok ? 'success' : 'error',
          readiness: readinessResult.data ?? s.readiness,
          issues: issuesResult.data?.issues ?? s.issues,
          meta: issuesResult.data?.meta ?? s.meta,
          disabled: false,
          error: failure.error ?? 'Data operasi tidak dapat dimuat.',
        }));
        return;
      }

      setState((s) => ({
        ...s,
        readiness: readinessResult.data,
        issues: issuesResult.data?.issues ?? [],
        meta: issuesResult.data?.meta ?? { page: nextPage, limit, total: 0, has_more: false },
        readinessState: 'success',
        issuesState: 'success',
        disabled: false,
        error: null,
      }));
    },
    [source, issueStatus, severity, correlationId, from, to],
  );

  // Auth bootstrap: stored token → /auth/me → role check.
  useEffect(() => {
    const storedToken = getStoredToken();
    if (!storedToken) {
      setState((s) => ({ ...s, ready: true }));
      return;
    }
    let active = true;
    fetch(apiUrl('/auth/me'), { headers: authHeaders(storedToken) })
      .then(async (response) => {
        const body = await response.json();
        if (!response.ok) throw new Error(body.message || 'Sesi tidak dapat diverifikasi.');
        return body.data.role as string;
      })
      .then((currentRole) => {
        if (!active) return;
        setState((s) => ({ ...s, token: storedToken, role: currentRole, ready: true }));
        if (currentRole === 'admin') void loadAll(storedToken, 1);
        else setState((s) => ({ ...s, error: 'Halaman ini khusus untuk administrator.' }));
      })
      .catch((reason) => {
        if (!active) return;
        setState((s) => ({
          ...s,
          ready: true,
          error: reason instanceof Error ? reason.message : 'Sesi tidak dapat diverifikasi.',
        }));
      });
    return () => {
      active = false;
    };
  }, [loadAll]);

  const handleSelect = useCallback(
    async (id: string | number) => {
      setState((s) => ({ ...s, detailId: id, detailLoading: true, detailState: 'loading', detail: null }));
      const result = await fetchIssueDetail(id, state.token ?? undefined);
      if (!result.ok) {
        setState((s) => ({
          ...s,
          detailLoading: false,
          detailState: 'error',
          error: result.error ?? 'Detail isu tidak dapat dimuat.',
        }));
        return;
      }
      setState((s) => ({ ...s, detail: result.data, detailLoading: false, detailState: 'success' }));
    },
    [state.token],
  );

  const closeDetail = useCallback(() => {
    setState((s) => ({ ...s, detailId: null, detail: null, detailState: 'idle' }));
  }, []);

  if (!state.ready) return <p className="text-sm text-gray-500">Memuat...</p>;
  if (!state.token) {
    return (
      <div className="mx-auto max-w-6xl">
        <PageHeader title="Operasi" description="Kesiapan pre-pilot dan isu operasional." />
        <LoginForm
          expectedRole={['admin']}
          onLogin={(nextToken, nextRole) => {
            setState((s) => ({ ...s, token: nextToken, role: nextRole, ready: true }));
            if (nextRole === 'admin') void loadAll(nextToken, 1);
            else setState((s) => ({ ...s, error: 'Halaman ini khusus untuk administrator.' }));
          }}
        />
      </div>
    );
  }

  const loading = state.readinessState === 'loading' || state.issuesState === 'loading';

  return (
    <div className="mx-auto max-w-6xl">
      <PageHeader
        title="Operasi"
        description="Kesiapan pre-pilot dan isu operasional."
        action={
          <Button
            variant="secondary"
            disabled={loading}
            onClick={() => state.token && void loadAll(state.token, page)}
          >
            {loading ? 'Memuat...' : 'Muat ulang'}
          </Button>
        }
      />

      {state.error && (
        <p role="alert" className="mb-6 rounded-lg border border-danger-200 bg-danger-50 p-3 text-sm text-danger-700">
          {state.error}
        </p>
      )}

      {state.disabled ? (
        <DisabledNotice onRefresh={() => state.token && void loadAll(state.token, page)} />
      ) : (
        <>
          <ReadinessCard data={state.readiness} loading={state.readinessState === 'loading'} />

          <FiltersForm
            source={source}
            status={issueStatus}
            severity={severity}
            correlationId={correlationId}
            from={from}
            to={to}
            loading={loading}
            onSourceChange={setSource}
            onStatusChange={setIssueStatus}
            onSeverityChange={setSeverity}
            onCorrelationIdChange={setCorrelationId}
            onFromChange={setFrom}
            onToChange={setTo}
            onSubmit={(e: FormEvent<HTMLFormElement>) => {
              e.preventDefault();
              if (state.token) {
                setPage(1);
                void loadAll(state.token, 1, { source, status: issueStatus, severity, correlation_id: correlationId, from, to });
              }
            }}
          />

          <Card className="p-5">
            <h2 className="mb-4 text-base font-semibold text-gray-900">Isu operasional</h2>
            {state.issuesState === 'loading' && state.issues.length === 0 ? (
              <p className="text-sm text-gray-500">Memuat isu...</p>
            ) : (
              <IssuesTable issues={state.issues} onSelect={handleSelect} />
            )}
            <div className="mt-4 flex items-center justify-between">
              <p className="text-xs text-gray-500">
                Halaman {state.meta.page} · {state.meta.total} total
              </p>
              <div className="flex gap-2">
                <Button
                  variant="secondary"
                  disabled={loading || state.meta.page <= 1}
                  onClick={() => {
                    const next = page - 1;
                    setPage(next);
                    if (state.token) void loadAll(state.token, next);
                  }}
                >
                  Sebelumnya
                </Button>
                <Button
                  variant="secondary"
                  disabled={loading || !state.meta.has_more}
                  onClick={() => {
                    const next = page + 1;
                    setPage(next);
                    if (state.token) void loadAll(state.token, next);
                  }}
                >
                  Berikutnya
                </Button>
              </div>
            </div>
          </Card>
        </>
      )}

      <Modal open={state.detailId !== null} onClose={closeDetail} title="Detail isu">
        {state.detailLoading ? (
          <p className="text-sm text-gray-500">Memuat detail...</p>
        ) : state.detail ? (
          <dl className="space-y-2">
            <div><dt className="text-xs font-medium uppercase text-gray-500">Source</dt><dd>{state.detail.source}</dd></div>
            <div><dt className="text-xs font-medium uppercase text-gray-500">Reference</dt><dd>{state.detail.reference}</dd></div>
            <div><dt className="text-xs font-medium uppercase text-gray-500">Status</dt><dd>{state.detail.status}</dd></div>
            <div><dt className="text-xs font-medium uppercase text-gray-500">Severity</dt><dd>{state.detail.severity}</dd></div>
            <div><dt className="text-xs font-medium uppercase text-gray-500">Attempts</dt><dd>{String(state.detail.attempts)}</dd></div>
            <div><dt className="text-xs font-medium uppercase text-gray-500">Occurred</dt><dd>{state.detail.occurred_at}</dd></div>
            <div><dt className="text-xs font-medium uppercase text-gray-500">Error class</dt><dd>{state.detail.error_class}</dd></div>
            <div><dt className="text-xs font-medium uppercase text-gray-500">Correlation</dt><dd className="font-mono text-xs">{state.detail.correlation_id}</dd></div>
            <div><dt className="text-xs font-medium uppercase text-gray-500">Next action</dt><dd>{state.detail.next_action}</dd></div>
            {state.detail.detail && (
              <div>
                <dt className="text-xs font-medium uppercase text-gray-500">Detail</dt>
                <dd><pre className="mt-1 overflow-auto rounded bg-gray-50 p-2 text-xs">{JSON.stringify(state.detail.detail, null, 2)}</pre></dd>
              </div>
            )}
          </dl>
        ) : (
          <p className="text-sm text-gray-500">Detail tidak tersedia.</p>
        )}
      </Modal>
    </div>
  );
}

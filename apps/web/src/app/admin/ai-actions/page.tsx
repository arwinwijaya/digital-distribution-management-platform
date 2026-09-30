'use client';

import { useCallback, useEffect, useRef, useState } from 'react';
import LoginForm from '@/components/LoginForm';
import { clearStoredToken, getStoredToken } from '@/lib/api';
import { Button, Card, EmptyState, PageHeader, StatusBadge, Table, TableDensityToggle, TablePagination, TableSummary } from '@/components/ui';
import { formatDateTime, toggleSort, type ColumnSort } from '@/lib/admin-table';
import { useTableDensity } from '@/hooks/useTableDensity';
import { ApiError } from '@/lib/api-error';
import {
  approveAction,
  executeAction,
  fetchRecommendationActions,
  rejectAction,
  verifyAdminAccess,
  type RecommendationAction,
  type RecommendationActionsListResult,
} from '@/lib/ai-actions-api';

const PAGE_LIMIT = 15;
const DRAFT_STATUSES = new Set(['draft', 'pending_approval']);

type GuardState = {
  token: string | null;
  ready: boolean;
  accessError: string | null;
  setToken: (token: string | null) => void;
};

function useAdminGuard(): GuardState {
  const [token, setToken] = useState<string | null>(null);
  const [ready, setReady] = useState(false);
  const [accessError, setAccessError] = useState<string | null>(null);

  useEffect(() => {
    const stored = getStoredToken();
    if (!stored) {
      setReady(true);
      return;
    }

    let active = true;
    verifyAdminAccess(stored)
      .then((role) => {
        if (!active) return;
        if (role === 'admin' || role === 'platform_owner') {
          setToken(stored);
        } else {
          setAccessError('Akses ditolak');
        }
        setReady(true);
      })
      .catch((reason) => {
        if (!active) return;
        if (reason instanceof ApiError && reason.status === 401) {
          clearStoredToken();
          setAccessError('Sesi berakhir. Silakan masuk kembali');
        } else {
          setAccessError(reason instanceof Error ? reason.message : 'Sesi tidak dapat diverifikasi.');
        }
        setReady(true);
      });

    return () => { active = false; };
  }, []);

  return { token, ready, accessError, setToken };
}

function titleFromPayload(action: RecommendationAction): string {
  const payload = action.payload ?? {};
  const candidates = [
    payload.outlet_name,
    payload.name,
    payload.product_name,
    payload.title,
    payload.order_id,
  ];
  const found = candidates.find((value) => typeof value === 'string' && value.trim() !== '');
  if (typeof found === 'string') return found;
  if (action.outlet_id) return `Outlet #${action.outlet_id}`;
  return `Aksi #${action.id}`;
}

function typeLabel(type: RecommendationAction['type']): string {
  if (type === 'draft_campaign') return 'Draft campaign';
  return 'Draft order';
}

function buildSummary(result: RecommendationActionsListResult | null, rows: RecommendationAction[]) {
  const total = result?.total ?? rows.length;
  const summary = result?.summary;
  if (summary) {
    return {
      total,
      breakdown: [
        { label: 'draft', value: summary.draft ?? 0 },
        { label: 'pending', value: summary.pending ?? 0 },
        { label: 'approved', value: summary.approved ?? 0 },
        { label: 'executed', value: summary.executed ?? 0 },
      ],
    };
  }
  return {
    total,
    breakdown: [
      { label: 'draft', value: rows.filter((a) => a.status === 'draft').length },
      { label: 'pending', value: rows.filter((a) => a.status === 'pending_approval').length },
      { label: 'approved', value: rows.filter((a) => a.status === 'approved').length },
      { label: 'executed', value: rows.filter((a) => a.status === 'executed').length },
    ],
  };
}

export default function AdminAiActionsPage() {
  const { token, ready, accessError, setToken } = useAdminGuard();
  const [actions, setActions] = useState<RecommendationAction[]>([]);
  const [lastResult, setLastResult] = useState<RecommendationActionsListResult | null>(null);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [actionSuccess, setActionSuccess] = useState<string | null>(null);
  const [sort, setSort] = useState<ColumnSort>({ column: 'created_at', order: 'desc' });
  const [cursor, setCursor] = useState(0);
  const [hasMore, setHasMore] = useState(false);
  const [total, setTotal] = useState<number | undefined>(undefined);
  const { density, setDensity } = useTableDensity();

  const sortRef = useRef(sort);
  sortRef.current = sort;
  const cursorRef = useRef(cursor);
  cursorRef.current = cursor;

  const loadActions = useCallback(async (authToken: string, opts?: { resetCursor?: boolean; cursor?: number; sort?: ColumnSort }) => {
    setLoading(true);
    setError(null);
    try {
      const nextSort = opts?.sort ?? sortRef.current;
      const nextCursor = opts?.cursor ?? (opts?.resetCursor ? 0 : cursorRef.current);
      const result = await fetchRecommendationActions(authToken, {
        limit: PAGE_LIMIT,
        cursor: nextCursor,
        sort: nextSort.column,
        order: nextSort.order,
      });
      setActions(result.actions);
      setLastResult(result);
      setHasMore(result.hasMore);
      setCursor(result.cursor);
      if (result.total !== undefined) setTotal(result.total);
    } catch (reason) {
      setError(reason instanceof Error ? reason.message : 'Daftar aksi rekomendasi tidak dapat dimuat.');
      setActions([]);
      setLastResult(null);
      setHasMore(false);
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    if (token) void loadActions(token, { resetCursor: true });
  }, [token, loadActions]);

  async function runAction(actionId: number, action: 'approve' | 'reject' | 'execute') {
    if (!token) return;
    setError(null);
    setActionSuccess(null);
    try {
      if (action === 'approve') {
        await approveAction(token, actionId);
        setActionSuccess('Aksi disetujui.');
      } else if (action === 'reject') {
        await rejectAction(token, actionId, 'Ditolak dari inbox admin.');
        setActionSuccess('Aksi ditolak.');
      } else {
        await executeAction(token, actionId);
        setActionSuccess('Aksi dieksekusi.');
      }
      await loadActions(token);
    } catch (reason) {
      setError(reason instanceof Error ? reason.message : 'Operasi aksi gagal.');
    }
  }

  if (!ready) return <p className="text-sm text-gray-500">Memuat…</p>;

  if (accessError) {
    return (
      <div className="mx-auto max-w-6xl">
        <PageHeader title="Inbox Aksi AI" description="Tinjau rekomendasi draft order dan campaign sebelum dieksekusi." />
        <div role="alert" className="mb-5 rounded-lg border border-danger-200 bg-danger-50 p-3 text-sm text-danger-700">
          {accessError}
        </div>
        <LoginForm expectedRole="admin" onLogin={(nextToken) => setToken(nextToken)} />
      </div>
    );
  }

  if (!token) {
    return (
      <div className="mx-auto max-w-6xl">
        <PageHeader title="Inbox Aksi AI" description="Tinjau rekomendasi draft order dan campaign sebelum dieksekusi." />
        <div className="mb-5 rounded-lg border border-warning-200 bg-warning-50 p-3 text-sm text-warning-700">
          Masuk sebagai administrator untuk mengelola aksi AI.
        </div>
        <LoginForm expectedRole="admin" onLogin={(nextToken) => setToken(nextToken)} />
      </div>
    );
  }

  const summary = buildSummary(lastResult, actions);
  const columns = [
    {
      key: 'id',
      header: 'Aksi',
      render: (action: RecommendationAction) => (
        <span>
          <span className="font-semibold text-gray-900">{titleFromPayload(action)}</span>
          <span className="mt-1 block text-xs text-gray-500">#{action.id} · {typeLabel(action.type)}</span>
        </span>
      ),
    },
    {
      key: 'status',
      header: 'Status',
      render: (action: RecommendationAction) => <StatusBadge status={action.status} />,
    },
    {
      key: 'method',
      header: 'Metode',
      render: (action: RecommendationAction) => (
        <span className="text-xs text-gray-600">
          {action.method ?? '—'}{action.method_version ? ` · ${action.method_version}` : ''}{action.fallback ? ' · fallback' : ''}
        </span>
      ),
    },
    {
      key: 'data_sufficiency',
      header: 'Data',
      render: (action: RecommendationAction) => <span className="text-xs text-gray-600">{action.data_sufficiency ?? '—'}</span>,
    },
    {
      key: 'created_at',
      header: 'Dibuat',
      render: (action: RecommendationAction) => <span className="text-xs text-gray-600">{formatDateTime(action.created_at)}</span>,
    },
    {
      key: 'updated_at',
      header: 'Diperbarui',
      render: (action: RecommendationAction) => <span className="text-xs text-gray-600">{formatDateTime(action.updated_at)}</span>,
    },
    {
      key: 'action',
      header: 'Aksi',
      render: (action: RecommendationAction) => (
        <span className="flex flex-wrap gap-1.5">
          {DRAFT_STATUSES.has(action.status) ? (
            <>
              <Button size="sm" onClick={() => void runAction(action.id, 'approve')}>Setujui</Button>
              <Button size="sm" variant="danger" onClick={() => void runAction(action.id, 'reject')}>Tolak</Button>
            </>
          ) : null}
          {action.status === 'approved' ? (
            <Button size="sm" onClick={() => void runAction(action.id, 'execute')}>Eksekusi</Button>
          ) : null}
          {!DRAFT_STATUSES.has(action.status) && action.status !== 'approved' ? (
            <span className="text-xs text-gray-500">Tidak ada aksi</span>
          ) : null}
        </span>
      ),
    },
  ];

  return (
    <div className="mx-auto max-w-6xl">
      <PageHeader title="Inbox Aksi AI" description="Tinjau rekomendasi draft order dan campaign sebelum dieksekusi." />
      {error && <p role="alert" className="mb-5 rounded-lg border border-danger-200 bg-danger-50 p-3 text-sm text-danger-700">{error}</p>}
      {actionSuccess && <p className="mb-3 rounded-lg border border-success-200 bg-success-50 p-3 text-sm text-success-700">{actionSuccess}</p>}
      <Card className="overflow-hidden">
        <div className="flex flex-wrap items-center justify-between gap-3 border-b border-gray-100 px-5 py-3">
          <TableSummary total={summary.total} noun="aksi" breakdown={summary.breakdown} />
          <TableDensityToggle value={density} onChange={setDensity} />
        </div>
        {loading && actions.length === 0 ? (
          <p className="p-8 text-sm text-gray-500">Memuat aksi AI...</p>
        ) : (
          <Table
            columns={columns}
            rows={actions}
            rowKey={(action) => action.id}
            density={density}
            sortableColumns={['created_at', 'updated_at', 'id', 'status', 'type']}
            sort={sort}
            onSort={(column) => {
              const next = toggleSort(sortRef.current, column);
              setSort(next);
              if (token) void loadActions(token, { resetCursor: true, sort: next });
            }}
            empty={<EmptyState icon={<span>🤖</span>} title="Belum ada aksi AI" description="Rekomendasi yang membutuhkan approval akan muncul di sini." />}
          />
        )}
        <TablePagination
          cursor={cursor}
          limit={PAGE_LIMIT}
          total={total}
          hasMore={hasMore}
          onPageChange={(nextCursor) => {
            setCursor(nextCursor);
            if (token) void loadActions(token, { cursor: nextCursor });
          }}
        />
      </Card>
    </div>
  );
}

'use client';

import { useCallback, useEffect, useRef, useState } from 'react';
import LoginForm from '@/components/LoginForm';
import { clearStoredToken, getStoredToken } from '@/lib/api';
import { ApiError } from '@/lib/api-error';
import {
  Button,
  Card,
  EmptyState,
  PageHeader,
  StatusBadge,
  Table,
  TableDensityToggle,
  TablePagination,
  TableSummary,
} from '@/components/ui';
import { formatDateTime, toggleSort, type ColumnSort } from '@/lib/admin-table';
import { useTableDensity } from '@/hooks/useTableDensity';
import {
  approvePlan,
  executePlan,
  fetchReplenishmentPlans,
  generatePlan,
  verifyAdminAccess,
  type ReplenishmentPlan,
  type ReplenishmentPlansListResult,
} from '@/lib/replenishment-api';

const PAGE_LIMIT = 15;
const TERMINAL_STATUSES = new Set(['executed', 'failed', 'rejected', 'cancelled']);

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

function poReferences(plan: ReplenishmentPlan): string[] {
  const purchaseOrders = plan.execution_result?.purchase_orders;
  if (!Array.isArray(purchaseOrders)) return [];
  return purchaseOrders.flatMap((purchaseOrder) => {
    if (!purchaseOrder || typeof purchaseOrder !== 'object') return [];
    const reference = (purchaseOrder as { reference?: unknown }).reference;
    return typeof reference === 'string' && reference.trim() ? [reference] : [];
  });
}

function summaryFor(result: ReplenishmentPlansListResult | null, rows: ReplenishmentPlan[]) {
  const summary = result?.summary;
  const total = result?.total ?? rows.length;
  return {
    total,
    breakdown: [
      { label: 'draft', value: summary?.draft ?? rows.filter((p) => p.status === 'draft').length },
      { label: 'approved', value: summary?.approved ?? rows.filter((p) => p.status === 'approved').length },
      { label: 'executed', value: summary?.executed ?? rows.filter((p) => p.status === 'executed').length },
    ],
  };
}

function defaultGenerateWindow(): { window_start: string; window_end: string } {
  const now = new Date();
  const start = new Date(now.getFullYear(), now.getMonth(), 1);
  const end = new Date(now.getFullYear(), now.getMonth() + 1, 0);
  const date = (value: Date) => value.toISOString().slice(0, 10);
  return { window_start: date(start), window_end: date(end) };
}

export default function SupplyChainPage() {
  const { token, ready, accessError, setToken } = useAdminGuard();
  const [plans, setPlans] = useState<ReplenishmentPlan[]>([]);
  const [lastResult, setLastResult] = useState<ReplenishmentPlansListResult | null>(null);
  const [loading, setLoading] = useState(false);
  const [hasLoaded, setHasLoaded] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [success, setSuccess] = useState<string | null>(null);
  const [sort, setSort] = useState<ColumnSort>({ column: 'created_at', order: 'desc' });
  const [cursor, setCursor] = useState(0);
  const [hasMore, setHasMore] = useState(false);
  const [total, setTotal] = useState<number | undefined>(undefined);
  const { density, setDensity } = useTableDensity();
  const sortRef = useRef(sort);
  sortRef.current = sort;
  const cursorRef = useRef(cursor);
  cursorRef.current = cursor;

  const loadPlans = useCallback(async (authToken: string, opts?: { resetCursor?: boolean; cursor?: number; sort?: ColumnSort }) => {
    setLoading(true);
    setError(null);
    try {
      const nextSort = opts?.sort ?? sortRef.current;
      const nextCursor = opts?.cursor ?? (opts?.resetCursor ? 0 : cursorRef.current);
      const result = await fetchReplenishmentPlans(authToken, {
        limit: PAGE_LIMIT,
        cursor: nextCursor,
        sort: nextSort.column,
        order: nextSort.order,
      });
      setPlans(result.plans);
      setLastResult(result);
      setHasMore(result.hasMore);
      setCursor(result.cursor);
      if (result.total !== undefined) setTotal(result.total);
    } catch (reason) {
      setError(reason instanceof Error ? reason.message : 'Daftar rencana replenishment tidak dapat dimuat.');
      setPlans([]);
      setLastResult(null);
      setHasMore(false);
    } finally {
      setLoading(false);
      setHasLoaded(true);
    }
  }, []);

  useEffect(() => {
    if (token) void loadPlans(token, { resetCursor: true });
  }, [token, loadPlans]);

  async function runGenerate() {
    if (!token) return;
    setError(null);
    setSuccess(null);
    try {
      await generatePlan(token, defaultGenerateWindow());
      setSuccess('Rencana replenishment berhasil dibuat.');
      await loadPlans(token, { resetCursor: true });
    } catch (reason) {
      setError(reason instanceof Error ? reason.message : 'Gagal membuat rencana replenishment.');
    }
  }

  async function runAction(planId: number, action: 'approve' | 'execute') {
    if (!token) return;
    setError(null);
    setSuccess(null);
    try {
      if (action === 'approve') {
        await approvePlan(token, planId);
        setSuccess('Rencana disetujui.');
      } else {
        await executePlan(token, planId, `replenishment-plan-${planId}`);
        setSuccess('Rencana dieksekusi.');
      }
      await loadPlans(token);
    } catch (reason) {
      setError(reason instanceof Error ? reason.message : 'Operasi rencana replenishment gagal.');
    }
  }

  if (!ready) return <p className="text-sm text-gray-500">Memuat…</p>;

  if (accessError) {
    return (
      <div className="mx-auto max-w-6xl">
        <PageHeader title="Replenishment" description="Kelola rencana pengadaan dan draft purchase order." />
        <div role="alert" className="mb-5 rounded-lg border border-danger-200 bg-danger-50 p-3 text-sm text-danger-700">{accessError}</div>
        <LoginForm expectedRole="admin" onLogin={(nextToken) => setToken(nextToken)} />
      </div>
    );
  }

  if (!token) {
    return (
      <div className="mx-auto max-w-6xl">
        <PageHeader title="Replenishment" description="Kelola rencana pengadaan dan draft purchase order." />
        <div className="mb-5 rounded-lg border border-warning-200 bg-warning-50 p-3 text-sm text-warning-700">Masuk sebagai administrator untuk mengelola replenishment.</div>
        <LoginForm expectedRole="admin" onLogin={(nextToken) => setToken(nextToken)} />
      </div>
    );
  }

  const summary = summaryFor(lastResult, plans);
  const columns = [
    {
      key: 'id',
      header: 'Rencana',
      render: (plan: ReplenishmentPlan) => (
        <span>
          <span className="font-semibold text-gray-900">{plan.supplier?.name ?? 'Supplier belum ditentukan'}</span>
          <span className="mt-1 block text-xs text-gray-500">#{plan.id} · {plan.items.length} item · {plan.window_start ?? '—'} s/d {plan.window_end ?? '—'}</span>
        </span>
      ),
    },
    {
      key: 'status',
      header: 'Status',
      render: (plan: ReplenishmentPlan) => <StatusBadge status={plan.status} />,
    },
    {
      key: 'purchase_order',
      header: 'PO reference',
      render: (plan: ReplenishmentPlan) => {
        const refs = poReferences(plan);
        return refs.length > 0 ? <span className="text-xs font-medium text-gray-700">{refs.join(', ')}</span> : <span className="text-xs text-gray-400">—</span>;
      },
    },
    {
      key: 'created_at',
      header: 'Dibuat',
      render: (plan: ReplenishmentPlan) => <span className="text-xs text-gray-600">{formatDateTime(plan.created_at)}</span>,
    },
    {
      key: 'action',
      header: 'Aksi',
      render: (plan: ReplenishmentPlan) => (
        <span className="flex flex-wrap gap-1.5">
          {plan.status === 'draft' ? <Button size="sm" onClick={() => void runAction(plan.id, 'approve')}>Approve</Button> : null}
          {plan.status === 'approved' ? <Button size="sm" onClick={() => void runAction(plan.id, 'execute')}>Execute</Button> : null}
          {TERMINAL_STATUSES.has(plan.status) ? <span className="text-xs text-gray-500">Tidak ada aksi</span> : null}
        </span>
      ),
    },
  ];

  return (
    <div className="mx-auto max-w-6xl">
      <PageHeader title="Replenishment" description="Kelola rencana pengadaan dan draft purchase order." />
      <div className="mb-5 flex justify-end"><Button onClick={() => void runGenerate()}>Generate rencana</Button></div>
      {error && <p role="alert" className="mb-5 rounded-lg border border-danger-200 bg-danger-50 p-3 text-sm text-danger-700">{error}</p>}
      {success && <p className="mb-3 rounded-lg border border-success-200 bg-success-50 p-3 text-sm text-success-700">{success}</p>}
      <Card className="overflow-hidden">
        <div className="flex flex-wrap items-center justify-between gap-3 border-b border-gray-100 px-5 py-3">
          <TableSummary total={summary.total} noun="rencana" breakdown={summary.breakdown} />
          <TableDensityToggle value={density} onChange={setDensity} />
        </div>
        {!hasLoaded ? (
          <p className="p-8 text-sm text-gray-500">Memuat rencana replenishment...</p>
        ) : (
          <Table
            columns={columns}
            rows={plans}
            rowKey={(plan) => plan.id}
            density={density}
            sortableColumns={['created_at', 'id', 'status']}
            sort={sort}
            onSort={(column) => {
              const next = toggleSort(sortRef.current, column);
              setSort(next);
              void loadPlans(token, { resetCursor: true, sort: next });
            }}
            empty={<EmptyState icon={<span>📦</span>} title="Belum ada rencana replenishment" description="Generate rencana untuk memulai proses pengadaan stok dari supplier." />}
          />
        )}
        <TablePagination
          cursor={cursor}
          limit={PAGE_LIMIT}
          total={total}
          hasMore={hasMore}
          onPageChange={(nextCursor) => {
            setCursor(nextCursor);
            void loadPlans(token, { cursor: nextCursor });
          }}
        />
      </Card>
    </div>
  );
}

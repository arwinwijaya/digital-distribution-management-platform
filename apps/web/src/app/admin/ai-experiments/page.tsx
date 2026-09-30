'use client';

import { useCallback, useEffect, useRef, useState } from 'react';
import LoginForm from '@/components/LoginForm';
import { clearStoredToken, getStoredToken } from '@/lib/api';
import { ApiError } from '@/lib/api-error';
import { useDummyRefresh } from '@/dummy/guards';
import {
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
  fetchExperiments,
  fetchRevenueLift,
  verifyAdminAccess,
  type AbExperiment,
  type ExperimentListData,
  type RevenueLiftSnapshot,
} from '@/lib/experiments-api';

const PAGE_LIMIT = 15;

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
        if (role === 'admin' || role === 'platform_owner') setToken(stored);
        else setAccessError('Akses ditolak');
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

/** Format fixed-point money without converting the integer part through Number. */
export function formatRupiahDecimal(value: string | null | undefined): string {
  if (value === null || value === undefined || value.trim() === '') return '—';
  const raw = value.trim().replace(/^\+/, '');
  const negative = raw.startsWith('-');
  const unsigned = raw.replace(/^-/, '');
  const [wholeRaw, fractionRaw = ''] = unsigned.split('.');
  const whole = wholeRaw.replace(/^0+(?=\d)/, '') || '0';
  const grouped = whole.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
  const fraction = fractionRaw.padEnd(2, '0').slice(0, 2);
  return `${negative ? '-' : ''}Rp ${grouped},${fraction}`;
}

function formatUplift(value: string | null): string {
  if (value === null) return '—';
  const numeric = Number(value);
  if (!Number.isFinite(numeric)) return '—';
  return `${(numeric * 100).toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}%`;
}

function LiftCard({ experiment, lift }: { experiment: AbExperiment; lift: RevenueLiftSnapshot | undefined }) {
  const insufficient = !lift || lift.status === 'insufficient-data' || lift.uplift === null;
  const minimum = lift?.metadata?.minimum_sample_size ?? experiment.minimum_sample_size;
  return (
    <div data-testid={`lift-card-${experiment.id}`}>
    <Card className="p-5">
      <div className="flex items-start justify-between gap-3">
        <div>
          <h2 className="font-semibold text-gray-900">{experiment.name}</h2>
          <p className="mt-1 text-xs text-gray-500">#{experiment.id} · {experiment.experiment_key}</p>
        </div>
        <StatusBadge status={lift?.status ?? 'pending'} />
      </div>
      {insufficient ? (
        <div className="mt-4 rounded-lg border border-warning-200 bg-warning-50 p-3 text-sm text-warning-800">
          <p className="font-semibold">Data belum cukup</p>
          <p className="mt-1">Lift belum dapat dihitung: sampel control/treatment belum mencapai minimum {minimum}. Metode: {lift?.method_version ?? 'lift-mean-v1'}.</p>
        </div>
      ) : (
        <div className="mt-4">
          <p className="text-xs font-medium uppercase tracking-wide text-gray-500">Revenue lift</p>
          <p className="mt-1 text-3xl font-bold text-gray-900">{formatUplift(lift.uplift)}</p>
        </div>
      )}
      <div className="mt-4 grid grid-cols-2 gap-3 text-xs text-gray-600">
        <span>{lift?.control_sample_size ?? 0} control · {lift?.treatment_sample_size ?? 0} treatment</span>
        <span className="text-right">{lift?.method_version ?? 'lift-mean-v1'}</span>
        <span>Control {formatRupiahDecimal(lift?.control_revenue)}</span>
        <span className="text-right">Treatment {formatRupiahDecimal(lift?.treatment_revenue)}</span>
      </div>
    </Card>
    </div>
  );
}

export default function AdminAiExperimentsPage() {
  const { token, ready, accessError, setToken } = useAdminGuard();
  const [experiments, setExperiments] = useState<AbExperiment[]>([]);
  const [lifts, setLifts] = useState<Record<number, RevenueLiftSnapshot>>({});
  const [lastResult, setLastResult] = useState<ExperimentListData | null>(null);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [sort, setSort] = useState<ColumnSort>({ column: 'created_at', order: 'desc' });
  const [cursor, setCursor] = useState(0);
  const [hasMore, setHasMore] = useState(false);
  const [total, setTotal] = useState<number | undefined>(undefined);
  const { density, setDensity } = useTableDensity();
  const sortRef = useRef(sort);
  sortRef.current = sort;
  const cursorRef = useRef(cursor);
  cursorRef.current = cursor;

  const load = useCallback(async (opts?: { resetCursor?: boolean; cursor?: number; sort?: ColumnSort }) => {
    setLoading(true);
    setError(null);
    const nextSort = opts?.sort ?? sortRef.current;
    const nextCursor = opts?.cursor ?? (opts?.resetCursor ? 0 : cursorRef.current);
    try {
      const result = await fetchExperiments(nextCursor, PAGE_LIMIT, nextSort.column, nextSort.order);
      setExperiments(result.experiments);
      setLastResult(result);
      setCursor(result.meta.cursor);
      setHasMore(result.meta.has_more);
      setTotal(result.meta.total);
      const entries = await Promise.all(result.experiments.map(async (experiment) => [experiment.id, await fetchRevenueLift(experiment.id)] as const));
      setLifts(Object.fromEntries(entries));
    } catch (reason) {
      setError(reason instanceof Error ? reason.message : 'Daftar eksperimen tidak dapat dimuat.');
      setExperiments([]);
      setLifts({});
      setLastResult(null);
      setHasMore(false);
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => { if (token) void load({ resetCursor: true }); }, [token, load]);
  useDummyRefresh(() => { if (token) void load({ resetCursor: true }); });

  if (!ready) return <p className="text-sm text-gray-500">Memuat…</p>;
  if (accessError) return <div className="mx-auto max-w-6xl"><PageHeader title="Eksperimen AI" description="Pantau assignment dan revenue lift eksperimen AI." /><div role="alert" className="mb-5 rounded-lg border border-danger-200 bg-danger-50 p-3 text-sm text-danger-700">{accessError}</div><LoginForm expectedRole="admin" onLogin={(nextToken) => setToken(nextToken)} /></div>;
  if (!token) return <div className="mx-auto max-w-6xl"><PageHeader title="Eksperimen AI" description="Pantau assignment dan revenue lift eksperimen AI." /><div className="mb-5 rounded-lg border border-warning-200 bg-warning-50 p-3 text-sm text-warning-700">Masuk sebagai administrator untuk melihat eksperimen AI.</div><LoginForm expectedRole="admin" onLogin={(nextToken) => setToken(nextToken)} /></div>;

  const summaryTotal = total ?? lastResult?.experiments.length ?? experiments.length;
  const columns = [
    { key: 'id', header: 'Eksperimen', render: (experiment: AbExperiment) => <span><span className="font-semibold text-gray-900">{experiment.name}</span><span className="mt-1 block text-xs text-gray-500">#{experiment.id} · {experiment.experiment_key}</span></span> },
    { key: 'status', header: 'Status', render: (experiment: AbExperiment) => <StatusBadge status={experiment.status} /> },
    { key: 'assignment', header: 'Assignment', render: (experiment: AbExperiment) => <span className="text-xs text-gray-600">{experiment.assignment_summary.total} total · {experiment.assignment_summary.control} control · {experiment.assignment_summary.treatment} treatment</span> },
    { key: 'sample', header: 'Minimum sampel', render: (experiment: AbExperiment) => <span className="text-xs text-gray-600">{experiment.minimum_sample_size}</span> },
    { key: 'created_at', header: 'Dibuat', render: (experiment: AbExperiment) => <span className="text-xs text-gray-600">{formatDateTime(experiment.created_at)}</span> },
  ];

  return <div className="mx-auto max-w-6xl">
    <PageHeader title="Eksperimen AI" description="Pantau assignment dan revenue lift eksperimen AI." />
    {error && <p role="alert" className="mb-5 rounded-lg border border-danger-200 bg-danger-50 p-3 text-sm text-danger-700">{error}</p>}
    <section aria-labelledby="lift-summary-heading" className="mb-6"><h2 id="lift-summary-heading" className="mb-3 text-lg font-semibold text-gray-900">Ringkasan revenue lift</h2><div className="grid gap-4 md:grid-cols-2">{experiments.map((experiment) => <LiftCard key={experiment.id} experiment={experiment} lift={lifts[experiment.id]} />)}</div></section>
    <Card className="overflow-hidden">
      <div className="flex flex-wrap items-center justify-between gap-3 border-b border-gray-100 px-5 py-3"><TableSummary total={summaryTotal} noun="eksperimen" breakdown={[{ label: 'running', value: experiments.filter((experiment) => experiment.status === 'running').length }, { label: 'data cukup', value: experiments.filter((experiment) => lifts[experiment.id]?.status === 'computed').length }]} /><TableDensityToggle value={density} onChange={setDensity} /></div>
      {loading && experiments.length === 0 ? <p className="p-8 text-sm text-gray-500">Memuat eksperimen AI...</p> : <Table columns={columns} rows={experiments} rowKey={(experiment) => experiment.id} density={density} sortableColumns={['created_at', 'id', 'status']} sort={sort} onSort={(column) => { const next = toggleSort(sortRef.current, column); setSort(next); void load({ resetCursor: true, sort: next }); }} empty={<EmptyState icon={<span>🧪</span>} title="Belum ada eksperimen AI" description="Eksperimen yang telah dibuat akan muncul di sini." />} />}
      <TablePagination cursor={cursor} limit={PAGE_LIMIT} total={total} hasMore={hasMore} onPageChange={(nextCursor) => { setCursor(nextCursor); void load({ cursor: nextCursor }); }} />
    </Card>
  </div>;
}

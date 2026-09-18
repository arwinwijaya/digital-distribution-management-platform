'use client';

import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import LoginForm from '@/components/LoginForm';
import { getStoredToken } from '@/lib/api';
import { fetchAdminSalesPerformance, formatPercentage, formatRupiah, parseMoney, type SalesPerformanceRow } from './api';
import { useDummyRefresh } from '@/dummy/guards';
import { Button, Card, EmptyState, Input, PageHeader, Table, TablePagination, TableSummary } from '@/components/ui';
import { toggleSort, type ColumnSort } from '@/lib/admin-table';

/** Rows fetched per page (offset pagination). */
const PAGE_LIMIT = 15;

/** Current YYYY-MM in Asia/Jakarta timezone (indonesia). */
function currentPeriod(): string {
  const now = new Date();
  const yyyy = now.getFullYear();
  const mm = String(now.getMonth() + 1).padStart(2, '0');
  return `${yyyy}-${mm}`;
}

export default function AdminSalesPerformancePage() {
  const [token, setToken] = useState<string | null>(null);
  const [ready, setReady] = useState(false);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [period, setPeriod] = useState('');
  const [rows, setRows] = useState<SalesPerformanceRow[]>([]);
  const [hasMore, setHasMore] = useState(false);
  const [total, setTotal] = useState<number>();
  // Table state — `achievement` is the CLIENT-SIDE default display order; it is
  // never a server-sortable column (server allowlist is only `['name','id']`).
  const [sort, setSort] = useState<ColumnSort>({ column: 'achievement', order: 'desc' });
  const [cursor, setCursor] = useState(0);

  // Latest sort/cursor readable inside `load` WITHOUT adding them to its
  // dependency list (which would otherwise re-run the mount effect and reset the
  // page on every sort/page change).
  const sortRef = useRef(sort);
  sortRef.current = sort;
  const cursorRef = useRef(cursor);
  cursorRef.current = cursor;

  const load = useCallback(
    async (
      authToken: string,
      targetPeriod: string,
      opts?: { resetCursor?: boolean; cursor?: number; sort?: ColumnSort },
    ) => {
      setLoading(true);
      setError(null);
      try {
        const nextSort = opts?.sort ?? sortRef.current;
        const nextCursor = opts?.cursor ?? (opts?.resetCursor ? 0 : cursorRef.current);
        // `achievement` is CLIENT-SIDE only — never send it to the server (the
        // allowlist is just `name`/`id`). Omit sort/order entirely so the server
        // keeps its own default order.
        const serverSort = nextSort.column === 'achievement' ? undefined : nextSort;
        const result = await fetchAdminSalesPerformance(authToken, {
          period: targetPeriod,
          limit: PAGE_LIMIT,
          cursor: nextCursor,
          sort: serverSort?.column,
          order: serverSort?.order,
        });
        // Offset paging REPLACES the page (never appends).
        setRows(result.rows);
        setHasMore(result.hasMore);
        setCursor(nextCursor);
        if (result.total !== undefined) setTotal(result.total);
      } catch (reason) {
        setError(reason instanceof Error ? reason.message : 'Kinerja sales tidak dapat dimuat.');
      } finally {
        setLoading(false);
      }
    },
    [],
  );

  useEffect(() => {
    const stored = getStoredToken();
    setToken(stored);
    setReady(true);
    if (stored) {
      const cp = currentPeriod();
      setPeriod(cp);
      load(stored, cp, { resetCursor: true });
    }
  }, [load]);

  useDummyRefresh(() => { if (token && period) void load(token, period, { resetCursor: true }); });

  const sortedMonths = useMemo(() => {
    const months: string[] = [];
    const now = new Date();
    for (let i = 0; i < 6; i++) {
      const d = new Date(now.getFullYear(), now.getMonth() - i, 1);
      months.push(`${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`);
    }
    return months;
  }, []);

  function handlePeriodChange(value: string) {
    setPeriod(value);
    // Period change resets the offset cursor but PRESERVES the active sort.
    if (token) load(token, value, { resetCursor: true, sort: sortRef.current });
  }

  // Default DISPLAY order is `achievement DESC` — a DERIVED value compared
  // NUMERICALLY via `parseMoney` (never string compare, never sent to server).
  // Applied ONLY while `achievement` is the active column so a server sort by
  // `name` is never overridden by this client-side order.
  const displayRows = useMemo(
    () =>
      sort.column === 'achievement'
        ? [...rows].sort((a, b) => parseMoney(b.achievement) - parseMoney(a.achievement))
        : rows,
    [rows, sort.column],
  );

  if (!ready) return <p className="text-sm text-gray-500">Memuat...</p>;
  if (!token)
    return (
      <div className="mx-auto max-w-6xl">
        <PageHeader title="Kinerja sales" description="Lihat kinerja seluruh sales berdasarkan periode." />
        <div className="mb-5 rounded-lg border border-warning-200 bg-warning-50 p-3 text-sm text-warning-700">
          Masuk sebagai administrator untuk melihat kinerja sales.
        </div>
        <LoginForm
          expectedRole="admin"
          onLogin={(nextToken) => {
            setToken(nextToken);
            const cp = currentPeriod();
            setPeriod(cp);
            load(nextToken, cp, { resetCursor: true });
          }}
        />
      </div>
    );

  const columns = [
    { key: 'name', header: 'Sales', render: (r: SalesPerformanceRow) => <span className="font-medium text-gray-900">{r.name}</span> },
    { key: 'email', header: 'Email', render: (r: SalesPerformanceRow) => <span className="text-gray-500">{r.email ?? '—'}</span> },
    { key: 'target', header: 'Target', render: (r: SalesPerformanceRow) => <span className="text-gray-700">{formatRupiah(r.target)}</span> },
    { key: 'achievement', header: 'Pencapaian', render: (r: SalesPerformanceRow) => <span className="text-gray-700">{formatRupiah(r.achievement)}</span> },
    {
      key: 'percentage',
      header: 'Persentase',
      render: (r: SalesPerformanceRow) => {
        const value = Number(parseMoney(r.percentage));
        return (
          <span className={`inline-flex items-center rounded-full border px-2.5 py-0.5 text-xs font-medium ${value >= 100 ? 'bg-success-50 text-success-700 border-success-200' : value >= 50 ? 'bg-warning-50 text-warning-700 border-warning-200' : 'bg-gray-50 text-gray-600 border-gray-200'}`}>
            {formatPercentage(r.percentage)}
          </span>
        );
      },
    },
    { key: 'orders', header: 'Pesanan', render: (r: SalesPerformanceRow) => <span className="text-gray-700">{r.order_count}</span> },
  ];

  return (
    <div className="mx-auto max-w-6xl">
      <PageHeader title="Kinerja sales" description="Lihat kinerja seluruh sales berdasarkan periode." />

      {error && (
        <p role="alert" className="mb-5 rounded-lg border border-danger-200 bg-danger-50 p-3 text-sm text-danger-700">{error}</p>
      )}

      <Card className="mb-6 p-5">
        <div className="grid gap-3 sm:grid-cols-[200px_auto] sm:items-end">
          <div>
            <label htmlFor="admin-perf-period" className="block text-sm font-medium text-gray-700 mb-1">Periode (YYYY-MM)</label>
            <Input id="admin-perf-period" value={period} onChange={(event) => handlePeriodChange(event.target.value)} placeholder="YYYY-MM" />
          </div>
          <div className="flex gap-3">
            <select
              aria-label="Pilih periode"
              className="rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm shadow-sm focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 focus:outline-none"
              value={period}
              onChange={(event) => handlePeriodChange(event.target.value)}
            >
              {sortedMonths.map((m) => (
                <option key={m} value={m}>{m}</option>
              ))}
            </select>
            <Button variant="secondary" onClick={() => token && load(token, period, { resetCursor: true, sort: sortRef.current })} disabled={loading}>
              Terapkan
            </Button>
          </div>
        </div>
      </Card>

      <Card className="overflow-hidden">
        <div className="flex flex-wrap items-center justify-between gap-3 border-b border-gray-100 px-5 py-3">
          <TableSummary total={total ?? rows.length} noun="sales" />
        </div>
        {loading && rows.length === 0 ? (
          <p className="p-8 text-sm text-gray-500">Memuat kinerja...</p>
        ) : (
          <Table
            columns={columns}
            rows={displayRows}
            rowKey={(r) => r.user_id}
            sortableColumns={['name']}
            sort={sort}
            onSort={(column) => {
              const next = toggleSort(sortRef.current, column);
              setSort(next);
              if (token) void load(token, period, { resetCursor: true, sort: next });
            }}
            empty={<EmptyState icon={<span>📊</span>} title="Belum ada data kinerja" description="Kinerja sales akan tampil di sini." />}
          />
        )}
        <TablePagination
          cursor={cursor}
          limit={PAGE_LIMIT}
          total={total}
          hasMore={hasMore}
          onPageChange={(nextCursor) => {
            setCursor(nextCursor);
            if (token) void load(token, period, { cursor: nextCursor });
          }}
        />
      </Card>
    </div>
  );
}

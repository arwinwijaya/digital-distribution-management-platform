'use client';

import { useCallback, useEffect, useMemo, useState } from 'react';
import LoginForm from '@/components/LoginForm';
import { getStoredToken } from '@/lib/api';
import { fetchAdminSalesPerformance, formatPercentage, formatRupiah, parseMoney, type SalesPerformanceRow } from './api';
import { useDummyRefresh } from '@/dummy/guards';
import { Button, Card, EmptyState, Input, PageHeader, Table } from '@/components/ui';

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
  const [nextCursor, setNextCursor] = useState<number | null>(null);

  const load = useCallback(async (authToken: string, targetPeriod: string, cursor?: number) => {
    setLoading(true);
    setError(null);
    try {
      const result = await fetchAdminSalesPerformance(authToken, {
        period: targetPeriod,
        limit: 100,
        cursor,
      });
      setRows((prev) => (cursor ? [...prev, ...result.rows] : result.rows));
      setHasMore(result.hasMore);
      setNextCursor(result.nextCursor);
    } catch (reason) {
      setError(reason instanceof Error ? reason.message : 'Kinerja sales tidak dapat dimuat.');
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    const stored = getStoredToken();
    setToken(stored);
    setReady(true);
    if (stored) {
      const cp = currentPeriod();
      setPeriod(cp);
      load(stored, cp);
    }
  }, [load]);

  useDummyRefresh(() => { if (token && period) void load(token, period); });

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
    if (token) load(token, value);
  }

  function handleLoadMore() {
    if (token && nextCursor) load(token, period, nextCursor);
  }

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
            load(nextToken, cp);
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
            <Button variant="secondary" onClick={() => token && load(token, period)} disabled={loading}>
              Terapkan
            </Button>
          </div>
        </div>
      </Card>

      <Card className="overflow-hidden">
        {loading && rows.length === 0 ? (
          <p className="p-8 text-sm text-gray-500">Memuat kinerja...</p>
        ) : (
          <Table
            columns={columns}
            rows={rows}
            rowKey={(r) => r.user_id}
            empty={<EmptyState icon={<span>📊</span>} title="Belum ada data kinerja" description="Kinerja sales akan tampil di sini." />}
          />
        )}
        {hasMore && (
          <div className="border-t border-gray-100 px-5 py-3">
            <Button variant="secondary" size="sm" onClick={handleLoadMore} disabled={loading}>
              Muat lebih banyak
            </Button>
          </div>
        )}
      </Card>
    </div>
  );
}

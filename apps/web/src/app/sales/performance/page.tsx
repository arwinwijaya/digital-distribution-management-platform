'use client';

import { useEffect, useState } from 'react';
import Link from 'next/link';
import LoginForm from '@/components/LoginForm';
import { getStoredToken } from '@/lib/api';
import { fetchMyPerformance, formatPercentage, formatRupiah, parseMoney, type MyPerformance } from './api';
import { Card, EmptyState, PageHeader, StatCard } from '@/components/ui';

function currentPeriod(): string {
  const now = new Date();
  return `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}`;
}

const sortedMonths = (() => {
  const months: string[] = [];
  const now = new Date();
  for (let i = 0; i < 6; i++) {
    const d = new Date(now.getFullYear(), now.getMonth() - i, 1);
    months.push(`${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`);
  }
  return months;
})();

export default function SalesPerformancePage() {
  const [token, setToken] = useState<string | null>(null);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [period, setPeriod] = useState('');
  const [perf, setPerf] = useState<MyPerformance | null>(null);

  useEffect(() => {
    const stored = getStoredToken();
    setToken(stored);
    if (!stored) return;
    const cp = currentPeriod();
    setPeriod(cp);
    load(stored, cp);
  }, []); // eslint-disable-line react-hooks/exhaustive-deps

  async function load(authToken: string, targetPeriod: string) {
    setLoading(true);
    setError(null);
    try {
      const data = await fetchMyPerformance(authToken, targetPeriod);
      setPerf(data);
    } catch (reason) {
      setError(reason instanceof Error ? reason.message : 'Kinerja tidak dapat dimuat.');
    } finally {
      setLoading(false);
    }
  }

  function handlePeriodChange(value: string) {
    setPeriod(value);
    if (token) load(token, value);
  }

  if (!token)
    return (
      <div className="mx-auto max-w-6xl">
        <PageHeader title="Kinerja saya" description="Pantau pencapaian target penjualan bulan ini." />
        <LoginForm
          expectedRole="sales"
          onLogin={(nextToken) => {
            setToken(nextToken);
            const cp = currentPeriod();
            setPeriod(cp);
            load(nextToken, cp);
          }}
        />
      </div>
    );

  return (
    <div className="mx-auto max-w-6xl">
      <PageHeader title="Kinerja saya" description="Pantau pencapaian target penjualan bulan ini." />

      <Card className="mb-6 p-5">
        <div className="flex items-end gap-3">
          <div>
            <label htmlFor="perf-period" className="block text-sm font-medium text-gray-700 mb-1">Periode</label>
            <select
              id="perf-period"
              className="block w-full rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm text-gray-900 shadow-sm focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 focus:outline-none disabled:bg-gray-100 disabled:cursor-not-allowed"
              value={period}
              onChange={(event) => handlePeriodChange(event.target.value)}
            >
              {sortedMonths.map((m) => (
                <option key={m} value={m}>{m}</option>
              ))}
            </select>
          </div>
        </div>
      </Card>

      {error && (
        <p role="alert" className="mb-5 rounded-lg border border-danger-200 bg-danger-50 p-3 text-sm text-danger-700">{error}</p>
      )}

      {loading && !perf && <p className="text-sm text-gray-500">Memuat kinerja...</p>}

      {!loading && !perf && !error && (
        <EmptyState icon={<span>📈</span>} title="Belum ada data kinerja" description="Kinerja Anda akan tampil di sini setelah ada target penugasan." />
      )}

      {perf && (
        <>
          <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <StatCard label="Target bulan ini" value={formatRupiah(perf.target)} icon={<span>🎯</span>} />
            <StatCard
              label="Pencapaian (revenue)"
              value={formatRupiah(perf.achievement)}
              change={`${formatPercentage(perf.percentage)} dari target`}
              changeType={Number(parseMoney(perf.percentage)) >= 100 ? 'up' : Number(parseMoney(perf.percentage)) >= 50 ? 'neutral' : 'down'}
              icon={<span>📈</span>}
            />
            <StatCard label="Persentase pencapaian" value={formatPercentage(perf.percentage)} icon={<span>📊</span>} />
            <StatCard label="Total pesanan" value={String(perf.order_count)} icon={<span>📋</span>} />
          </div>

          <Card className="mt-6 p-5">
            <h2 className="mb-3 text-base font-semibold text-gray-900">Detail kinerja</h2>
            <dl className="grid gap-4 sm:grid-cols-2 text-sm">
              <div>
                <dt className="text-xs text-gray-500">Periode</dt>
                <dd className="font-medium text-gray-900">{perf.period}</dd>
              </div>
              <div>
                <dt className="text-xs text-gray-500">Target</dt>
                <dd className="font-medium text-gray-900">{formatRupiah(perf.target)}</dd>
              </div>
              <div>
                <dt className="text-xs text-gray-500">Pencapaian</dt>
                <dd className="font-medium text-gray-900">{formatRupiah(perf.achievement)}</dd>
              </div>
              <div>
                <dt className="text-xs text-gray-500">Persentase</dt>
                <dd className="font-medium text-gray-900">{formatPercentage(perf.percentage)}</dd>
              </div>
              <div>
                <dt className="text-xs text-gray-500">Jumlah pesanan</dt>
                <dd className="font-medium text-gray-900">{perf.order_count}</dd>
              </div>
            </dl>
          </Card>
        </>
      )}
    </div>
  );
}

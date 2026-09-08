'use client';

import { useCallback, useEffect, useMemo, useState } from 'react';
import LoginForm from '@/components/LoginForm';
import { OutletPerformanceChart, SalesTrendChart, OutletPoint, TrendPoint } from '@/components/Charts';
import { apiUrl, authHeaders, getStoredToken } from '@/lib/api';

type Group = 'daily' | 'weekly' | 'monthly';

type Metrics = {
  orders_total: number;
  sales_total: string;
  outlets_total: number;
  products_total: number;
  payments_total: string;
  outstanding_total: string;
};

type DashboardData = {
  metrics: Metrics;
  sales_trends: TrendPoint[];
  outlet_performance: OutletPoint[];
};

function defaultStartDate(): string {
  const date = new Date();
  date.setDate(date.getDate() - 29);
  return date.toISOString().slice(0, 10);
}

function today(): string {
  return new Date().toISOString().slice(0, 10);
}

export default function DashboardPage() {
  const [token, setToken] = useState<string | null>(null);
  const [ready, setReady] = useState(false);
  const [startDate, setStartDate] = useState(defaultStartDate);
  const [endDate, setEndDate] = useState(today);
  const [group, setGroup] = useState<Group>('daily');
  const [dashboard, setDashboard] = useState<DashboardData | null>(null);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const loadDashboard = useCallback(async (authToken: string) => {
    setLoading(true);
    setError(null);
    try {
      const query = new URLSearchParams({ start_date: startDate, end_date: endDate, group });
      const response = await fetch(`${apiUrl('/analytics/dashboard')}?${query}`, { headers: authHeaders(authToken) });
      const body = await response.json();
      if (!response.ok) throw new Error(body.message || 'Unable to load dashboard.');
      setDashboard(body.data);
    } catch (loadError) {
      setDashboard(null);
      setError(loadError instanceof Error ? loadError.message : 'Unable to load dashboard.');
    } finally {
      setLoading(false);
    }
  }, [endDate, group, startDate]);

  useEffect(() => {
    const storedToken = getStoredToken();
    setToken(storedToken);
    setReady(true);
  }, []);

  useEffect(() => {
    if (token) loadDashboard(token);
  }, [loadDashboard, token]);

  const hasData = useMemo(() => {
    if (!dashboard) return false;
    return dashboard.metrics.orders_total > 0 || dashboard.metrics.sales_total !== '0.00' || dashboard.metrics.payments_total !== '0.00';
  }, [dashboard]);

  if (!ready) return <main className="mx-auto max-w-6xl p-8"><p>Loading dashboard...</p></main>;
  if (!token) return <main className="mx-auto max-w-6xl space-y-4 p-8"><h1 className="text-3xl font-bold">Executive dashboard</h1><p className="text-gray-600">Administrator sign-in is required to view business analytics.</p><LoginForm expectedRole="admin" onLogin={(nextToken) => setToken(nextToken)} /></main>;

  return (
    <main className="mx-auto max-w-6xl space-y-6 p-8">
      <header>
        <h1 className="text-3xl font-bold">Executive dashboard</h1>
        <p className="text-gray-600">Sales, payment, product, and outlet performance overview.</p>
      </header>

      <form className="flex flex-wrap items-end gap-3 rounded border bg-white p-4" onSubmit={(event) => { event.preventDefault(); if (token) loadDashboard(token); }}>
        <label className="flex flex-col gap-1 text-sm font-medium">From<input type="date" value={startDate} onChange={(event) => setStartDate(event.target.value)} className="rounded border px-2 py-1" /></label>
        <label className="flex flex-col gap-1 text-sm font-medium">To<input type="date" value={endDate} onChange={(event) => setEndDate(event.target.value)} className="rounded border px-2 py-1" /></label>
        <label className="flex flex-col gap-1 text-sm font-medium">Trend grouping<select value={group} onChange={(event) => setGroup(event.target.value as Group)} className="rounded border px-2 py-1"><option value="daily">Daily</option><option value="weekly">Weekly</option><option value="monthly">Monthly</option></select></label>
        <button type="submit" disabled={loading} className="rounded bg-blue-600 px-4 py-2 text-white disabled:bg-gray-400">{loading ? 'Loading...' : 'Apply'}</button>
      </form>

      {error && <p role="alert" className="rounded border border-red-200 bg-red-50 p-3 text-red-700">{error}</p>}
      {loading && !dashboard && <p aria-live="polite">Loading analytics...</p>}
      {!loading && dashboard && !hasData && <p className="rounded border bg-white p-6 text-gray-600">No data is available for this date range.</p>}

      {dashboard && (
        <>
          <section aria-label="Key metrics" className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            {[
              ['Sales', `Rp ${Number(dashboard.metrics.sales_total).toLocaleString('id-ID')}`],
              ['Orders', dashboard.metrics.orders_total.toLocaleString('id-ID')],
              ['Active outlets', dashboard.metrics.outlets_total.toLocaleString('id-ID')],
              ['Active products', dashboard.metrics.products_total.toLocaleString('id-ID')],
              ['Completed payments', `Rp ${Number(dashboard.metrics.payments_total).toLocaleString('id-ID')}`],
              ['Outstanding', `Rp ${Number(dashboard.metrics.outstanding_total).toLocaleString('id-ID')}`],
            ].map(([label, value]) => <article key={label} className="rounded border bg-white p-4"><p className="text-sm text-gray-500">{label}</p><p className="mt-1 text-2xl font-semibold">{value}</p></article>)}
          </section>
          <section className="grid gap-6 lg:grid-cols-2">
            <article className="rounded border bg-white p-5"><h2 className="mb-4 text-xl font-semibold">Sales trend</h2><SalesTrendChart points={dashboard.sales_trends} /></article>
            <article className="rounded border bg-white p-5"><h2 className="mb-4 text-xl font-semibold">Outlet performance</h2><OutletPerformanceChart outlets={dashboard.outlet_performance} /></article>
          </section>
        </>
      )}
    </main>
  );
}

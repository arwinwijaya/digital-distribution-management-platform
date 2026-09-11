'use client';

import { useCallback, useEffect, useMemo, useState } from 'react';
import LoginForm from '@/components/LoginForm';
import { OutletPerformanceChart, SalesTrendChart, OutletPoint, TrendPoint } from '@/components/Charts';
import { apiUrl, authHeaders, getStoredToken } from '@/lib/api';
import { Button, Card, Input, PageHeader, Select, StatCard } from '@/components/ui';

type Group = 'daily' | 'weekly' | 'monthly';
type Metrics = { orders_total: number; sales_total: string; outlets_total: number; products_total: number; payments_total: string; outstanding_total: string };
type DashboardData = { metrics: Metrics; sales_trends: TrendPoint[]; outlet_performance: OutletPoint[] };
type FinanceMetrics = {
  issued_invoices: { count: number };
  outstanding_balance: { amount: string | number };
  overdue_rate: { rate: number; overdue_count: number; active_count: number };
  collection_time: { average_days: number; fully_collected_count: number };
  payment_status_breakdown: Record<string, number>;
  reminders: { success: number; failure: number; sent: number; failed: number };
};

function defaultStartDate(): string { const date = new Date(); date.setDate(date.getDate() - 29); return date.toISOString().slice(0, 10); }
function today(): string { return new Date().toISOString().slice(0, 10); }
function safeNumber(value: unknown): number { const parsed = Number(value); return Number.isFinite(parsed) ? parsed : 0; }
function rupiah(value: unknown): string { return `Rp ${safeNumber(value).toLocaleString('id-ID')}`; }
function integer(value: unknown): string { return safeNumber(value).toLocaleString('id-ID'); }

export default function DashboardPage() {
  const [token, setToken] = useState<string | null>(null);
  const [role, setRole] = useState<string | null>(null);
  const [ready, setReady] = useState(false);
  const [startDate, setStartDate] = useState(defaultStartDate);
  const [endDate, setEndDate] = useState(today);
  const [group, setGroup] = useState<Group>('daily');
  const [dashboard, setDashboard] = useState<DashboardData | null>(null);
  const [financeMetrics, setFinanceMetrics] = useState<FinanceMetrics | null>(null);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const loadForRole = useCallback(async (authToken: string, currentRole: string) => {
    setLoading(true);
    setError(null);
    try {
      if (currentRole === 'finance') {
        const query = new URLSearchParams({ start_date: startDate, end_date: endDate });
        const response = await fetch(`${apiUrl('/finance/metrics')}?${query}`, { headers: authHeaders(authToken) });
        const body = await response.json();
        if (!response.ok) throw new Error(body.message || 'Metrik keuangan tidak dapat dimuat.');
        setFinanceMetrics(body.data);
        setDashboard(null);
      } else {
        const query = new URLSearchParams({ start_date: startDate, end_date: endDate, group });
        const response = await fetch(`${apiUrl('/analytics/dashboard')}?${query}`, { headers: authHeaders(authToken) });
        const body = await response.json();
        if (!response.ok) throw new Error(body.message || 'Data dasbor tidak dapat dimuat.');
        setDashboard(body.data);
        setFinanceMetrics(null);
      }
    } catch (reason) {
      setDashboard(null);
      setFinanceMetrics(null);
      setError(reason instanceof Error ? reason.message : 'Data dasbor tidak dapat dimuat.');
    } finally {
      setLoading(false);
    }
  }, [endDate, group, startDate]);

  useEffect(() => {
    const storedToken = getStoredToken();
    if (!storedToken) {
      setReady(true);
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
        setToken(storedToken);
        setRole(currentRole);
        setReady(true);
        void loadForRole(storedToken, currentRole);
      })
      .catch((reason) => {
        if (!active) return;
        setError(reason instanceof Error ? reason.message : 'Sesi tidak dapat diverifikasi.');
        setReady(true);
      });

    return () => { active = false; };
  }, [loadForRole]);

  const hasData = useMemo(() => dashboard && (
    safeNumber(dashboard.metrics.orders_total) > 0
    || safeNumber(dashboard.metrics.sales_total) > 0
    || safeNumber(dashboard.metrics.payments_total) > 0
  ), [dashboard]);

  const statusTotal = financeMetrics
    ? Object.values(financeMetrics.payment_status_breakdown ?? {}).reduce((sum, count) => sum + safeNumber(count), 0)
    : 0;
  const reminderSuccess = safeNumber(financeMetrics?.reminders?.success ?? financeMetrics?.reminders?.sent);
  const reminderFailure = safeNumber(financeMetrics?.reminders?.failure ?? financeMetrics?.reminders?.failed);

  if (!ready) return <p className="text-sm text-gray-500">Memuat...</p>;
  if (!token) return <div className="mx-auto max-w-6xl"><PageHeader title="Dasbor eksekutif" description="Ringkasan performa bisnis dan keuangan." /><LoginForm expectedRole={['admin', 'finance']} onLogin={(nextToken, nextRole) => { setToken(nextToken); setRole(nextRole); setReady(true); void loadForRole(nextToken, nextRole); }} /></div>;

  return <div className="mx-auto max-w-6xl">
    <PageHeader title={role === 'finance' ? 'Dasbor keuangan' : 'Dasbor eksekutif'} description={role === 'finance' ? 'Pantau invoice, piutang, pembayaran, dan pengingat.' : 'Ringkasan performa penjualan, pembayaran, produk, dan outlet.'} />
    <Card className="mb-6 p-4">
      <form className="grid items-end gap-3 sm:grid-cols-2 lg:grid-cols-4" onSubmit={(event) => { event.preventDefault(); if (token && role) void loadForRole(token, role); }}>
        <Input label="Dari tanggal" type="date" value={startDate} onChange={(event) => setStartDate(event.target.value)} />
        <Input label="Sampai tanggal" type="date" value={endDate} onChange={(event) => setEndDate(event.target.value)} />
        {role !== 'finance' && <Select label="Kelompok tren" value={group} onChange={(event) => setGroup(event.target.value as Group)}><option value="daily">Harian</option><option value="weekly">Mingguan</option><option value="monthly">Bulanan</option></Select>}
        <Button type="submit" disabled={loading}>{loading ? 'Memuat...' : 'Terapkan filter'}</Button>
      </form>
    </Card>
    {error && <p role="alert" className="mb-6 rounded-lg border border-danger-200 bg-danger-50 p-3 text-sm text-danger-700">{error}</p>}
    {role === 'finance' && financeMetrics && <section aria-label="Metrik keuangan" className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
      <StatCard label="Invoice diterbitkan" value={integer(financeMetrics.issued_invoices?.count)} icon="🧾" />
      <StatCard label="Saldo piutang" value={rupiah(financeMetrics.outstanding_balance?.amount)} icon="💰" />
      <StatCard label="Tingkat jatuh tempo" value={`${safeNumber(financeMetrics.overdue_rate?.rate)}%`} icon="⏱️" />
      <StatCard label="Waktu penagihan rata-rata" value={`${safeNumber(financeMetrics.collection_time?.average_days)} hari`} icon="📅" />
      <StatCard label="Status pembayaran" value={integer(statusTotal)} icon="✅" />
      <StatCard label="Pengingat invoice" value={`${integer(reminderSuccess)} berhasil`} change={`${integer(reminderFailure)} gagal`} changeType={reminderFailure > 0 ? 'down' : 'neutral'} icon="🔔" />
    </section>}
    {role === 'finance' && loading && !financeMetrics && <p className="text-sm text-gray-500">Memuat metrik keuangan...</p>}
    {role !== 'finance' && loading && !dashboard && <p className="text-sm text-gray-500">Memuat data analitik...</p>}
    {role !== 'finance' && !loading && dashboard && !hasData && <Card className="mb-6 p-6 text-sm text-gray-500">Belum ada data pada rentang tanggal ini.</Card>}
    {role !== 'finance' && dashboard && <><section aria-label="Metrik utama" className="mb-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-3"><StatCard label="Total penjualan" value={rupiah(dashboard.metrics.sales_total)} icon="💰" /><StatCard label="Total pesanan" value={integer(dashboard.metrics.orders_total)} icon="🛒" /><StatCard label="Outlet aktif" value={integer(dashboard.metrics.outlets_total)} icon="🏪" /><StatCard label="Produk aktif" value={integer(dashboard.metrics.products_total)} icon="📦" /><StatCard label="Pembayaran selesai" value={rupiah(dashboard.metrics.payments_total)} icon="💳" /><StatCard label="Piutang" value={rupiah(dashboard.metrics.outstanding_total)} icon="⏱️" changeType="down" /></section><section className="grid gap-5 lg:grid-cols-2"><Card className="p-5"><h2 className="mb-4 text-base font-semibold text-gray-900">Tren penjualan</h2><SalesTrendChart points={dashboard.sales_trends} /></Card><Card className="p-5"><h2 className="mb-4 text-base font-semibold text-gray-900">Performa outlet</h2><OutletPerformanceChart outlets={dashboard.outlet_performance} /></Card></section></>}
  </div>;
}

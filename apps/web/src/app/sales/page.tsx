'use client';

import { FormEvent, useEffect, useState } from 'react';
import Link from 'next/link';
import LoginForm from '@/components/LoginForm';
import { apiUrl, authHeaders, getStoredToken } from '@/lib/api';
import { loadSalesList, type Visit, type SalesPageMeta } from '@/app/sales/api';
import VisitCheckin from '@/app/sales/VisitCheckin';
import { useDummyRefresh } from '@/dummy/guards';
import { useDummyStore } from '@/dummy/store';
import { createDummyVisit } from '@/dummy/mutations';
import { Button, Card, EmptyState, Input, PageHeader, StatusBadge, Table, TablePagination } from '@/components/ui';

const PAGE_SIZE = 10;

export async function scheduleVisit(token: string, payload: { target: string; visit_date: string }): Promise<Visit> {
  if (useDummyStore.getState().isDummy) return createDummyVisit(payload);
  const response = await fetch(apiUrl('/sales/visits'), { method: 'POST', headers: authHeaders(token), body: JSON.stringify(payload) });
  const body = await response.json();
  if (!response.ok) throw new Error(body.message || 'Kunjungan tidak dapat dijadwalkan.');
  return (body.data ?? body) as Visit;
}

const SALES_NAV = [
  { href: '/sales/orders', label: 'Buat pesanan', description: 'Buat pesanan untuk outlet di wilayah Anda.' },
  { href: '/sales/performance', label: 'Kinerja saya', description: 'Pantau pencapaian target penjualan Anda.' },
];

export default function SalesPage() {
  const [token, setToken] = useState<string | null>(null);
  const [visits, setVisits] = useState<Visit[]>([]);
  const [meta, setMeta] = useState<SalesPageMeta>({ page: 1, limit: PAGE_SIZE, total: 0, has_more: false });
  const [target, setTarget] = useState('');
  const [visitDate, setVisitDate] = useState('');
  const [error, setError] = useState('');
  const [message, setMessage] = useState('');
  const [loading, setLoading] = useState(false);

  const load = async (nextToken: string, page = 1) => {
    setLoading(true);
    try {
      const result = await loadSalesList(nextToken, page);
      setVisits(result.visits);
      setMeta(result.meta);
      setError('');
    } catch (reason) {
      setError(reason instanceof Error ? reason.message : 'Jadwal kunjungan tidak dapat dimuat.');
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    const stored = getStoredToken();
    setToken(stored);
    if (stored) load(stored);
  }, []);

  useDummyRefresh(() => { if (token) void load(token); });

  const submit = async (event: FormEvent) => {
    event.preventDefault();
    if (!token) return;
    setError('');
    setMessage('');
    try {
      await scheduleVisit(token, { target, visit_date: visitDate });
      setMessage('Kunjungan berhasil dijadwalkan.');
      setTarget('');
      setVisitDate('');
      await load(token, 1);
    } catch (reason) {
      setError(reason instanceof Error ? reason.message : 'Kunjungan tidak dapat dijadwalkan.');
    }
  };

  if (!token)
    return (
      <div className="mx-auto max-w-6xl">
        <PageHeader title="Kunjungan sales" description="Rencanakan dan pantau kunjungan ke outlet." />
        <LoginForm expectedRole="sales" onLogin={(nextToken) => { setToken(nextToken); load(nextToken); }} />
      </div>
    );

  const columns = [
    { key: 'target', header: 'Target outlet', render: (visit: Visit) => <span className="font-medium">{visit.target || 'Target outlet'}</span> },
    { key: 'date', header: 'Tanggal', render: (visit: Visit) => new Date(visit.visit_date).toLocaleDateString('id-ID') },
    { key: 'status', header: 'Status', render: (visit: Visit) => <StatusBadge status={visit.status} /> },
    { key: 'notes', header: 'Catatan', render: (visit: Visit) => <span className="text-gray-500">{visit.notes || '—'}</span> },
    {
      key: 'checkin',
      header: 'Kehadiran',
      render: (visit: Visit) => (
        <VisitCheckin
          visit={visit}
          onUpdated={(updated) => setVisits((current) => current.map((item) => item.id === updated.id ? updated : item))}
        />
      ),
    },
  ];

  return (
    <div className="mx-auto max-w-6xl">
      <PageHeader title="Kunjungan sales" description="Rencanakan dan pantau kunjungan ke outlet yang ditugaskan." />

      <div className="mb-6 grid gap-4 sm:grid-cols-2">
        {SALES_NAV.map((item) => (
          <Link key={item.href} href={item.href}>
            <Card className="p-5 transition-colors hover:border-primary-300">
              <h2 className="font-semibold text-gray-900">{item.label}</h2>
              <p className="mt-1 text-sm text-gray-500">{item.description}</p>
            </Card>
          </Link>
        ))}
      </div>

      {message && <p className="mb-5 rounded-lg border border-success-200 bg-success-50 p-3 text-sm text-success-700">{message}</p>}
      {error && <p className="mb-5 rounded-lg border border-danger-200 bg-danger-50 p-3 text-sm text-danger-700">{error}</p>}

      <Card className="mb-6 p-5">
        <h2 className="mb-4 text-base font-semibold text-gray-900">Jadwalkan kunjungan</h2>
        <form onSubmit={submit} className="grid gap-4 sm:grid-cols-[1fr_1fr_auto] sm:items-end">
          <Input required label="Target outlet" value={target} onChange={(event) => setTarget(event.target.value)} placeholder="Nama outlet / kode outlet" />
          <Input required label="Tanggal kunjungan" type="date" value={visitDate} onChange={(event) => setVisitDate(event.target.value)} />
          <Button type="submit">Jadwalkan</Button>
        </form>
      </Card>

      <Card className="overflow-hidden">
        <div className="border-b border-gray-100 px-5 py-4">
          <h2 className="font-semibold text-gray-900">Jadwal kunjungan</h2>
        </div>
        {loading ? (
          <p className="p-8 text-sm text-gray-500">Memuat...</p>
        ) : (
          <Table
            columns={columns}
            rows={visits}
            rowKey={(visit) => visit.id}
            empty={<EmptyState icon={<span>📋</span>} title="Belum ada kunjungan" description="Jadwal kunjungan sales akan tampil di sini." />}
          />
        )}
        <TablePagination
          cursor={(meta.page - 1) * meta.limit}
          limit={meta.limit}
          total={meta.total}
          hasMore={meta.has_more}
          onPageChange={(nextCursor) => {
            const nextPage = Math.floor(nextCursor / meta.limit) + 1;
            if (token) void load(token, nextPage);
          }}
        />
      </Card>
    </div>
  );
}

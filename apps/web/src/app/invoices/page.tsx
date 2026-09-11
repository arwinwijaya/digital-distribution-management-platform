'use client';

import { useEffect, useState } from 'react';
import LoginForm from '@/components/LoginForm';
import { apiUrl, authHeaders, getStoredToken } from '@/lib/api';
import { Button, Card, EmptyState, PageHeader, StatCard, StatusBadge, Table } from '@/components/ui';

type Invoice = {
  id: number;
  order_id: number;
  invoice_number: string;
  issue_date: string | null;
  due_date: string | null;
  total_amount: string | number;
  paid_amount: string | number;
  balance_amount: string | number;
  status: string;
};

type PageMeta = { page: number; limit: number; total: number; has_more: boolean };
const PAGE_SIZE = 25;
const money = (value: string | number | null | undefined) => `Rp ${Number(value ?? 0).toLocaleString('id-ID')}`;
const date = (value: string | null) => value ? new Date(value).toLocaleDateString('id-ID') : '-';

export default function InvoicesPage() {
  const [token, setToken] = useState<string | null>(null);
  const [invoices, setInvoices] = useState<Invoice[]>([]);
  const [meta, setMeta] = useState<PageMeta>({ page: 1, limit: PAGE_SIZE, total: 0, has_more: false });
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);

  async function load(nextToken: string, page = 1) {
    setLoading(true);
    setError(null);
    try {
      const query = new URLSearchParams({ page: String(page), limit: String(PAGE_SIZE) });
      const response = await fetch(`${apiUrl('/invoices')}?${query}`, { headers: authHeaders(nextToken) });
      const body = await response.json();
      if (!response.ok) throw new Error(body.message || 'Data invoice tidak dapat dimuat.');
      setInvoices(Array.isArray(body.data) ? body.data : []);
      setMeta({
        page: Number(body.meta?.page ?? page),
        limit: Number(body.meta?.limit ?? PAGE_SIZE),
        total: Number(body.meta?.total ?? 0),
        has_more: Boolean(body.meta?.has_more),
      });
    } catch (reason) {
      setInvoices([]);
      setError(reason instanceof Error ? reason.message : 'Data invoice tidak dapat dimuat.');
    } finally {
      setLoading(false);
    }
  }

  useEffect(() => {
    const stored = getStoredToken();
    setToken(stored);
    if (stored) void load(stored);
  }, []);

  if (!token) return <div className="mx-auto max-w-6xl"><PageHeader title="Invoice" description="Pantau invoice dan saldo piutang." /><LoginForm expectedRole={['finance', 'admin', 'outlet']} onLogin={(nextToken) => { setToken(nextToken); void load(nextToken); }} /></div>;

  const totalBalance = invoices.reduce((sum, invoice) => sum + Number(invoice.balance_amount || 0), 0);
  const columns = [
    { key: 'number', header: 'Nomor invoice', render: (invoice: Invoice) => <span className="font-medium">{invoice.invoice_number}</span> },
    { key: 'order', header: 'Pesanan', render: (invoice: Invoice) => <span>#{invoice.order_id}</span> },
    { key: 'issue', header: 'Terbit', render: (invoice: Invoice) => date(invoice.issue_date) },
    { key: 'due', header: 'Jatuh tempo', render: (invoice: Invoice) => date(invoice.due_date) },
    { key: 'balance', header: 'Sisa', render: (invoice: Invoice) => <span className="font-semibold">{money(invoice.balance_amount)}</span> },
    { key: 'status', header: 'Status', render: (invoice: Invoice) => <StatusBadge status={invoice.status} /> },
  ];

  return <div className="mx-auto max-w-6xl">
    <PageHeader title="Invoice" description="Pantau invoice dan saldo piutang yang terotorisasi." />
    <section className="mb-6 grid gap-4 sm:grid-cols-2"><StatCard label="Invoice" value={meta.total.toLocaleString('id-ID')} icon="🧾" /><StatCard label="Sisa pada halaman ini" value={money(totalBalance)} icon="💰" /></section>
    {error && <p role="alert" className="mb-5 rounded-lg border border-danger-200 bg-danger-50 p-3 text-sm text-danger-700">{error}</p>}
    <Card className="overflow-hidden">
      <div className="flex flex-wrap items-center justify-between gap-3 border-b border-gray-100 px-5 py-4"><h2 className="font-semibold text-gray-900">Riwayat invoice</h2><p className="text-xs text-gray-500">Halaman {meta.page} · {meta.total.toLocaleString('id-ID')} total</p></div>
      {loading ? <p className="p-8 text-sm text-gray-500">Memuat invoice...</p> : <Table columns={columns} rows={invoices} rowKey={(invoice) => invoice.id} empty={<EmptyState icon={<span>🧾</span>} title="Belum ada invoice" description="Invoice yang tersedia akan tampil di sini." />} />}
      <div className="flex items-center justify-between border-t border-gray-100 px-5 py-4"><Button size="sm" variant="secondary" disabled={loading || meta.page <= 1} onClick={() => void load(token, meta.page - 1)}>Sebelumnya</Button><Button size="sm" variant="secondary" disabled={loading || !meta.has_more} onClick={() => void load(token, meta.page + 1)}>Berikutnya</Button></div>
    </Card>
  </div>;
}

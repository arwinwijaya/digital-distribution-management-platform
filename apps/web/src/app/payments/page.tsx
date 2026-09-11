'use client';

import { FormEvent, useEffect, useState } from 'react';
import LoginForm from '@/components/LoginForm';
import { apiUrl, authHeaders, getStoredToken } from '@/lib/api';
import { Button, Card, EmptyState, Input, PageHeader, StatCard, Table } from '@/components/ui';

type Payment = { id: number; order_id: number; amount: string; payment_method: string; receipt_reference: string | null; created_at: string };
type Invoice = { id: number; balance_amount: string | number; status: string };
type CreditSummary = { credit_limit: string | null; outstanding_balance: string; available_credit: string | null };
type PageMeta = { page: number; limit: number; total: number; has_more: boolean };
const PAGE_SIZE = 25;
const money = (value: string | number | null | undefined) => value === null ? 'Tidak terbatas' : `Rp ${Number(value ?? 0).toLocaleString('id-ID')}`;

export default function PaymentsPage() {
  const [token, setToken] = useState<string | null>(null);
  const [role, setRole] = useState<string | null>(null);
  const [payments, setPayments] = useState<Payment[]>([]);
  const [paymentMeta, setPaymentMeta] = useState<PageMeta>({ page: 1, limit: PAGE_SIZE, total: 0, has_more: false });
  const [invoiceMeta, setInvoiceMeta] = useState<PageMeta>({ page: 1, limit: PAGE_SIZE, total: 0, has_more: false });
  const [summary, setSummary] = useState<CreditSummary | null>(null);
  const [orderId, setOrderId] = useState('');
  const [amount, setAmount] = useState('');
  const [error, setError] = useState('');
  const [message, setMessage] = useState('');
  const [loading, setLoading] = useState(false);

  async function load(authToken: string, currentRole: string, page = 1) {
    setLoading(true);
    setError('');
    try {
      const query = new URLSearchParams({ page: String(page), limit: String(PAGE_SIZE) });
      const requests = [
        fetch(`${apiUrl('/payments')}?${query}`, { headers: authHeaders(authToken) }),
        fetch(`${apiUrl('/invoices')}?${query}`, { headers: authHeaders(authToken) }),
      ];
      if (currentRole !== 'finance') requests.push(fetch(apiUrl('/credit-limit'), { headers: authHeaders(authToken) }));
      const responses = await Promise.all(requests);
      const paymentsResponse = responses[0];
      const invoicesResponse = responses[1];
      const summaryResponse = responses[2];
      const paymentsBody = await paymentsResponse.json();
      const invoicesBody = await invoicesResponse.json();
      if (!paymentsResponse.ok) throw new Error(paymentsBody.message || 'Data pembayaran tidak dapat dimuat.');
      if (!invoicesResponse.ok) throw new Error(invoicesBody.message || 'Data invoice tidak dapat dimuat.');
      setPayments(Array.isArray(paymentsBody.data) ? paymentsBody.data : []);
      setPaymentMeta({ page: Number(paymentsBody.meta?.page ?? page), limit: Number(paymentsBody.meta?.limit ?? PAGE_SIZE), total: Number(paymentsBody.meta?.total ?? 0), has_more: Boolean(paymentsBody.meta?.has_more) });
      setInvoiceMeta({ page: Number(invoicesBody.meta?.page ?? page), limit: Number(invoicesBody.meta?.limit ?? PAGE_SIZE), total: Number(invoicesBody.meta?.total ?? 0), has_more: Boolean(invoicesBody.meta?.has_more) });
      if (summaryResponse) {
        const summaryBody = await summaryResponse.json();
        if (!summaryResponse.ok) throw new Error(summaryBody.message || 'Saldo kredit tidak dapat dimuat.');
        setSummary(summaryBody.data);
      } else {
        setSummary(null);
      }
    } catch (reason) {
      setError(reason instanceof Error ? reason.message : 'Data pembayaran tidak dapat dimuat.');
    } finally {
      setLoading(false);
    }
  }

  useEffect(() => {
    const stored = getStoredToken();
    if (!stored) return;
    let active = true;
    fetch(apiUrl('/auth/me'), { headers: authHeaders(stored) })
      .then(async (response) => {
        const body = await response.json();
        if (!response.ok) throw new Error(body.message || 'Sesi tidak dapat diverifikasi.');
        return body.data.role as string;
      })
      .then((currentRole) => {
        if (!active) return;
        setToken(stored);
        setRole(currentRole);
        void load(stored, currentRole);
      })
      .catch((reason) => {
        if (active) setError(reason instanceof Error ? reason.message : 'Sesi tidak dapat diverifikasi.');
      });
    return () => { active = false; };
  }, []);

  async function submit(event: FormEvent) {
    event.preventDefault();
    if (!token) return;
    setError('');
    setMessage('');
    try {
      const response = await fetch(apiUrl('/payments'), { method: 'POST', headers: authHeaders(token), body: JSON.stringify({ order_id: Number(orderId), amount: Number(amount), payment_method: 'cash', idempotency_key: `web-${orderId}-${amount}` }) });
      const body = await response.json();
      if (!response.ok) {
        setError(body.message || 'Pembayaran tidak dapat dicatat.');
        return;
      }
      setMessage(`Pembayaran berhasil dicatat. Referensi: ${body.data.receipt_reference || 'tersedia'}.`);
      setOrderId('');
      setAmount('');
      await load(token, role ?? 'finance', paymentMeta.page);
    } catch (reason) {
      setError(reason instanceof Error ? reason.message : 'Pembayaran tidak dapat dicatat.');
    }
  }

  if (!token) return <div className="mx-auto max-w-6xl"><PageHeader title="Pembayaran" description="Catat pembayaran dan pantau status invoice." />{error && <p role="alert" className="mb-5 rounded-lg border border-danger-200 bg-danger-50 p-3 text-sm text-danger-700">{error}</p>}<LoginForm expectedRole={['finance', 'admin', 'outlet']} onLogin={(nextToken, nextRole) => { setToken(nextToken); setRole(nextRole); void load(nextToken, nextRole); }} /></div>;

  const columns = [{ key: 'order', header: 'Pesanan', render: (payment: Payment) => <span className="font-medium">#{payment.order_id}</span> }, { key: 'method', header: 'Metode', render: (payment: Payment) => <span className="capitalize">{payment.payment_method}</span> }, { key: 'amount', header: 'Jumlah', render: (payment: Payment) => <span className="font-semibold">{money(payment.amount)}</span> }, { key: 'date', header: 'Tanggal', render: (payment: Payment) => new Date(payment.created_at).toLocaleDateString('id-ID') }];

  return <div className="mx-auto max-w-6xl"><PageHeader title="Pembayaran" description="Catat pembayaran dan pantau status invoice." />{summary && <section className="mb-6 grid gap-4 sm:grid-cols-3"><StatCard label="Batas kredit" value={money(summary.credit_limit)} icon="🎯" /><StatCard label="Piutang berjalan" value={money(summary.outstanding_balance)} icon="⏱️" changeType="down" /><StatCard label="Kredit tersedia" value={money(summary.available_credit)} icon="✅" /></section>}<section className="mb-6 grid gap-4 sm:grid-cols-2"><StatCard label="Total pembayaran" value={paymentMeta.total.toLocaleString('id-ID')} icon="💳" /><StatCard label="Total invoice" value={invoiceMeta.total.toLocaleString('id-ID')} icon="🧾" /></section><Card className="mb-6 p-5"><h2 className="mb-4 text-base font-semibold text-gray-900">Catat pembayaran</h2><form onSubmit={submit} className="grid gap-4 sm:grid-cols-[1fr_1fr_auto] sm:items-end"><Input required type="number" min="1" label="ID pesanan" value={orderId} onChange={(event) => setOrderId(event.target.value)} placeholder="Contoh: 1024" /><Input required type="number" min="0.01" step="0.01" label="Jumlah pembayaran" value={amount} onChange={(event) => setAmount(event.target.value)} placeholder="0" /><Button type="submit" disabled={loading}>Simpan pembayaran</Button></form></Card>{message && <p className="mb-5 rounded-lg border border-success-200 bg-success-50 p-3 text-sm text-success-700">{message}</p>}{error && <p role="alert" className="mb-5 rounded-lg border border-danger-200 bg-danger-50 p-3 text-sm text-danger-700">{error}</p>}<Card className="overflow-hidden"><div className="flex flex-wrap items-center justify-between gap-3 border-b border-gray-100 px-5 py-4"><h2 className="font-semibold text-gray-900">Riwayat pembayaran</h2><p className="text-xs text-gray-500">Halaman {paymentMeta.page} · {paymentMeta.total.toLocaleString('id-ID')} total</p></div>{loading ? <p className="p-8 text-sm text-gray-500">Memuat pembayaran...</p> : <Table columns={columns} rows={payments} rowKey={(payment) => payment.id} empty={<EmptyState icon={<span>💳</span>} title="Belum ada pembayaran" description="Pembayaran yang Anda catat akan tampil di sini." />} />}<div className="flex items-center justify-between border-t border-gray-100 px-5 py-4"><Button size="sm" variant="secondary" disabled={loading || paymentMeta.page <= 1} onClick={() => token && void load(token, role ?? 'finance', paymentMeta.page - 1)}>Sebelumnya</Button><Button size="sm" variant="secondary" disabled={loading || !paymentMeta.has_more} onClick={() => token && void load(token, role ?? 'finance', paymentMeta.page + 1)}>Berikutnya</Button></div></Card></div>;
}

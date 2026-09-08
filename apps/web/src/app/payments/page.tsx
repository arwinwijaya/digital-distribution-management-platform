'use client';

import { FormEvent, useEffect, useState } from 'react';
import LoginForm from '@/components/LoginForm';
import { apiUrl, authHeaders, getStoredToken } from '@/lib/api';

type Payment = {
  id: number;
  order_id: number;
  amount: string;
  payment_method: string;
  receipt_reference: string | null;
  created_at: string;
};

type CreditSummary = {
  credit_limit: string | null;
  outstanding_balance: string;
  available_credit: string | null;
};

export default function PaymentsPage() {
  const [token, setToken] = useState<string | null>(null);
  const [payments, setPayments] = useState<Payment[]>([]);
  const [summary, setSummary] = useState<CreditSummary | null>(null);
  const [orderId, setOrderId] = useState('');
  const [amount, setAmount] = useState('');
  const [error, setError] = useState('');
  const [message, setMessage] = useState('');

  const load = async (authToken: string) => {
    const headers = authHeaders(authToken);
    const [paymentsResponse, summaryResponse] = await Promise.all([
      fetch(apiUrl('/payments'), { headers }),
      fetch(apiUrl('/credit-limit'), { headers }),
    ]);
    if (!paymentsResponse.ok || !summaryResponse.ok) throw new Error('Unable to load payment data.');
    setPayments((await paymentsResponse.json()).data);
    setSummary((await summaryResponse.json()).data);
  };

  useEffect(() => {
    const stored = getStoredToken();
    setToken(stored);
    if (stored) load(stored).catch((reason: Error) => setError(reason.message));
  }, []);

  const submit = async (event: FormEvent) => {
    event.preventDefault();
    if (!token) return;
    setError('');
    setMessage('');
    const response = await fetch(apiUrl('/payments'), {
      method: 'POST',
      headers: authHeaders(token),
      body: JSON.stringify({
        order_id: Number(orderId),
        amount: Number(amount),
        payment_method: 'cash',
        idempotency_key: `web-${orderId}-${amount}`,
      }),
    });
    const body = await response.json();
    if (!response.ok) {
      setError(body.message || 'Payment could not be recorded.');
      return;
    }
    setMessage(`Payment recorded. Receipt ${body.data.receipt_reference || 'available'}.`);
    setOrderId('');
    setAmount('');
    await load(token);
  };

  if (!token) {
    return <main className="mx-auto max-w-5xl space-y-4 p-8"><h1 className="text-3xl font-bold">Payments</h1><LoginForm expectedRole="outlet" onLogin={(nextToken) => { setToken(nextToken); load(nextToken).catch((reason: Error) => setError(reason.message)); }} /></main>;
  }

  return (
    <main className="mx-auto max-w-5xl space-y-6 p-8">
      <header><h1 className="text-3xl font-bold">Payments and outstanding balance</h1><p className="text-gray-600">Record payments for delivered orders and review receipts.</p></header>
      {summary && <section className="grid gap-3 sm:grid-cols-3"><div className="rounded border bg-white p-4"><p className="text-sm text-gray-500">Credit limit</p><p className="text-xl font-semibold">{summary.credit_limit ?? 'Unlimited'}</p></div><div className="rounded border bg-white p-4"><p className="text-sm text-gray-500">Outstanding</p><p className="text-xl font-semibold">{summary.outstanding_balance}</p></div><div className="rounded border bg-white p-4"><p className="text-sm text-gray-500">Available credit</p><p className="text-xl font-semibold">{summary.available_credit ?? 'Unlimited'}</p></div></section>}
      <form onSubmit={submit} className="flex flex-wrap gap-3 rounded border bg-white p-4"><input required type="number" min="1" value={orderId} onChange={(event) => setOrderId(event.target.value)} placeholder="Delivered order ID" className="rounded border p-2" /><input required type="number" min="0.01" step="0.01" value={amount} onChange={(event) => setAmount(event.target.value)} placeholder="Amount" className="rounded border p-2" /><button className="rounded bg-blue-600 px-4 py-2 font-semibold text-white" type="submit">Record payment</button></form>
      {message && <p className="rounded bg-green-100 p-3 text-green-800">{message}</p>}
      {error && <p className="rounded bg-red-100 p-3 text-red-800">{error}</p>}
      <section className="space-y-2"><h2 className="text-xl font-semibold">Payment history</h2>{payments.length === 0 ? <p className="text-gray-600">No payments recorded yet.</p> : <div className="divide-y rounded border bg-white">{payments.map((payment) => <div key={payment.id} className="flex flex-wrap justify-between gap-2 p-3"><span>Order #{payment.order_id} · {payment.payment_method}</span><span>{payment.amount} · {payment.receipt_reference || 'Receipt pending'}</span></div>)}</div>}</section>
    </main>
  );
}

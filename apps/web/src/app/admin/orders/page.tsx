'use client';

import { useCallback, useEffect, useState } from 'react';
import LoginForm from '@/components/LoginForm';
import { apiUrl, authHeaders, getStoredToken } from '@/lib/api';
import type { Order } from '@/components/OrderForm';

export default function AdminOrdersPage() {
  const [token, setToken] = useState<string | null>(null);
  const [orders, setOrders] = useState<Order[]>([]);
  const [selected, setSelected] = useState<Order | null>(null);
  const [ready, setReady] = useState(false);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const loadOrders = useCallback(async (authToken: string) => {
    setLoading(true); setError(null);
    try {
      const response = await fetch(apiUrl('/admin/orders'), { headers: authHeaders(authToken) });
      const data = await response.json(); if (!response.ok) throw new Error(data.message || 'Unable to load orders.'); setOrders(data.data);
    } catch (err) { setError(err instanceof Error ? err.message : 'Unable to load orders.'); }
    finally { setLoading(false); }
  }, []);

  useEffect(() => { const stored = getStoredToken(); setToken(stored); setReady(true); if (stored) loadOrders(stored); }, [loadOrders]);
  async function showOrder(id: number) { if (!token) return; const response = await fetch(apiUrl(`/admin/orders/${id}`), { headers: authHeaders(token) }); const data = await response.json(); if (response.ok) setSelected(data.data); else setError(data.message || 'Unable to load order.'); }
  async function approve(id: number) { if (!token) return; const response = await fetch(apiUrl(`/orders/${id}/approve`), { method: 'PUT', headers: authHeaders(token) }); const data = await response.json(); if (!response.ok) { setError(data.message || 'Unable to approve order.'); return; } setSelected(data.data); await loadOrders(token); }

  if (!ready) return <main className="mx-auto max-w-5xl p-8"><p>Loading...</p></main>;
  if (!token) return <main className="mx-auto max-w-5xl space-y-4 p-8"><h1 className="text-3xl font-bold">Admin orders</h1><p className="text-gray-600">Administrator sign-in is required.</p><LoginForm expectedRole="admin" onLogin={(nextToken) => { setToken(nextToken); loadOrders(nextToken); }} /></main>;
  return <main className="mx-auto max-w-5xl space-y-6 p-8"><header><h1 className="text-3xl font-bold">Admin orders</h1><p className="text-gray-600">Review and approve outlet orders.</p></header>{error && <p role="alert" className="rounded bg-red-100 p-3 text-red-700">{error}</p>}{loading ? <p>Loading orders...</p> : orders.length === 0 ? <p className="rounded border bg-white p-6 text-gray-500">No orders yet.</p> : <div className="grid gap-6 md:grid-cols-[1fr_1fr]"><ul className="space-y-2">{orders.map((order) => <li key={order.id} className="flex items-center justify-between rounded border bg-white p-4"><button onClick={() => showOrder(order.id)} className="text-left"><strong>{order.order_id}</strong><span className="ml-3 text-sm text-gray-600">{order.status}</span></button><button onClick={() => approve(order.id)} disabled={order.status !== 'New'} className="rounded bg-blue-600 px-3 py-1 text-sm text-white disabled:bg-gray-300">Approve</button></li>)}</ul>{selected ? <article className="rounded border bg-white p-6"><h2 className="text-xl font-semibold">{selected.order_id}</h2><p className="mt-2">Status: {selected.status}</p><p>Total: Rp {Number(selected.total_amount).toLocaleString('id-ID')}</p><h3 className="mt-4 font-medium">Status history</h3><ol className="list-inside list-decimal text-sm text-gray-600">{(selected.status_history || []).map((history) => <li key={`${history.status}-${history.created_at}`}>{history.status} — {new Date(history.created_at).toLocaleString()}</li>)}</ol></article> : <p className="rounded border bg-white p-6 text-gray-500">Select an order to view details.</p>}</div>}</main>;
}

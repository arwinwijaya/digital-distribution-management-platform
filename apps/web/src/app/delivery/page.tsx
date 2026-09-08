'use client';

import { useEffect, useState } from 'react';
import LoginForm from '@/components/LoginForm';
import { apiUrl, authHeaders, getStoredToken } from '@/lib/api';

type Delivery = { id: number; order_id: number; driver_id: number; status: string; delivered_at: string | null; recipient_name: string | null };

export default function DeliveryPage() {
  const [token, setToken] = useState<string | null>(null);
  const [deliveries, setDeliveries] = useState<Delivery[]>([]);
  const [error, setError] = useState('');
  const [loading, setLoading] = useState(false);

  const load = async (nextToken: string) => {
    setLoading(true);
    try {
      const response = await fetch(apiUrl('/deliveries'), { headers: authHeaders(nextToken) });
      const body = await response.json();
      if (!response.ok) throw new Error(body.message || 'Unable to load deliveries.');
      setDeliveries(body.data); setError('');
    } catch (reason) { setError(reason instanceof Error ? reason.message : 'Unable to load deliveries.'); }
    finally { setLoading(false); }
  };

  useEffect(() => { const stored = getStoredToken(); setToken(stored); if (stored) load(stored); }, []);

  const updateStatus = async (delivery: Delivery, status: string) => {
    if (!token) return;
    const response = await fetch(apiUrl(`/deliveries/${delivery.id}/status`), { method: 'PATCH', headers: authHeaders(token), body: JSON.stringify({ status }) });
    const body = await response.json();
    if (!response.ok) { setError(body.message || 'Status update failed.'); return; }
    await load(token);
  };

  if (!token) return <main className="mx-auto max-w-5xl space-y-4 p-8"><h1 className="text-3xl font-bold">Deliveries</h1><LoginForm expectedRole="driver" onLogin={(nextToken) => { setToken(nextToken); load(nextToken); }} /></main>;
  return <main className="mx-auto max-w-5xl space-y-6 p-8"><header><h1 className="text-3xl font-bold">Delivery tracking</h1><p className="text-gray-600">Review assignments and record auditable delivery progress.</p></header>{error && <p className="rounded bg-red-100 p-3 text-red-800">{error}</p>}{loading ? <p>Loading deliveries...</p> : <section className="divide-y rounded border bg-white">{deliveries.length === 0 ? <p className="p-4 text-gray-600">No deliveries assigned.</p> : deliveries.map((delivery) => <div key={delivery.id} className="flex flex-wrap items-center justify-between gap-3 p-4"><span>Order #{delivery.order_id} · Driver #{delivery.driver_id}</span><span className="font-semibold">{delivery.status}</span>{delivery.status === 'assigned' && <button onClick={() => updateStatus(delivery, 'in_progress')} className="rounded bg-blue-600 px-3 py-1 text-white">Start</button>}{delivery.status === 'in_progress' && <button onClick={() => updateStatus(delivery, 'delivered')} className="rounded bg-green-600 px-3 py-1 text-white">Delivered</button>}</div>)}</section>}</main>;
}

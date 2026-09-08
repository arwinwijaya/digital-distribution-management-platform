'use client';

import { useEffect, useState } from 'react';
import LoginForm from '@/components/LoginForm';
import { apiUrl, authHeaders, getStoredToken } from '@/lib/api';

type Delivery = { id: number; order_id: number; driver_id: number; status: string; delivered_at: string | null; recipient_name: string | null };
type Proof = { recipient: string; url: string };

export default function DeliveryPage() {
  const [token, setToken] = useState<string | null>(null);
  const [deliveries, setDeliveries] = useState<Delivery[]>([]);
  const [error, setError] = useState('');
  const [loading, setLoading] = useState(false);
  const [proof, setProof] = useState<Record<number, Proof>>({});
  const [completing, setCompleting] = useState<number | null>(null);

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
    const details = proof[delivery.id];
    if (status === 'delivered' && (!details?.recipient.trim() || !details.url.trim())) {
      setError('Recipient name and a proof URL are required to complete delivery.');
      return;
    }

    setCompleting(delivery.id);
    try {
      const payload = status === 'delivered'
        ? { status, recipient_name: details.recipient.trim(), proof_of_delivery_url: details.url.trim() }
        : { status };
      const response = await fetch(apiUrl(`/deliveries/${delivery.id}/status`), { method: 'PATCH', headers: authHeaders(token), body: JSON.stringify(payload) });
      const body = await response.json();
      if (!response.ok) { setError(body.message || 'Status update failed.'); return; }
      setError('');
      await load(token);
    } catch (reason) {
      setError(reason instanceof Error ? reason.message : 'Status update failed.');
    } finally { setCompleting(null); }
  };

  if (!token) return <main className="mx-auto max-w-5xl space-y-4 p-8"><h1 className="text-3xl font-bold">Deliveries</h1><LoginForm expectedRole="driver" onLogin={(nextToken) => { setToken(nextToken); load(nextToken); }} /></main>;
  return <main className="mx-auto max-w-5xl space-y-6 p-8"><header><h1 className="text-3xl font-bold">Delivery tracking</h1><p className="text-gray-600">Review assignments and record auditable delivery progress.</p></header>{error && <p className="rounded bg-red-100 p-3 text-red-800">{error}</p>}{loading ? <p>Loading deliveries...</p> : <section className="divide-y rounded border bg-white">{deliveries.length === 0 ? <p className="p-4 text-gray-600">No deliveries assigned.</p> : deliveries.map((delivery) => { const details = proof[delivery.id] || { recipient: '', url: '' }; return <div key={delivery.id} className="flex flex-wrap items-center justify-between gap-3 p-4"><span>Order #{delivery.order_id} · Driver #{delivery.driver_id}</span><span className="font-semibold">{delivery.status}</span>{delivery.status === 'assigned' && <button onClick={() => updateStatus(delivery, 'in_progress')} className="rounded bg-blue-600 px-3 py-1 text-white">Start</button>}{delivery.status === 'in_progress' && <div className="flex w-full flex-wrap items-end gap-2"><label className="flex flex-col text-sm">Recipient<input required value={details.recipient} onChange={(event) => setProof({ ...proof, [delivery.id]: { ...details, recipient: event.target.value } })} className="rounded border p-2" placeholder="Recipient name" /></label><label className="flex flex-col text-sm">Proof URL<input required type="url" value={details.url} onChange={(event) => setProof({ ...proof, [delivery.id]: { ...details, url: event.target.value } })} className="rounded border p-2" placeholder="https://..." /></label><button disabled={completing === delivery.id} onClick={() => updateStatus(delivery, 'delivered')} className="rounded bg-green-600 px-3 py-2 text-white disabled:opacity-50">{completing === delivery.id ? 'Saving...' : 'Delivered'}</button></div>}</div>; })}</section>}</main>;
}

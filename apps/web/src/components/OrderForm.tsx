'use client';

import { FormEvent, useEffect, useMemo, useRef, useState } from 'react';
import { apiUrl, authHeaders } from '@/lib/api';

export interface Product { id: number; name: string; price: string; stock_quantity: number; is_active: boolean; }
export interface Order { id: number; order_id: string; status: string; total_amount: string; items: Array<{ product_name: string; quantity: number; subtotal: string }>; status_history?: Array<{ status: string; notes?: string; created_at: string }>; }

export default function OrderForm({ token }: { token: string }) {
  const [products, setProducts] = useState<Product[]>([]);
  const [cart, setCart] = useState<Record<number, number>>({});
  const [order, setOrder] = useState<Order | null>(null);
  const [trackingId, setTrackingId] = useState('');
  const [tracked, setTracked] = useState<Order | null>(null);
  const [loading, setLoading] = useState(true);
  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const idempotencyAttempt = useRef<{ signature: string; key: string } | null>(null);

  useEffect(() => {
    fetch(apiUrl('/products'), { headers: authHeaders(token) })
      .then(async (response) => { const data = await response.json(); if (!response.ok) throw new Error(data.message || 'Catalog unavailable.'); return data.data; })
      .then(setProducts).catch((err) => setError(err.message || 'Catalog unavailable.')).finally(() => setLoading(false));
  }, [token]);

  const total = useMemo(() => products.reduce((sum, product) => sum + Number(product.price) * (cart[product.id] || 0), 0), [products, cart]);
  const changeQuantity = (id: number, quantity: number) => setCart((current) => ({ ...current, [id]: Math.max(0, quantity) }));

  async function submitOrder() {
    const items = Object.entries(cart).filter(([, quantity]) => quantity > 0).map(([product_id, quantity]) => ({ product_id: Number(product_id), quantity }));
    if (!items.length) { setError('Add at least one available product.'); return; }
    setSubmitting(true); setError(null);
    try {
      // Keep one identity for this cart payload so a retry cannot create a duplicate order.
      const signature = JSON.stringify(items);
      if (!idempotencyAttempt.current || idempotencyAttempt.current.signature !== signature) {
        const key = typeof crypto !== 'undefined' && crypto.randomUUID ? crypto.randomUUID() : `${Date.now()}-${Math.random()}`;
        idempotencyAttempt.current = { signature, key };
      }
      const idempotencyKey = idempotencyAttempt.current.key;
      const response = await fetch(apiUrl('/orders'), { method: 'POST', headers: { ...authHeaders(token), 'Idempotency-Key': idempotencyKey }, body: JSON.stringify({ items, idempotency_key: idempotencyKey }) });
      const data = await response.json();
      if (!response.ok) throw new Error(data.message || 'Order could not be submitted.');
      setOrder(data.data); setTrackingId(String(data.data.id)); setCart({}); idempotencyAttempt.current = null;
    } catch (err) { setError(err instanceof Error ? err.message : 'Order could not be submitted.'); }
    finally { setSubmitting(false); }
  }

  async function trackOrder(event: FormEvent) {
    event.preventDefault(); setError(null);
    if (!trackingId.trim()) return;
    try {
      const response = await fetch(apiUrl(`/orders/${encodeURIComponent(trackingId)}`), { headers: authHeaders(token) });
      const data = await response.json(); if (!response.ok) throw new Error(data.message || 'Order not found.'); setTracked(data.data);
    } catch (err) { setError(err instanceof Error ? err.message : 'Order not found.'); }
  }

  if (loading) return <p className="py-8 text-gray-500">Loading catalog...</p>;
  return <div className="space-y-8">
    {error && <p role="alert" className="rounded bg-red-100 p-3 text-red-700">{error}</p>}
    <section><h2 className="mb-4 text-xl font-semibold">Browse products</h2>{products.length === 0 ? <p className="text-gray-500">No products available.</p> : <div className="grid gap-4 md:grid-cols-2">{products.map((product) => <article key={product.id} className="rounded border bg-white p-4"><div className="flex justify-between"><h3 className="font-semibold">{product.name}</h3><span>Rp {Number(product.price).toLocaleString('id-ID')}</span></div><p className="text-sm text-gray-500">{product.is_active && product.stock_quantity > 0 ? `${product.stock_quantity} available` : 'Unavailable'}</p><input aria-label={`Quantity for ${product.name}`} type="number" min="0" max={product.stock_quantity} disabled={!product.is_active || product.stock_quantity < 1} value={cart[product.id] || 0} onChange={(e) => changeQuantity(product.id, Number(e.target.value))} className="mt-3 w-24 rounded border p-2" /></article>)}</div>}</section>
    <section className="rounded-lg border bg-white p-5"><h2 className="text-xl font-semibold">Cart</h2><p className="mt-2">Total: Rp {total.toLocaleString('id-ID')}</p><button onClick={submitOrder} disabled={submitting || total === 0} className="mt-4 rounded bg-blue-600 px-4 py-2 text-white disabled:opacity-50">{submitting ? 'Submitting...' : 'Submit order'}</button>{order && <p className="mt-3 text-green-700">Order {order.order_id} submitted with status {order.status}.</p>}</section>
    <section className="rounded-lg border bg-white p-5"><h2 className="text-xl font-semibold">Track an order</h2><form onSubmit={trackOrder} className="mt-3 flex gap-2"><input aria-label="Order ID" value={trackingId} onChange={(e) => setTrackingId(e.target.value)} placeholder="Order numeric ID" className="flex-1 rounded border p-2" /><button className="rounded bg-gray-800 px-4 py-2 text-white">Track</button></form>{tracked && <div className="mt-4"><p className="font-medium">{tracked.order_id}: {tracked.status}</p><ol className="mt-2 list-inside list-decimal text-sm text-gray-600">{(tracked.status_history || []).map((history) => <li key={`${history.status}-${history.created_at}`}>{history.status} — {new Date(history.created_at).toLocaleString()}</li>)}</ol></div>}</section>
  </div>;
}

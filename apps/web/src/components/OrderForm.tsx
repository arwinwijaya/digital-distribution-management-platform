'use client';

import { FormEvent, useEffect, useMemo, useRef, useState } from 'react';
import { apiUrl, authHeaders } from '@/lib/api';
import { Button, Card, EmptyState, Input, StatusBadge } from '@/components/ui';

export interface Product { id: number; name: string; price: string; stock_quantity: number; is_active: boolean; }
export interface Order { id: number; order_id: string; status: string; total_amount: string; items: Array<{ product_name: string; quantity: number; subtotal: string }>; status_history?: Array<{ status: string; notes?: string; created_at: string }>; }

export default function OrderForm({ token }: { token: string }) {
  const [products, setProducts] = useState<Product[]>([]); const [cart, setCart] = useState<Record<number, number>>({});
  const [order, setOrder] = useState<Order | null>(null); const [trackingId, setTrackingId] = useState(''); const [tracked, setTracked] = useState<Order | null>(null);
  const [loading, setLoading] = useState(true); const [submitting, setSubmitting] = useState(false); const [error, setError] = useState<string | null>(null);
  const idempotencyAttempt = useRef<{ signature: string; key: string } | null>(null);

  useEffect(() => { fetch(apiUrl('/products'), { headers: authHeaders(token) }).then(async (response) => { const data = await response.json(); if (!response.ok) throw new Error(data.message || 'Katalog tidak tersedia.'); return data.data; }).then(setProducts).catch((reason) => setError(reason.message || 'Katalog tidak tersedia.')).finally(() => setLoading(false)); }, [token]);
  const total = useMemo(() => products.reduce((sum, product) => sum + Number(product.price) * (cart[product.id] || 0), 0), [products, cart]);
  const changeQuantity = (id: number, quantity: number) => setCart((current) => ({ ...current, [id]: Math.max(0, quantity) }));

  async function submitOrder() {
    const items = Object.entries(cart).filter(([, quantity]) => quantity > 0).map(([product_id, quantity]) => ({ product_id: Number(product_id), quantity }));
    if (!items.length) { setError('Pilih minimal satu produk.'); return; }
    setSubmitting(true); setError(null);
    try {
      const signature = JSON.stringify(items);
      if (!idempotencyAttempt.current || idempotencyAttempt.current.signature !== signature) { const key = typeof crypto !== 'undefined' && crypto.randomUUID ? crypto.randomUUID() : `${Date.now()}-${Math.random()}`; idempotencyAttempt.current = { signature, key }; }
      const idempotencyKey = idempotencyAttempt.current.key;
      const response = await fetch(apiUrl('/orders'), { method: 'POST', headers: { ...authHeaders(token), 'Idempotency-Key': idempotencyKey }, body: JSON.stringify({ items, idempotency_key: idempotencyKey }) });
      const data = await response.json(); if (!response.ok) throw new Error(data.message || 'Pesanan tidak dapat dibuat.');
      setOrder(data.data); setTrackingId(String(data.data.id)); setCart({}); idempotencyAttempt.current = null;
    } catch (reason) { setError(reason instanceof Error ? reason.message : 'Pesanan tidak dapat dibuat.'); } finally { setSubmitting(false); }
  }

  async function trackOrder(event: FormEvent) {
    event.preventDefault(); setError(null); if (!trackingId.trim()) return;
    try { const response = await fetch(apiUrl(`/orders/${encodeURIComponent(trackingId)}`), { headers: authHeaders(token) }); const data = await response.json(); if (!response.ok) throw new Error(data.message || 'Pesanan tidak ditemukan.'); setTracked(data.data); }
    catch (reason) { setError(reason instanceof Error ? reason.message : 'Pesanan tidak ditemukan.'); }
  }

  if (loading) return <div className="space-y-3"><div className="skeleton h-6 w-40 rounded" /><div className="grid gap-4 sm:grid-cols-2"><div className="skeleton h-32 rounded-xl" /><div className="skeleton h-32 rounded-xl" /></div></div>;
  return <div className="space-y-6">
    {error && <p role="alert" className="rounded-lg border border-danger-200 bg-danger-50 p-3 text-sm text-danger-700">{error}</p>}
    <section><div className="mb-3 flex items-center justify-between"><h2 className="text-base font-semibold text-gray-900">Pilih produk</h2><span className="text-sm text-gray-500">{products.length} produk</span></div>{products.length === 0 ? <Card><EmptyState icon={<span>📦</span>} title="Belum ada produk" description="Produk yang tersedia akan muncul di sini." /></Card> : <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">{products.map((product) => { const available = product.is_active && product.stock_quantity > 0; return <Card key={product.id} className="p-4"><div className="flex items-start justify-between gap-3"><div className="flex h-10 w-10 items-center justify-center rounded-lg bg-primary-50">📦</div><StatusBadge status={available ? 'active' : 'inactive'} /></div><h3 className="mt-3 font-semibold text-gray-900">{product.name}</h3><p className="mt-1 text-sm font-bold text-primary-700">Rp {Number(product.price).toLocaleString('id-ID')}</p><div className="mt-3 flex items-center justify-between"><span className="text-xs text-gray-500">Stok {product.stock_quantity}</span><input aria-label={`Jumlah ${product.name}`} type="number" min="0" max={product.stock_quantity} disabled={!available} value={cart[product.id] || 0} onChange={(event) => changeQuantity(product.id, Number(event.target.value))} className="w-20 rounded-lg border border-gray-200 px-2 py-1.5 text-center text-sm focus:border-primary-500 focus:outline-none disabled:bg-gray-100" /></div></Card>; })}</div>}</section>
    <Card className="p-5"><div className="flex flex-wrap items-center justify-between gap-4"><div><p className="text-sm text-gray-500">Total pesanan</p><p className="text-2xl font-bold text-gray-900">Rp {total.toLocaleString('id-ID')}</p></div><Button onClick={submitOrder} disabled={submitting || total === 0}>{submitting ? 'Mengirim...' : 'Kirim pesanan'}</Button></div>{order && <div className="mt-4 flex items-center gap-2 rounded-lg bg-success-50 p-3 text-sm text-success-700">Pesanan <strong>{order.order_id}</strong> berhasil dibuat. <StatusBadge status={order.status} /></div>}</Card>
    <Card className="p-5"><h2 className="text-base font-semibold text-gray-900">Lacak pesanan</h2><form onSubmit={trackOrder} className="mt-3 flex gap-2"><Input aria-label="Nomor pesanan" value={trackingId} onChange={(event) => setTrackingId(event.target.value)} placeholder="Masukkan ID pesanan" /><Button type="submit" variant="secondary">Lacak</Button></form>{tracked && <div className="mt-4 border-t border-gray-100 pt-4"><div className="flex items-center gap-2"><strong>{tracked.order_id}</strong><StatusBadge status={tracked.status} /></div><ol className="mt-3 space-y-2 border-l-2 border-primary-100 pl-4 text-sm text-gray-600">{(tracked.status_history || []).map((history) => <li key={`${history.status}-${history.created_at}`}><span className="font-medium text-gray-800">{history.status}</span> · {new Date(history.created_at).toLocaleString('id-ID')}</li>)}</ol></div>}</Card>
  </div>;
}

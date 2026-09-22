'use client';

import { FormEvent, useEffect, useMemo, useRef, useState } from 'react';
import { apiUrl, authHeaders } from '@/lib/api';
import { withDummyRead, useDummyRefresh } from '@/dummy/guards';
import { useDummyStore } from '@/dummy/store';
import { createDummyOutletOrder } from '@/dummy/mutations';
import type { FullDummy } from '@/dummy';
import { Button, Card, EmptyState, Input, StatusBadge, Table, ViewModeToggle, useViewMode } from '@/components/ui';
import { filterProducts } from '@/lib/product-filter';
import { useOnlineStatus } from '@/hooks/useOnlineStatus';
import { createOrderQueue, createDefaultAdapter, type OrderQueue } from '@/lib/offline/order-queue';


export interface Product { id: number; name: string; price: string; stock_quantity: number; is_active: boolean; }
export interface Order { id: number; order_id: string; status: string; total_amount: string; items: Array<{ product_name: string; quantity: number; subtotal: string }>; status_history?: Array<{ status: string; notes?: string; created_at: string }>; }

export async function submitOutletOrder(token: string, items: Array<{ product_id: number; quantity: number }>, idempotencyKey: string): Promise<Order> {
  if (useDummyStore.getState().isDummy) {
    return createDummyOutletOrder(items) as unknown as Order;
  }
  const response = await fetch(apiUrl('/orders'), {
    method: 'POST',
    headers: { ...authHeaders(token), 'Idempotency-Key': idempotencyKey },
    body: JSON.stringify({ items, idempotency_key: idempotencyKey }),
  });
  const data = await response.json();
  if (!response.ok) throw new Error(data.message || 'Pesanan tidak dapat dibuat.');
  return data.data as Order;
}

function money(value: number): string {
  return (Math.round(value * 100) / 100).toFixed(2);
}

/**
 * Load product catalog for the order form — guarded.
 * While dummy mode is ON, derives catalog from T5 master data — zero network.
 */
export async function loadOrderFormProducts(token: string): Promise<Product[]> {
  const { isDummy, dummyEntities } = useDummyStore.getState();
  const dummy = dummyEntities as FullDummy | null;

  const dummyCatalog: Product[] | null = isDummy && dummy
    ? dummy.products.map((p, idx) => ({
        id: idx + 1,
        name: p.name,
        price: money(p.price),
        stock_quantity: 40 + ((idx * 13) % 260),
        is_active: true,
      }))
    : null;

  return withDummyRead(isDummy, dummyCatalog as Product[], async () => {
    const response = await fetch(apiUrl('/products'), { headers: authHeaders(token) });
    const data = await response.json();
    if (!response.ok) throw new Error(data.message || 'Katalog tidak tersedia.');
    return data.data as Product[];
  });
}

/**
 * Track an order — guarded.
 * While dummy mode is ON, matches by id/order_id from the dummy graph,
 * returning the first dummy order if no direct match (demo fallback).
 * Zero network in dummy mode.
 */
export async function trackOrder(token: string, trackingId: string): Promise<Order | null> {
  const { isDummy, dummyEntities } = useDummyStore.getState();
  const dummy = dummyEntities as FullDummy | null;

  if (isDummy && dummy) {
    const match = dummy.orders.find(
      (o) => String(o.id) === trackingId || o.order_id === trackingId,
    );
    const order = match ?? dummy.orders[0];
    if (!order) return null;
    return {
      id: order.id,
      order_id: order.order_id,
      status: order.status,
      total_amount: order.total_amount,
      items: order.items.map((it) => ({
        product_name: it.product_name,
        quantity: it.quantity,
        subtotal: it.subtotal,
      })),
      status_history: order.status_history,
    };
  }

  const response = await fetch(apiUrl(`/orders/${encodeURIComponent(trackingId)}`), {
    headers: authHeaders(token),
  });
  const data = await response.json();
  if (!response.ok) throw new Error(data.message || 'Pesanan tidak ditemukan.');
  return data.data as Order;
}

export default function OrderForm({ token }: { token: string }) {
  const [products, setProducts] = useState<Product[]>([]); const [cart, setCart] = useState<Record<number, number>>({});
  const [order, setOrder] = useState<Order | null>(null); const [trackingId, setTrackingId] = useState(''); const [tracked, setTracked] = useState<Order | null>(null);
  const [loading, setLoading] = useState(true); const [submitting, setSubmitting] = useState(false); const [error, setError] = useState<string | null>(null);
  const [search, setSearch] = useState(''); const [minPrice, setMinPrice] = useState(''); const [maxPrice, setMaxPrice] = useState('');
  const [pendingCount, setPendingCount] = useState(0);
  const { viewMode, setViewMode } = useViewMode();
  const idempotencyAttempt = useRef<{ signature: string; key: string } | null>(null);
  const queueRef = useRef<OrderQueue | null>(null);
  const isOnline = useOnlineStatus();
  const isDummy = useDummyStore((s) => s.isDummy);

  // Initialize queue once
  useEffect(() => {
    let mounted = true;
    createDefaultAdapter().then((adapter) => {
      if (mounted) queueRef.current = createOrderQueue(adapter);
    });
    return () => { mounted = false; };
  }, []);

  // Update pending count when online status changes
  useEffect(() => {
    if (queueRef.current) {
      queueRef.current.list().then((items) => setPendingCount(items.length));
    }
  }, [isOnline]);

  // Auto-flush when coming back online
  useEffect(() => {
    if (!isOnline || !queueRef.current || isDummy) return;
    const flush = async () => {
      const result = await queueRef.current!.flushQueue(async (payload, init) => {
        const response = await fetch(apiUrl('/orders'), {
          method: 'POST',
          headers: { ...authHeaders(token), ...(init?.headers as Record<string, string>) },
          body: JSON.stringify(payload),
        });
        return response;
      });
      if (result.sent > 0 || result.failed > 0) {
        setPendingCount(result.failed);
      }
    };
    flush();
  }, [isOnline, token, isDummy]);

  useEffect(() => {
    loadOrderFormProducts(token)
      .then(setProducts)
      .catch((reason) => setError(reason.message || 'Katalog tidak tersedia.'))
      .finally(() => setLoading(false));
  }, [token]);
  useDummyRefresh(() => {
    setLoading(true);
    loadOrderFormProducts(token)
      .then(setProducts)
      .catch((reason) => setError(reason.message || 'Katalog tidak tersedia.'))
      .finally(() => setLoading(false));
  });
  const total = useMemo(() => products.reduce((sum, product) => sum + Number(product.price) * (cart[product.id] || 0), 0), [products, cart]);
  const changeQuantity = (id: number, quantity: number) => setCart((current) => ({ ...current, [id]: Math.max(0, quantity) }));
  const filteredProducts = useMemo(() => filterProducts(products, { name: search, minPrice, maxPrice }), [products, search, minPrice, maxPrice]);
  const columns = [
    { key: 'name', header: 'Produk', render: (product: Product) => <span className="font-medium text-gray-900">{product.name}</span> },
    { key: 'price', header: 'Harga', render: (product: Product) => <span className="font-bold text-primary-700">Rp {Number(product.price).toLocaleString('id-ID')}</span> },
    { key: 'stock', header: 'Stok', render: (product: Product) => <span className="text-gray-600">{product.stock_quantity}</span> },
    { key: 'quantity', header: 'Jumlah', render: (product: Product) => { const available = product.is_active && product.stock_quantity > 0; return <input aria-label={`Jumlah ${product.name}`} type="number" min="0" max={product.stock_quantity} disabled={!available} value={cart[product.id] || 0} onChange={(event) => changeQuantity(product.id, Number(event.target.value))} className="w-20 rounded-lg border border-gray-200 px-2 py-1.5 text-center text-sm focus:border-primary-500 focus:outline-none disabled:bg-gray-100" />; } },
  ];

  async function submitOrder() {
    const items = Object.entries(cart).filter(([, quantity]) => quantity > 0).map(([product_id, quantity]) => ({ product_id: Number(product_id), quantity }));
    if (!items.length) { setError('Pilih minimal satu produk.'); return; }
    setSubmitting(true); setError(null);
    try {
      const signature = JSON.stringify(items);
      if (!idempotencyAttempt.current || idempotencyAttempt.current.signature !== signature) { const key = typeof crypto !== 'undefined' && crypto.randomUUID ? crypto.randomUUID() : `${Date.now()}-${Math.random()}`; idempotencyAttempt.current = { signature, key }; }
      const idempotencyKey = idempotencyAttempt.current.key;

      // Offline path: enqueue instead of direct submit
      if (!isOnline && !isDummy && queueRef.current) {
        await queueRef.current.enqueue({ items, idempotency_key: idempotencyKey });
        const queued = await queueRef.current.list();
        setPendingCount(queued.length);
        idempotencyAttempt.current = null; // clear for next order
        setCart({});
        setSubmitting(false);
        return;
      }

      const data = await submitOutletOrder(token, items, idempotencyKey);
      setOrder(data); setTrackingId(String(data.id)); setCart({}); idempotencyAttempt.current = null;
    } catch (reason) { setError(reason instanceof Error ? reason.message : 'Pesanan tidak dapat dibuat.'); } finally { setSubmitting(false); }
  }

  async function handleTrackOrder(event: FormEvent) {
    event.preventDefault(); setError(null); if (!trackingId.trim()) return;
    try { const result = await trackOrder(token, trackingId); setTracked(result); }
    catch (reason) { setError(reason instanceof Error ? reason.message : 'Pesanan tidak ditemukan.'); }
  }

  if (loading) return <div className="space-y-3"><div className="skeleton h-6 w-40 rounded" /><div className="grid gap-4 sm:grid-cols-2"><div className="skeleton h-32 rounded-xl" /><div className="skeleton h-32 rounded-xl" /></div></div>;
  return <div className="space-y-6">
    {error && <p role="alert" className="rounded-lg border border-danger-200 bg-danger-50 p-3 text-sm text-danger-700">{error}</p>}
    {pendingCount > 0 && (
      <p role="status" className="rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-700">
        {pendingCount} pesanan menunggu sinkronisasi {isOnline ? ' (menyinkronkan...)' : '(offline)'}
      </p>
    )}
    <section>
      <div className="mb-3 flex flex-wrap items-center justify-between gap-3">
        <div>
          <h2 className="text-base font-semibold text-gray-900">Pilih produk</h2>
          <span className="text-sm text-gray-500">{filteredProducts.length} dari {products.length} produk</span>
        </div>
        <ViewModeToggle value={viewMode} onChange={setViewMode} />
      </div>
      <div className="mb-4 grid gap-3 sm:grid-cols-3">
        <Input aria-label="Cari produk" value={search} onChange={(event) => setSearch(event.target.value)} placeholder="Cari nama produk..." />
        <Input aria-label="Harga minimum" type="number" min="0" value={minPrice} onChange={(event) => setMinPrice(event.target.value)} placeholder="Harga min" />
        <Input aria-label="Harga maksimum" type="number" min="0" value={maxPrice} onChange={(event) => setMaxPrice(event.target.value)} placeholder="Harga maks" />
      </div>
      {products.length === 0
        ? <Card><EmptyState icon={<span>📦</span>} title="Belum ada produk" description="Produk yang tersedia akan muncul di sini." /></Card>
        : filteredProducts.length === 0
          ? <Card><EmptyState icon={<span>🔍</span>} title="Produk tidak ditemukan" description="Tidak ada produk yang cocok dengan pencarian atau rentang harga Anda." /></Card>
          : viewMode === 'table'
            ? <Card className="overflow-hidden"><Table columns={columns} rows={filteredProducts} rowKey={(product) => product.id} /></Card>
            : <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">{filteredProducts.map((product) => { const available = product.is_active && product.stock_quantity > 0; return <Card key={product.id} className="p-4"><div className="flex items-start justify-between gap-3"><div className="flex h-10 w-10 items-center justify-center rounded-lg bg-primary-50">📦</div><StatusBadge status={available ? 'active' : 'inactive'} /></div><h3 className="mt-3 font-semibold text-gray-900">{product.name}</h3><p className="mt-1 text-sm font-bold text-primary-700">Rp {Number(product.price).toLocaleString('id-ID')}</p><div className="mt-3 flex items-center justify-between"><span className="text-xs text-gray-500">Stok {product.stock_quantity}</span><input aria-label={`Jumlah ${product.name}`} type="number" min="0" max={product.stock_quantity} disabled={!available} value={cart[product.id] || 0} onChange={(event) => changeQuantity(product.id, Number(event.target.value))} className="w-20 rounded-lg border border-gray-200 px-2 py-1.5 text-center text-sm focus:border-primary-500 focus:outline-none disabled:bg-gray-100" /></div></Card>; })}</div>}
    </section>
    <Card className="p-5"><div className="flex flex-wrap items-center justify-between gap-4"><div><p className="text-sm text-gray-500">Total pesanan</p><p className="text-2xl font-bold text-gray-900">Rp {total.toLocaleString('id-ID')}</p></div><Button onClick={submitOrder} disabled={submitting || total === 0}>{submitting ? 'Mengirim...' : 'Kirim pesanan'}</Button></div>{order && <div className="mt-4 flex items-center gap-2 rounded-lg bg-success-50 p-3 text-sm text-success-700">Pesanan <strong>{order.order_id}</strong> berhasil dibuat. <StatusBadge status={order.status} /></div>}</Card>
    <Card className="p-5"><h2 className="text-base font-semibold text-gray-900">Lacak pesanan</h2><form onSubmit={handleTrackOrder} className="mt-3 flex gap-2"><Input aria-label="Nomor pesanan" value={trackingId} onChange={(event) => setTrackingId(event.target.value)} placeholder="Masukkan ID pesanan" /><Button type="submit" variant="secondary">Lacak</Button></form>{tracked && <div className="mt-4 border-t border-gray-100 pt-4"><div className="flex items-center gap-2"><strong>{tracked.order_id}</strong><StatusBadge status={tracked.status} /></div><ol className="mt-3 space-y-2 border-l-2 border-primary-100 pl-4 text-sm text-gray-600">{(tracked.status_history || []).map((history) => <li key={`${history.status}-${history.created_at}`}><span className="font-medium text-gray-800">{history.status}</span> · {new Date(history.created_at).toLocaleString('id-ID')}</li>)}</ol></div>}</Card>
  </div>;
}

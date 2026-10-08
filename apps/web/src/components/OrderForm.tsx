'use client';

import { useEffect, useMemo, useState } from 'react';
import { apiUrl, authHeaders } from '@/lib/api';
import { withDummyRead, useDummyRefresh } from '@/dummy/guards';
import { useDummyStore } from '@/dummy/store';
import { createDummyOutletOrder } from '@/dummy/mutations';
import type { FullDummy } from '@/dummy';
import { Button, Card, Input, StatusBadge } from '@/components/ui';
import OrderFormRecommendations from '@/components/OrderFormRecommendations';
import OrderFormCatalog from '@/components/OrderFormCatalog';
import OrderFormTracking from '@/components/OrderFormTracking';
import { useOnlineStatus } from '@/hooks/useOnlineStatus';
import { useOrderSubmission } from '@/hooks/useOrderSubmission';


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
  if (!response.ok) {
    const err = new Error(data.message || 'Pesanan tidak dapat dibuat.') as Error & {
      errors?: Record<string, string[]>;
    };
    if (data.errors && typeof data.errors === 'object') {
      err.errors = data.errors as Record<string, string[]>;
    }
    throw err;
  }
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
  const [products, setProducts] = useState<Product[]>([]);
  const [loading, setLoading] = useState(true);
  const isOnline = useOnlineStatus();
  const isDummy = useDummyStore((s) => s.isDummy);
  const { cart, setCart, order, trackingId, setTrackingId, submitting, error, setError,
    reorderRefreshKey, discoveryRefreshKey, pendingCount, submitOrder, discoveryCart, addDiscoveryItem } =
    useOrderSubmission(token, isOnline, isDummy, submitOutletOrder);

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
  const productIdSet = useMemo(() => new Set(products.map((p) => p.id)), [products]);
  const total = useMemo(() => {
    const catalogTotal = products.reduce((sum, product) => sum + Number(product.price) * (cart[product.id] || 0), 0);
    const discoveryFallbackTotal = Object.entries(discoveryCart)
      .filter(([id]) => !productIdSet.has(Number(id)))
      .reduce((sum, [, entry]) => sum + Number(entry.price) * (entry.quantity || 0), 0);
    return catalogTotal + discoveryFallbackTotal;
  }, [products, cart, discoveryCart, productIdSet]);

  if (loading) return <div className="space-y-3"><div className="skeleton h-6 w-40 rounded" /><div className="grid gap-4 sm:grid-cols-2"><div className="skeleton h-32 rounded-xl" /><div className="skeleton h-32 rounded-xl" /></div></div>;
  return <div className="space-y-6">
    {error && <p role="alert" className="rounded-lg border border-danger-200 bg-danger-50 p-3 text-sm text-danger-700">{error}</p>}
    {pendingCount > 0 && (
      <p role="status" className="rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-700">
        {pendingCount} pesanan menunggu sinkronisasi {isOnline ? ' (menyinkronkan...)' : '(offline)'}
      </p>
    )}
    <section>
      <OrderFormRecommendations
        token={token}
        products={products}
        cart={cart}
        setCart={setCart}
        isOnline={isOnline}
        isDummy={isDummy}
        reorderRefreshKey={reorderRefreshKey}
        discoveryRefreshKey={discoveryRefreshKey}
        onDiscoveryAdd={addDiscoveryItem}
      />
      <OrderFormCatalog products={products} cart={cart} setCart={setCart} />
    </section>
    <Card className="p-5"><div className="flex flex-wrap items-center justify-between gap-4"><div><p className="text-sm text-gray-500">Total pesanan</p><p className="text-2xl font-bold text-gray-900">Rp {total.toLocaleString('id-ID')}</p></div><Button onClick={submitOrder} disabled={submitting || total === 0} data-testid="order-submit">{submitting ? 'Mengirim...' : 'Kirim pesanan'}</Button></div>{order && <div className="mt-4 flex items-center gap-2 rounded-lg bg-success-50 p-3 text-sm text-success-700" data-testid="order-success">Pesanan <strong>{order.order_id}</strong> berhasil dibuat. <StatusBadge status={order.status} /></div>}</Card>
    <OrderFormTracking token={token} trackingId={trackingId} setTrackingId={setTrackingId} onError={setError} />
  </div>;
}

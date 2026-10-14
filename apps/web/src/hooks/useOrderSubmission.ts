'use client';

import { useRef, useState } from 'react';
import type { Order } from '@/components/OrderForm';
import { useOrderQueue } from '@/hooks/useOrderQueue';
import { canonicalSignature, generateOrderIdempotencyKey, clearOrderIdempotencyKey } from '@/lib/offline/idempotency';
import { invalidateOutletOrdersCache } from '@/app/dashboard/api';

type Submit = (token: string, items: Array<{ product_id: number; quantity: number }>, key: string) => Promise<Order>;

export function useOrderSubmission(token: string, isOnline: boolean, isDummy: boolean, submit: Submit) {
  const [cart, setCart] = useState<Record<number, number>>({});
  const [discoveryPrices, setDiscoveryPrices] = useState<Record<number, string>>({});
  const [order, setOrder] = useState<Order | null>(null);
  const [trackingId, setTrackingId] = useState('');
  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [reorderRefreshKey, setReorderRefreshKey] = useState(0);
  const [discoveryRefreshKey, setDiscoveryRefreshKey] = useState(0);
  const { queueRef, pendingCount, setPendingCount } = useOrderQueue(token, isOnline, isDummy);
  const attempt = useRef<{ signature: string; key: string } | null>(null);
  const releaseKey = () => {
    if (attempt.current) {
      clearOrderIdempotencyKey(attempt.current.signature);
      attempt.current = null;
    }
  };

  function addDiscoveryItem(productId: number, price: string) {
    setDiscoveryPrices((current) => ({ ...current, [productId]: price }));
  }

  async function submitOrder() {
    const items = Object.entries(cart).filter(([, quantity]) => quantity > 0).map(([product_id, quantity]) => ({ product_id: Number(product_id), quantity }));
    const discoveryItems = Object.keys(discoveryPrices).filter((id) => !cart[Number(id)]).map((id) => ({ product_id: Number(id), quantity: 1 }));
    items.push(...discoveryItems);
    if (!items.length) { setError('Pilih minimal satu produk.'); return; }
    setSubmitting(true); setError(null);
    try {
      const signature = canonicalSignature(items);
      if (!attempt.current || attempt.current.signature !== signature) {
        attempt.current = { signature, key: generateOrderIdempotencyKey(signature) };
      }
      const key = attempt.current.key;
      if (!isOnline && !isDummy && queueRef.current) {
        await queueRef.current.enqueue({ items, idempotency_key: key });
        setPendingCount((await queueRef.current.list()).length);
        releaseKey(); setCart({}); setDiscoveryPrices({}); setSubmitting(false);
        return;
      }
      const data = await submit(token, items, key);
      if (!isDummy) invalidateOutletOrdersCache(token);
      setReorderRefreshKey((k) => k + 1);
      setDiscoveryRefreshKey((k) => k + 1);
      setOrder(data); setTrackingId(String(data.id)); setCart({}); setDiscoveryPrices({}); releaseKey();
    } catch (reason) {
      const err = reason as Error & { errors?: Record<string, string[]> };
      const details = err.errors
        ? Object.entries(err.errors).map(([key, messages]) => `${key}: ${messages.join(', ')}`).join(' | ')
        : '';
      setError(details ? `${err.message} — ${details}` : (reason instanceof Error ? reason.message : 'Pesanan tidak dapat dibuat.'));
    } finally {
      setSubmitting(false);
    }
  }

  return { cart, setCart, order, trackingId, setTrackingId, submitting, error, setError,
    reorderRefreshKey, discoveryRefreshKey, pendingCount, submitOrder, discoveryCart: Object.fromEntries(Object.keys(discoveryPrices).map((id) => [Number(id), { price: discoveryPrices[Number(id)], quantity: 1 }])), addDiscoveryItem };
}

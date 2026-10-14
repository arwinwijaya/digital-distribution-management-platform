'use client';

import { useCallback, useEffect, useMemo, useRef, useState, type Dispatch, type SetStateAction } from 'react';
import { apiUrl, authHeaders } from '@/lib/api';
import type { Product } from '@/components/OrderForm';

export interface DiscoveryItem {
  product_id: number;
  product_name: string;
  reason: string;
  price?: string;
  stock_quantity?: number;
  is_active?: boolean;
  sku?: string;
  category?: string;
  /** False when the latest catalog shows the product inactive or out of stock. */
  available?: boolean;
}

export interface UseOutletDiscoveryOptions {
  online?: boolean;
  cart?: Record<number, number>;
  dismissed?: Set<number>;
  refreshKey?: number;
  onAdd?: (productId: number, price: string) => void;
  setCart?: Dispatch<SetStateAction<Record<number, number>>>;
}

export interface UseOutletDiscoveryResult {
  discovery: DiscoveryItem[];
  loading: boolean;
  error: string | null;
  addToCart: (productId: number) => void;
  dismiss: (productId: number) => void;
  retry: () => void;
}

/** Normalize a raw discovery API entry into a stable item shape. */
function normalizeItem(raw: Record<string, unknown>): DiscoveryItem {
  const item: DiscoveryItem = {
    product_id: Number(raw.product_id),
    product_name: String(raw.product_name ?? raw.name ?? ''),
    reason: String(raw.reason ?? ''),
    available: raw.available === undefined ? true : Boolean(raw.available),
  };
  if (raw.price !== undefined) item.price = String(raw.price);
  if (raw.stock_quantity !== undefined) item.stock_quantity = Number(raw.stock_quantity);
  if (raw.is_active !== undefined) item.is_active = Boolean(raw.is_active);
  if (raw.sku !== undefined && raw.sku !== null) item.sku = String(raw.sku);
  if (raw.category !== undefined && raw.category !== null) item.category = String(raw.category);
  return item;
}

/** Extract the item array from either the discovery or recommendations envelope. */
function extractItems(payload: unknown): DiscoveryItem[] {
  const root = payload as {
    discovery?: unknown;
    data?: { discovery?: unknown; recommendations?: unknown };
  } | null;
  const raw = root?.discovery ?? root?.data?.discovery ?? root?.data?.recommendations;
  if (!Array.isArray(raw)) return [];
  return raw.map((entry) => normalizeItem(entry as Record<string, unknown>));
}

function newEventUuid(): string {
  if (typeof crypto !== 'undefined' && typeof crypto.randomUUID === 'function') {
    return crypto.randomUUID();
  }
  return `${Date.now()}-${Math.random().toString(16).slice(2)}`;
}

/**
 * Outlet product discovery ("Coba produk lain").
 *
 * Fetches never-purchased, available products when online, deduplicates against
 * the active cart and session dismissals, adds to cart using the latest catalog
 * price/stock, and emits fire-and-forget funnel events.
 */
export function useOutletDiscovery(
  token: string,
  products: Product[],
  options: UseOutletDiscoveryOptions = {},
): UseOutletDiscoveryResult {
  const { online = true, refreshKey = 0, onAdd, setCart, dismissed, cart } = options;

  const [items, setItems] = useState<DiscoveryItem[]>([]);
  const [localDismissed, setLocalDismissed] = useState<Set<number>>(() => new Set());
  const [retryNonce, setRetryNonce] = useState(0);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const displayedRef = useRef<Set<number>>(new Set());

  const sendEvent = useCallback(
    (eventType: 'displayed' | 'clicked' | 'cart', productId: number) => {
      if (!token) return;
      fetch(apiUrl('/ai/discovery/events'), {
        method: 'POST',
        headers: authHeaders(token),
        body: JSON.stringify({
          event_uuid: newEventUuid(),
          event_type: eventType,
          product_id: productId,
          occurred_at: new Date().toISOString(),
        }),
      }).catch((err: unknown) => {
        // Fire-and-forget: never block ordering on a tracking failure.
        console.warn('Failed to send discovery event:', err);
      });
    },
    [token],
  );

  // Enrich discovery entries with the latest catalog price/stock/active state,
  // and drop items already in the cart or dismissed this session.
  const discovery = useMemo(
    () =>
      items
        .filter((item) => !localDismissed.has(item.product_id) && !dismissed?.has(item.product_id))
        .filter((item) => !cart?.[item.product_id])
        .map((item) => {
          const product = products.find((candidate) => candidate.id === item.product_id);
          if (!product) return item;
          return {
            ...item,
            product_name: product.name || item.product_name,
            price: product.price,
            stock_quantity: product.stock_quantity,
            is_active: product.is_active,
            available: product.is_active && product.stock_quantity > 0,
          };
        }),
    [items, products, localDismissed, dismissed, cart],
  );

  useEffect(() => {
    if (!token || !online) {
      setItems([]);
      setLoading(false);
      setError(null);
      return;
    }

    let cancelled = false;
    setLoading(true);
    setError(null);

    fetch(apiUrl('/ai/discovery'), { headers: authHeaders(token) })
      .then(async (response) => {
        if (!response.ok) throw new Error('Gagal memuat rekomendasi.');
        const payload = await response.json();
        if (cancelled) return;
        setItems(extractItems(payload));
      })
      .catch((reason: unknown) => {
        if (cancelled) return;
        setItems([]);
        setError(reason instanceof Error ? reason.message : 'Gagal memuat rekomendasi.');
      })
      .finally(() => {
        if (cancelled) return;
        setLoading(false);
      });

    return () => {
      cancelled = true;
    };
  }, [token, online, refreshKey, retryNonce]);

  // Emit a single `displayed` event per item per session, for items actually shown.
  useEffect(() => {
    if (!online) return;
    for (const item of discovery) {
      if (displayedRef.current.has(item.product_id)) continue;
      displayedRef.current.add(item.product_id);
      sendEvent('displayed', item.product_id);
    }
  }, [discovery, online, sendEvent]);

  const addToCart = useCallback(
    (productId: number) => {
      const product = products.find((candidate) => candidate.id === productId);
      const item = items.find((candidate) => candidate.product_id === productId);
      const active = product?.is_active ?? item?.is_active ?? true;
      const stock = product?.stock_quantity ?? item?.stock_quantity ?? 0;
      if (!active || stock <= 0) return;
      setCart?.((current) => ({ ...current, [productId]: (current[productId] || 0) + 1 }));
      sendEvent('clicked', productId);
      sendEvent('cart', productId);
      const price = product?.price ?? item?.price ?? '0';
      onAdd?.(productId, price);
    },
    [products, items, onAdd, setCart, sendEvent],
  );

  const dismiss = useCallback(
    (productId: number) => {
      dismissed?.add(productId);
      setLocalDismissed((current) => new Set(current).add(productId));
    },
    [dismissed],
  );

  const retry = useCallback(() => {
    displayedRef.current = new Set();
    setRetryNonce((current) => current + 1);
  }, []);

  return { discovery, loading, error, addToCart, dismiss, retry };
}

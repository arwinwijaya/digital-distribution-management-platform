'use client';

import { useEffect, useMemo, useState, type Dispatch, type SetStateAction } from 'react';
import { fetchOutletDashboard, loadOutletCachedSections } from '@/app/dashboard/api';
import type { Product } from '@/components/OrderForm';

export interface ReorderItem {
  product_id: number;
  display_name: string;
  last_qty: number;
  last_purchased_at: string;
  current_price: string;
  stock_quantity: number;
  is_active: boolean;
  credit_exceeded: boolean;
  source: 'favorite' | 'recent' | 'both';
}

export interface LastOrderItem {
  product_id: number;
  product_name: string;
  quantity: number;
  unit_price: string;
  subtotal: string;
}

export interface SkippedReorderItem {
  product_id: number;
  product_name: string;
  reason: string;
}

export interface UseOutletReorderResult {
  reorderItems: ReorderItem[];
  lastOrderItems: LastOrderItem[];
  loading: boolean;
  error: string | null;
  availableCredit: number | null;
  addToCart: (productId: number, lastQty: number) => void;
  reorderLastOrder: () => SkippedReorderItem[];
}

export interface UseOutletReorderOptions {
  cart?: Record<number, number>;
  setCart?: Dispatch<SetStateAction<Record<number, number>>>;
  /** Bump to force a reload after cache invalidation (T2/T4 seam). */
  refreshKey?: number;
}

const RECENT_ORDER_LIMIT = 3;

/**
 * Client-side reorder engine. Loads outlet order history from the existing
 * `outletOrdersCache` (via `loadOutletCachedSections`), fetching once via
 * `fetchOutletDashboard` on a cache miss, then composes the reorder list from
 * top-5 favorites + unique items across the 3 most recent orders, enriched
 * with the current catalog passed in by the caller.
 *
 * NOTE: This hook never calls `/products` or `loadOrderFormProducts`; the
 * caller passes the already-loaded catalog as the `products` parameter.
 */
export function useOutletReorder(
  token: string,
  products: Product[],
  options: UseOutletReorderOptions = {},
): UseOutletReorderResult {
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [favorites, setFavorites] = useState<Array<{ product_id: number; display_name: string; total_qty: number }>>([]);
  const [orders, setOrders] = useState<Array<{
    id: number;
    order_id: string;
    status: string;
    created_at: string;
    items: Array<{ product_id: number; product_name: string; quantity: number; unit_price: string; subtotal: string }>;
  }>>([]);
  const [availableCredit, setAvailableCredit] = useState<number | null>(null);

  const { cart = {}, setCart } = options;

  useEffect(() => {
    if (!token) {
      setFavorites([]);
      setOrders([]);
      setAvailableCredit(null);
      setLoading(false);
      return;
    }
    let cancelled = false;

    async function load() {
      setLoading(true);
      setError(null);
      try {
        // R6: prefer cache; only fetch once when the cache is empty.
        let sections = loadOutletCachedSections(token, 30);
        let credit: number | null = null;

        if (!sections) {
          const dashboard = await fetchOutletDashboard(token);
          if (cancelled) return;
          if (dashboard.kind === 'outlet') {
            const available = dashboard.data.credit.available_credit;
            credit = available === null ? null : Number(available);
          }
          // fetchOutletDashboard populated the module-level cache; re-read it.
          sections = loadOutletCachedSections(token, 30);
        }

        if (cancelled) return;
        setFavorites(sections?.favorites ?? []);
        setOrders((sections?.orders ?? []).slice(0, RECENT_ORDER_LIMIT));
        setAvailableCredit(credit);
        setLoading(false);
      } catch (e) {
        if (cancelled) return;
        setError(e instanceof Error ? e.message : 'Gagal memuat reorder.');
        setLoading(false);
      }
    }

    load();
    return () => {
      // Cleanup on token change / unmount.
      cancelled = true;
    };
  }, [token, options.refreshKey]);

  // R1: favorites (top-5) first, then unique items from the 3 most recent orders.
  // R5: enrich from the current catalog (`products` param); historical price is ignored.
  const reorderItems = useMemo((): ReorderItem[] => {
    if (!favorites.length && !orders.length) return [];

    const productById = new Map(products.map((product) => [product.id, product]));

    // Last quantity/date per product, taken from the most recent occurrence.
    const recentMeta = new Map<number, { qty: number; purchasedAt: string }>();
    for (const order of orders) {
      for (const item of order.items) {
        if (!recentMeta.has(item.product_id)) {
          recentMeta.set(item.product_id, { qty: item.quantity, purchasedAt: order.created_at });
        }
      }
    }

    const composed: ReorderItem[] = [];
    const seen = new Set<number>();

    for (const favorite of favorites) {
      seen.add(favorite.product_id);
      const meta = recentMeta.get(favorite.product_id);
      composed.push({
        product_id: favorite.product_id,
        display_name: favorite.display_name,
        last_qty: meta?.qty ?? 0,
        last_purchased_at: meta?.purchasedAt ?? '',
        current_price: '0',
        stock_quantity: 0,
        is_active: true,
        credit_exceeded: false,
        source: meta ? 'both' : 'favorite',
      });
    }

    for (const order of orders) {
      for (const item of order.items) {
        if (seen.has(item.product_id)) continue;
        seen.add(item.product_id);
        const meta = recentMeta.get(item.product_id);
        composed.push({
          product_id: item.product_id,
          display_name: item.product_name,
          last_qty: meta?.qty ?? item.quantity,
          last_purchased_at: meta?.purchasedAt ?? order.created_at,
          current_price: '0',
          stock_quantity: 0,
          is_active: true,
          credit_exceeded: false,
          source: 'recent',
        });
      }
    }

    // R5 + R8: enrich with current catalog data; drop items that are missing
    // from the catalog or inactive. Current price always wins over history.
    const enriched: ReorderItem[] = [];
    for (const item of composed) {
      const product = productById.get(item.product_id);
      if (!product || !product.is_active) continue;
      const unitPrice = Number(product.price);
      const creditExceeded = availableCredit !== null
        && Number.isFinite(unitPrice)
        && unitPrice * item.last_qty > availableCredit;
      enriched.push({
        ...item,
        display_name: product.name || item.display_name,
        current_price: product.price,
        stock_quantity: product.stock_quantity,
        is_active: product.is_active,
        credit_exceeded: creditExceeded,
      });
    }

    return enriched;
  }, [favorites, orders, products, availableCredit]);

  // Items of the single most recent order, for "Ulangi Pesanan Terakhir".
  const lastOrderItems = useMemo((): LastOrderItem[] => {
    const firstOrder = orders[0];
    if (!firstOrder) return [];
    return firstOrder.items.map((item) => ({
      product_id: item.product_id,
      product_name: item.product_name,
      quantity: item.quantity,
      unit_price: item.unit_price,
      subtotal: item.subtotal,
    }));
  }, [orders]);

  const addToCart = (productId: number, lastQty: number) => {
    if (!setCart || lastQty <= 0) return;
    setCart((current) => ({
      ...current,
      [productId]: (current[productId] || 0) + lastQty,
    }));
  };

  const reorderLastOrder = (): SkippedReorderItem[] => {
    const skipped: SkippedReorderItem[] = [];
    const byId = new Map(reorderItems.map((item) => [item.product_id, item]));

    for (const item of lastOrderItems) {
      const reorder = byId.get(item.product_id);
      if (!reorder) {
        skipped.push({ product_id: item.product_id, product_name: item.product_name, reason: 'Produk tidak aktif' });
        continue;
      }
      if (reorder.stock_quantity <= 0) {
        skipped.push({ product_id: item.product_id, product_name: item.product_name, reason: 'Stok habis' });
        continue;
      }
      if (reorder.credit_exceeded) {
        skipped.push({ product_id: item.product_id, product_name: item.product_name, reason: 'Melebihi batas kredit' });
        continue;
      }
      addToCart(item.product_id, item.quantity);
    }

    return skipped;
  };

  return {
    reorderItems,
    lastOrderItems,
    loading,
    error,
    availableCredit,
    addToCart,
    reorderLastOrder,
  };
}

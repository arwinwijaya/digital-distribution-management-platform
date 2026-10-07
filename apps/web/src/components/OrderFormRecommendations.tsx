'use client';

import { useRef } from 'react';
import DiscoverySection from '@/components/DiscoverySection';
import ReorderSection from '@/components/ReorderSection';
import { useOutletDiscovery } from '@/hooks/useOutletDiscovery';
import { useOutletReorder } from '@/hooks/useOutletReorder';
import type { Dispatch, ReactNode, SetStateAction } from 'react';
import type { Product } from '@/components/OrderForm';

interface Props {
  token: string;
  products: Product[];
  cart: Record<number, number>;
  setCart: Dispatch<SetStateAction<Record<number, number>>>;
  isOnline: boolean;
  isDummy: boolean;
  reorderRefreshKey: number;
  discoveryRefreshKey: number;
}

/** The recommendation slots live together, directly above the catalog in the same section. */
export default function OrderFormRecommendations({ token, products, cart, setCart, isOnline, isDummy, reorderRefreshKey, discoveryRefreshKey }: Props): ReactNode {
  const dismissed = useRef<Set<number>>(new Set());
  const reorderEnabled = process.env.NEXT_PUBLIC_ENABLE_REORDER !== 'false';
  const discoveryEnabled = process.env.NEXT_PUBLIC_ENABLE_DISCOVERY !== 'false';
  const { reorderItems, lastOrderItems, addToCart: reorderAddToCart, reorderLastOrder } = useOutletReorder(token, products, {
    cart, setCart, refreshKey: reorderRefreshKey,
  });
  const showReorderSection = reorderEnabled && !isDummy && (reorderItems.length > 0 || lastOrderItems.length > 0);
  const showReorderHint = reorderEnabled && !isDummy && reorderItems.length === 0 && lastOrderItems.length === 0;
  const showDiscoverySection = discoveryEnabled && !isDummy;
  const offline = !isOnline;
  const discoveryOptions = {
    online: !offline && showDiscoverySection,
    cart, dismissed: dismissed.current, refreshKey: discoveryRefreshKey, setCart,
  };
  const { discovery, error, addToCart, dismiss, retry } = useOutletDiscovery(token, products, discoveryOptions);

  return <>
    {showReorderSection && (
      <div className="mb-6">
        <ReorderSection items={reorderItems} offline={offline} onAdd={reorderAddToCart} onReorderLastOrder={reorderLastOrder} />
      </div>
    )}
    {showReorderHint && (
      <p className="mb-6 rounded-lg border border-gray-200 bg-gray-50 p-3 text-sm text-gray-600">
        Belum ada riwayat pesanan — pilih dari katalog di bawah untuk pesanan pertama Anda.
      </p>
    )}
    {showDiscoverySection && (
      <div className="mb-6">
        <DiscoverySection items={discovery} onAdd={addToCart} onDismiss={dismiss} offline={offline} error={error} onRetry={retry} />
      </div>
    )}
  </>;
}

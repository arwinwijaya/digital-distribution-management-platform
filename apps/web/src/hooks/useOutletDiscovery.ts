'use client';

import { useState } from 'react';
import type { Product } from '@/components/OrderForm';

export interface DiscoveryItem {
  product_id: number;
  product_name: string;
  reason: string;
}

export interface UseOutletDiscoveryResult {
  discovery: DiscoveryItem[];
  loading: boolean;
}

/** Scaffold for outlet product discovery. */
export function useOutletDiscovery(
  _token: string,
  _products: Product[],
  _online: boolean,
): UseOutletDiscoveryResult {
  const [discovery] = useState<DiscoveryItem[]>([]);
  return { discovery, loading: false };
}

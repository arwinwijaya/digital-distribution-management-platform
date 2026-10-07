'use client';

import { useState } from 'react';
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
}

export interface UseOutletDiscoveryOptions {
  online?: boolean;
  cart?: Record<number, number>;
  dismissed?: Set<number>;
  refreshKey?: number;
}

export interface UseOutletDiscoveryResult {
  discovery: DiscoveryItem[];
  loading: boolean;
}

/** Scaffold for outlet product discovery. */
export function useOutletDiscovery(
  _token: string,
  _products: Product[],
  _options: UseOutletDiscoveryOptions = {},
): UseOutletDiscoveryResult {
  const [discovery] = useState<DiscoveryItem[]>([]);
  return { discovery, loading: false };
}

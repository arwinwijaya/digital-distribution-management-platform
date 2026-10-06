'use client';

import type { DiscoveryItem } from '@/hooks/useOutletDiscovery';

export interface DiscoverySectionProps {
  items: DiscoveryItem[];
  onAdd: (productId: number, qty: number) => void;
}

/** Scaffold DiscoverySection component. */
export default function DiscoverySection(_props: DiscoverySectionProps) {
  return null;
}

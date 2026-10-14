'use client';

import { Button, Card } from '@/components/ui';
import type { DiscoveryItem } from '@/hooks/useOutletDiscovery';

export interface DiscoverySectionProps {
  items: DiscoveryItem[];
  onAdd: (productId: number, qty: number) => void;
  onDismiss?: (productId: number) => void;
  /** True while the outlet is online — offline hides the list (T7). */
  isOnline?: boolean;
  /** Explicit offline flag; equivalent to `isOnline={false}`. */
  offline?: boolean;
  /** Non-blocking fetch error message; shows a retry notice (T7). */
  error?: string | null;
  onRetry?: () => void;
}

/** T7: guard reason for a discovery item, or null when addable. */
export function disabledReason(item: DiscoveryItem): string | null {
  if (item.is_active === false) return 'Produk tidak aktif';
  if ((item.stock_quantity ?? 0) <= 0) return 'Stok habis';
  return null;
}

/** Accessibility: describe the action and guard status for screen readers. */
export function addButtonAriaLabel(item: DiscoveryItem): string {
  const reason = disabledReason(item);
  if (reason) {
    return `Tambah ${item.product_name} — ${reason} — tidak dapat ditambahkan`;
  }
  return `Tambah ${item.product_name} — Stok: ${item.stock_quantity ?? 0}`;
}

const OFFLINE_MESSAGE = 'Rekomendasi produk baru tersedia saat online.';
const EMPTY_MESSAGE = 'Belum ada rekomendasi baru saat ini.';
const ERROR_MESSAGE = 'Gagal memuat rekomendasi. Coba lagi nanti.';

/**
 * "Coba produk lain" discovery section (T7).
 * Purely presentational over `useOutletDiscovery`; the caller owns the cart,
 * dismiss state, feature-flag guard, and online detection. Native <button>
 * elements keep keyboard semantics intact, mirroring ReorderSection.
 */
export default function DiscoverySection({
  items,
  onAdd,
  onDismiss,
  isOnline = true,
  offline = false,
  error = null,
  onRetry,
}: DiscoverySectionProps) {
  const isOffline = offline || !isOnline;

  return (
    <Card className="p-5">
      <div className="mb-3 flex flex-wrap items-center justify-between gap-3">
        <h2 className="text-base font-semibold text-gray-900">Coba produk lain</h2>
      </div>
      {error ? (
        <div
          role="alert"
          className="rounded-lg border border-danger-200 bg-danger-50 p-3 text-sm text-danger-700"
        >
          <p>{ERROR_MESSAGE}</p>
          <Button size="sm" variant="secondary" className="mt-2" onClick={() => onRetry?.()}>
            Coba lagi
          </Button>
        </div>
      ) : isOffline ? (
        <p className="text-sm text-gray-500">{OFFLINE_MESSAGE}</p>
      ) : items.length === 0 ? (
        <p className="text-sm text-gray-500">{EMPTY_MESSAGE}</p>
      ) : (
        <ul className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
          {items.map((item) => {
            const reason = disabledReason(item);
            return (
              <li key={item.product_id}>
                <Card className="p-4">
                  <div className="flex items-start justify-between gap-2">
                    <h3 className="font-semibold text-gray-900">{item.product_name}</h3>
                    <Button
                      size="sm"
                      variant="ghost"
                      aria-label={`Abaikan ${item.product_name}`}
                      onClick={() => onDismiss?.(item.product_id)}
                    >
                      ×
                    </Button>
                  </div>
                  <p className="mt-1 text-sm font-bold text-primary-700">
                    Rp {Number(item.price ?? 0).toLocaleString('id-ID')}
                  </p>
                  <p className="mt-1 text-xs text-gray-500">Stok {item.stock_quantity ?? 0}</p>
                  <p className="mt-1 text-xs text-gray-400">{item.reason}</p>
                  {reason && <p className="mt-2 text-xs font-medium text-danger-600">{reason}</p>}
                  <Button
                    size="sm"
                    variant="primary"
                    className="mt-3"
                    disabled={!!reason}
                    aria-label={addButtonAriaLabel(item)}
                    onClick={() => onAdd(item.product_id, 1)}
                  >
                    + Tambah
                  </Button>
                </Card>
              </li>
            );
          })}
        </ul>
      )}
    </Card>
  );
}

'use client';

import { useState } from 'react';
import { Button, Card } from '@/components/ui';
import type { ReorderItem, SkippedReorderItem } from '@/hooks/useOutletReorder';

export interface ReorderSectionProps {
  items: ReorderItem[];
  /** True while the outlet is offline — enables the cache-only notice. */
  offline: boolean;
  onAdd: (productId: number, lastQty: number) => void;
  onReorderLastOrder: () => SkippedReorderItem[];
}

/** R4/R8: client-side guard reason for a reorder item, or null when addable. */
export function disabledReason(item: ReorderItem): string | null {
  if (!item.is_active) return 'Produk tidak aktif';
  if (item.stock_quantity <= 0) return 'Stok habis';
  if (item.credit_exceeded) return 'Melebihi batas kredit';
  return null;
}

/** Accessibility: describe the action and the guard status for screen readers. */
export function addButtonAriaLabel(item: ReorderItem): string {
  const reason = disabledReason(item);
  if (reason) {
    return `Tambah ${item.display_name}, qty ${item.last_qty} — ${reason} — tidak dapat ditambahkan`;
  }
  return `Tambah ${item.display_name}, qty ${item.last_qty} — Stok: ${item.stock_quantity}`;
}

const OFFLINE_NOTICE = 'Ditambahkan berdasarkan data terakhir — validasi final saat online.';

/**
 * "Pesan Lagi" reorder section (R1–R4, R7, R8, accessibility, offline notice).
 * Purely presentational over `useOutletReorder`; the caller owns the cart and
 * feature-flag guard. Native <button> elements keep keyboard semantics intact.
 */
export default function ReorderSection({ items, offline, onAdd, onReorderLastOrder }: ReorderSectionProps) {
  const [notice, setNotice] = useState<string | null>(null);

  const handleAdd = (item: ReorderItem) => {
    if (disabledReason(item)) return;
    onAdd(item.product_id, item.last_qty);
    setNotice(offline ? OFFLINE_NOTICE : null);
  };

  const handleReorderLastOrder = () => {
    const skipped = onReorderLastOrder();
    const parts: string[] = [];
    if (skipped.length) {
      parts.push(skipped.map((s) => `${s.product_name} tidak ditambahkan: ${s.reason}`).join('; '));
    }
    if (offline) parts.push(OFFLINE_NOTICE);
    setNotice(parts.length ? parts.join(' ') : null);
  };

  return (
    <Card className="p-5">
      <div className="mb-3 flex flex-wrap items-center justify-between gap-3">
        <h2 className="text-base font-semibold text-gray-900">Pesan Lagi</h2>
        <Button
          variant="secondary"
          size="sm"
          onClick={handleReorderLastOrder}
          aria-label="Ulangi Pesanan Terakhir"
        >
          ↻ Ulangi Pesanan Terakhir
        </Button>
      </div>
      {notice && (
        <p
          role="alert"
          data-testid="reorder-notice"
          className="mb-3 rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-700"
        >
          {notice}
        </p>
      )}
      <ul className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
        {items.map((item) => {
          const reason = disabledReason(item);
          return (
            <li key={item.product_id}>
              <Card className="p-4">
                <h3 className="font-semibold text-gray-900">{item.display_name}</h3>
                <p className="mt-1 text-sm font-bold text-primary-700">
                  Rp {Number(item.current_price).toLocaleString('id-ID')}
                </p>
                <p className="mt-1 text-xs text-gray-500">
                  Stok {item.stock_quantity} · Qty terakhir {item.last_qty}
                </p>
                {item.last_purchased_at && (
                  <p className="text-xs text-gray-400">
                    Terakhir {new Date(item.last_purchased_at).toLocaleDateString('id-ID')}
                  </p>
                )}
                {reason && <p className="mt-2 text-xs font-medium text-danger-600">{reason}</p>}
                <Button
                  size="sm"
                  variant="primary"
                  className="mt-3"
                  disabled={!!reason}
                  aria-label={addButtonAriaLabel(item)}
                  onClick={() => handleAdd(item)}
                >
                  + Tambah
                </Button>
              </Card>
            </li>
          );
        })}
      </ul>
    </Card>
  );
}

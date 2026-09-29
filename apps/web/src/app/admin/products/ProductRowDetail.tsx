'use client';

import type { ReactNode } from 'react';
import { Button, Card } from '@/components/ui';
import { formatDateTime } from '@/lib/admin-table';
import {
  STOCK_UNIT_NOTE,
  STOCK_VALUE_LABEL,
  categoryDisplay,
  deriveProductDetailFacts,
  normalizeStock,
} from './product-clarity';
import type { AdminProduct, PriceHistoryResult } from './api';
import PriceHistoryPanel, { type PriceHistoryFetchParams } from './PriceHistoryPanel';

/** Shared tooltip/label copy: what the `price` value actually represents. */
export const PRICE_TOOLTIP = 'Harga jual yang digunakan dalam order';

export type ProductRowDetailProps = {
  product: AdminProduct;
  /** Stable DOM id for the panel; also the row trigger's `aria-controls` target. */
  detailId?: string;
  /** Optional price-history seam: bound to this product's id by the page. */
  historyFetch?: (params: PriceHistoryFetchParams) => Promise<PriceHistoryResult>;
  /** Called when the price-history panel detects a deleted product (404). */
  onClose?: () => void;
};

/** Label/value pair used by the fact grid below. */
function Fact({ label, value }: { label: string; value: ReactNode }) {
  return (
    <div className="flex flex-col gap-0.5">
      <span className="text-xs font-semibold uppercase tracking-wide text-gray-500">{label}</span>
      <span className="text-sm text-gray-700">{value}</span>
    </div>
  );
}

/**
 * Products-local row trigger: the product name control that opens/closes the
 * detail panel. Keyboard toggle (Enter/Space) plus click; `aria-expanded`
 * mirrors state and `aria-controls` always points at the detail panel id.
 */
export function ProductRowTrigger({
  product,
  detailId,
  expanded,
  onToggle,
}: {
  product: AdminProduct;
  detailId: string;
  expanded: boolean;
  onToggle: () => void;
}) {
  return (
    <button
      type="button"
      aria-expanded={expanded}
      aria-controls={detailId}
      onClick={onToggle}
      onKeyDown={(event) => {
        if (event.key === 'Enter' || event.key === ' ') {
          event.preventDefault();
          onToggle();
        }
      }}
      className="text-left font-medium text-primary-700 hover:underline"
    >
      {product.name}
    </button>
  );
}

/**
 * Products-local independent price action. Lives in its own cell (never
 * inside the row trigger), so activation never toggles row expansion.
 */
export function ProductRowPriceAction({
  product,
  onPrice,
}: {
  product: AdminProduct;
  onPrice: (product: AdminProduct) => void;
}) {
  return (
    <Button
      size="sm"
      variant="secondary"
      type="button"
      onClick={(event) => {
        event.stopPropagation();
        onPrice(product);
      }}
    >
      Ubah harga
    </Button>
  );
}

/**
 * Products-local expanded row detail: complete identity, supplier + product
 * status facts, price context, normalized stock value with the static unit
 * note, and timestamps. Pure display — all status/stock derivation comes from
 * the T2 `product-clarity` helpers (never re-derived here).
 */
export default function ProductRowDetail({ product, detailId, historyFetch, onClose }: ProductRowDetailProps) {
  const facts = deriveProductDetailFacts(product);
  const stock = normalizeStock(product.stock_quantity);
  const price = Number(product.price ?? 0);
  const stockValue = Math.round(price * stock);

  return (
    <div id={detailId}>
      <Card className="p-5">
        <div className="flex flex-wrap items-center gap-3">
          <h3 className="text-sm font-semibold text-gray-900">{product.name}</h3>
          <span className="text-xs text-gray-500">{facts.status}</span>
        </div>

        <div className="mt-4 grid gap-4 sm:grid-cols-3">
          <Fact label="SKU" value={product.sku ?? '\u2014'} />
          <Fact label="Kategori" value={categoryDisplay(product.category)} />
          <Fact label="Deskripsi" value={product.description?.trim() || '\u2014'} />

          <Fact label="Supplier" value={facts.supplierName} />
          <Fact label="Status supplier" value={facts.supplierStatus} />
          <Fact label="Status produk" value={facts.productActive ? 'Aktif' : 'Nonaktif'} />

          <Fact label="Harga jual" value={<span title={PRICE_TOOLTIP}>Rp {price.toLocaleString('id-ID')}</span>} />
          <Fact label={STOCK_VALUE_LABEL} value={`Rp ${stockValue.toLocaleString('id-ID')}`} />
          <Fact label="Satuan" value={STOCK_UNIT_NOTE} />

          <Fact label="Dibuat" value={formatDateTime(product.created_at ?? null)} />
          <Fact label="Diperbarui" value={formatDateTime(product.updated_at ?? null)} />
        </div>

        {historyFetch ? (
          <div className="mt-4 border-t border-gray-100 pt-4">
            <h4 className="text-xs font-semibold uppercase tracking-wide text-gray-500">Riwayat harga</h4>
            <PriceHistoryPanel fetchHistory={historyFetch} onClose={onClose} />
          </div>
        ) : null}
      </Card>
    </div>
  );
}

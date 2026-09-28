'use client';

import React from 'react';
import type { GeographicMapPoint } from '@/lib/data-intelligence-api';
import {
  computeFilteredCounts,
  periodToRange,
  type SnapshotWindow,
} from '@/lib/geographic-filters';

export interface DrawerFilter {
  statuses: string[];
  period: 'today' | '7d' | '30d';
}

interface OutletDrawerProps {
  point: GeographicMapPoint;
  openingFilter: DrawerFilter;
  currentFilter: DrawerFilter;
  window: SnapshotWindow;
  onClose: () => void;
}

/** Build the additive order-list link; every value is URL-encoded by URLSearchParams. */
export function buildOrderLink(
  point: GeographicMapPoint,
  filter: DrawerFilter,
  window: SnapshotWindow,
): string {
  const range = periodToRange(window, filter.period);
  const params = new URLSearchParams();
  params.set('outlet_id', String(point.outlet_id));
  params.set('status', filter.statuses.join(','));
  params.set('start', range.start);
  params.set('end', range.end);
  return `/admin/orders?${params.toString()}`;
}

/** Format an integer-cents amount as a fixed two-decimal string. */
export function formatCents(cents: number): string {
  const sign = cents < 0 ? '-' : '';
  const abs = Math.abs(Math.round(cents));
  const whole = Math.floor(abs / 100);
  const fraction = abs % 100;
  return `${sign}${whole}.${String(fraction).padStart(2, '0')}`;
}

function toCents(value: string | undefined): number {
  if (value === undefined) return 0;
  const numeric = Number(value);
  return Number.isFinite(numeric) ? Math.round(numeric * 100) : 0;
}

interface DailyRow {
  date: string;
  count: number;
}

/** Per-date filtered counts for the selected statuses within the opening period range. */
function dailyRows(point: GeographicMapPoint, filter: DrawerFilter, window: SnapshotWindow): DailyRow[] {
  if (!Array.isArray(point.daily_by_status)) return [];
  const range = periodToRange(window, filter.period);
  return point.daily_by_status
    .filter((bucket) => bucket.date >= range.start && bucket.date <= range.end)
    .map((bucket) => ({
      date: bucket.date,
      count: filter.statuses.reduce((sum, status) => sum + (bucket.counts?.[status] ?? 0), 0),
    }));
}

export default function OutletDrawer({
  point,
  openingFilter,
  currentFilter,
  window,
  onClose,
}: OutletDrawerProps) {
  const counts = computeFilteredCounts(point, openingFilter.statuses, openingFilter.period, window);
  const daily = dailyRows(point, openingFilter, window);
  const products = Array.isArray(point.product_summary) ? point.product_summary : [];
  const legacyOnly = counts.legacyOnly;
  const hasDailyDetail = counts.hasDailyDetail;
  const narrowPeriod = openingFilter.period !== '30d';

  const productLabel = point.product_summary_truncated
    ? 'Top 5 produk'
    : narrowPeriod
      ? 'Ringkasan produk — snapshot-window'
      : 'Ringkasan produk';

  return (
    <div data-testid="outlet-drawer" role="dialog" aria-modal="true" aria-label={point.outlet_name}>
      <h2>{point.outlet_name}</h2>
      <button type="button" onClick={onClose} aria-label="Tutup detail">
        ✕
      </button>

      <dl>
        <dt>Territory</dt>
        <dd>{point.territory}</dd>
        {legacyOnly ? (
          <>
            <dt>Pesanan (window penuh)</dt>
            <dd>{point.orders}</dd>
          </>
        ) : (
          <>
            <dt>Filtered orders</dt>
            <dd>{counts.filteredOrders}</dd>
          </>
        )}
      </dl>

      {legacyOnly ? (
        <p>Rincian status tidak tersedia</p>
      ) : (
        <section aria-label="Rincian status">
          <ul>
            {openingFilter.statuses.map((status) => (
              <li key={status}>{`${status}: ${counts.statusCounts[status] ?? 0}`}</li>
            ))}
          </ul>
        </section>
      )}

      {hasDailyDetail && (
        <section aria-label="Rincian harian">
          <ul>
            {daily.map((row) => (
              <li key={row.date}>{`${row.date}: ${row.count}`}</li>
            ))}
          </ul>
        </section>
      )}

      {point.latest_request && (
        <section aria-label="Request terbaru">
          <p>{`Order ${point.latest_request.order_id} — ${point.latest_request.status}`}</p>
        </section>
      )}

      <section aria-label="Ringkasan produk">
        <h3>{productLabel}</h3>
        {products.length > 0 ? (
          <ul>
            {products.map((product) => (
              <li key={product.product_id}>
                {`${product.product_name} — qty ${product.quantity} — ${formatCents(toCents(product.subtotal))}`}
              </li>
            ))}
          </ul>
        ) : (
          <p>Detail produk belum tersedia — jalankan pipeline data</p>
        )}
      </section>

      <a href={buildOrderLink(point, openingFilter, window)}>Lihat semua order</a>
    </div>
  );
}

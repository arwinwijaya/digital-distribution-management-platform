'use client';

import React, { useEffect, useRef } from 'react';
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

const FREEZE_BANNER = 'Filter berubah — tutup dan buka ulang untuk memuat data terbaru';
const FOCUSABLE_SELECTOR = 'button, a[href], input, select, textarea, [tabindex]:not([tabindex="-1"])';

/** Compare the status set (order-sensitive) and period of two filters. */
export function filtersEqual(a: DrawerFilter, b: DrawerFilter): boolean {
  if (a.period !== b.period) return false;
  const aStatuses = new Set(a.statuses);
  const bStatuses = new Set(b.statuses);
  if (aStatuses.size !== bStatuses.size) return false;
  return Array.from(aStatuses).every((status) => bStatuses.has(status));
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
  /* Snapshot-only: the point, opening filter, and window captured on first render are
     frozen for the drawer's lifetime. Later prop changes (map filter, refreshed point)
     never rewrite the drawer contents. */
  const snapshotRef = useRef<{ point: GeographicMapPoint; filter: DrawerFilter; window: SnapshotWindow } | null>(
    null,
  );
  if (snapshotRef.current === null) {
    snapshotRef.current = {
      /* Clone the opening inputs so parent filter/point updates cannot mutate the snapshot. */
      point: {
        ...point,
        orders_by_status: point.orders_by_status ? { ...point.orders_by_status } : point.orders_by_status,
        sales_by_status: point.sales_by_status ? { ...point.sales_by_status } : point.sales_by_status,
        daily_by_status: point.daily_by_status?.map((bucket) => ({
          ...bucket,
          counts: bucket.counts ? { ...bucket.counts } : bucket.counts,
          sales: bucket.sales ? { ...bucket.sales } : bucket.sales,
        })),
        product_summary: point.product_summary?.map((product) => ({ ...product })),
        latest_request: point.latest_request ? { ...point.latest_request } : point.latest_request,
      },
      filter: { statuses: [...openingFilter.statuses], period: openingFilter.period },
      window: { ...window },
    };
  }
  const snapshot = snapshotRef.current;

  const dialogRef = useRef<HTMLDivElement | null>(null);
  const triggerRef = useRef<HTMLElement | null>(null);

  const counts = computeFilteredCounts(
    snapshot.point,
    snapshot.filter.statuses,
    snapshot.filter.period,
    snapshot.window,
  );
  const daily = dailyRows(snapshot.point, snapshot.filter, snapshot.window);
  const products = Array.isArray(snapshot.point.product_summary) ? snapshot.point.product_summary : [];
  const legacyOnly = counts.legacyOnly;
  const hasDailyDetail = counts.hasDailyDetail;
  const narrowPeriod = snapshot.filter.period !== '30d';
  const filterChanged = !filtersEqual(snapshot.filter, currentFilter);

  const productLabel = snapshot.point.product_summary_truncated
    ? 'Top 5 produk'
    : narrowPeriod
      ? 'Ringkasan produk — snapshot-window'
      : 'Ringkasan produk';

  /* Focus management: remember the exact opening trigger, move focus inside on open,
     contain Tab within the dialog, and restore focus to the trigger on close. */
  useEffect(() => {
    const dialog = dialogRef.current;
    if (!dialog) return;

    const active = document.activeElement as HTMLElement | null;
    if (active && active !== dialog && !dialog.contains(active)) {
      triggerRef.current = active;
    }

    const focusable = Array.from(dialog.querySelectorAll<HTMLElement>(FOCUSABLE_SELECTOR));
    const first = focusable[0];
    const last = focusable[focusable.length - 1];

    const handleKeyDown = (event: KeyboardEvent) => {
      if (event.key === 'Escape') {
        event.preventDefault();
        onClose();
        return;
      }
      if (event.key !== 'Tab' || focusable.length === 0) return;
      if (event.shiftKey && document.activeElement === first) {
        event.preventDefault();
        last.focus();
      } else if (!event.shiftKey && document.activeElement === last) {
        event.preventDefault();
        first.focus();
      }
    };

    dialog.addEventListener('keydown', handleKeyDown);
    first?.focus();

    return () => {
      dialog.removeEventListener('keydown', handleKeyDown);
      triggerRef.current?.focus();
    };
    // Runs once per mounted drawer: the snapshot is frozen for its lifetime.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  return (
    <div
      ref={dialogRef}
      data-testid="outlet-drawer"
      role="dialog"
      aria-modal="true"
      aria-label={snapshot.point.outlet_name}
      onClick={(event) => {
        if (event.target === event.currentTarget) onClose();
      }}
    >
      <h2>{snapshot.point.outlet_name}</h2>
      <button type="button" onClick={onClose} aria-label="Tutup detail">
        ✕
      </button>

      {filterChanged && (
        <p role="alert" data-testid="outlet-drawer-freeze-banner">
          {FREEZE_BANNER}
        </p>
      )}

      <dl>
        <dt>Territory</dt>
        <dd>{snapshot.point.territory}</dd>
        {legacyOnly ? (
          <>
            <dt>Pesanan (window penuh)</dt>
            <dd>{snapshot.point.orders}</dd>
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
            {snapshot.filter.statuses.map((status) => (
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

      {snapshot.point.latest_request && (
        <section aria-label="Request terbaru">
          <p>{`Order ${snapshot.point.latest_request.order_id} — ${snapshot.point.latest_request.status}`}</p>
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

      <a href={buildOrderLink(snapshot.point, snapshot.filter, snapshot.window)}>Lihat semua order</a>
    </div>
  );
}

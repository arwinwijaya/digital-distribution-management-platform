'use client';

import React, { useEffect, useRef } from 'react';
import type { GeographicMapPoint } from '@/lib/data-intelligence-api';
import {
  computeFilteredCounts,
  periodToRange,
  type FilteredCounts,
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

/**
 * Single money helper for this drawer. `@/lib/geographic-filters` defines the same
 * cents convention but does not export it, so this is the one local helper; it is
 * kept aligned with that convention (parse to a number, round to integer cents).
 */
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

/** Deep-ish copy of the opening point so later prop updates cannot mutate the snapshot. */
function clonePoint(point: GeographicMapPoint): GeographicMapPoint {
  return {
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
  };
}

/**
 * Snapshot-only: the point, opening filter, and window captured on first render are
 * frozen for the drawer's lifetime. Later prop changes (map filter, refreshed point)
 * never rewrite the drawer contents.
 */
function useFrozenSnapshot(
  point: GeographicMapPoint,
  openingFilter: DrawerFilter,
  window: SnapshotWindow,
): { point: GeographicMapPoint; filter: DrawerFilter; window: SnapshotWindow } {
  const snapshotRef = useRef<{ point: GeographicMapPoint; filter: DrawerFilter; window: SnapshotWindow } | null>(
    null,
  );
  if (snapshotRef.current === null) {
    snapshotRef.current = {
      /* Clone the opening inputs so parent filter/point updates cannot mutate the snapshot. */
      point: clonePoint(point),
      filter: { statuses: [...openingFilter.statuses], period: openingFilter.period },
      window: { ...window },
    };
  }
  return snapshotRef.current;
}

/** Focus management: remember the exact opening trigger, move focus inside on open,
    contain Tab within the dialog, and restore focus to the trigger on close. */
function useFocusTrap(dialogRef: React.RefObject<HTMLDivElement | null>, onClose: () => void): void {
  const triggerRef = useRef<HTMLElement | null>(null);
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
}

function FreezeBanner(): React.JSX.Element {
  return (
    <p role="alert" data-testid="outlet-drawer-freeze-banner">
      {FREEZE_BANNER}
    </p>
  );
}

function TerritorySection({ point, counts }: { point: GeographicMapPoint; counts: FilteredCounts }): React.JSX.Element {
  return (
    <dl>
      <dt>Territory</dt>
      <dd>{point.territory}</dd>
      {counts.legacyOnly ? (
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
  );
}

function StatusSection({ statuses, counts }: { statuses: string[]; counts: FilteredCounts }): React.JSX.Element {
  if (counts.legacyOnly) return <p>Rincian status tidak tersedia</p>;
  return (
    <section aria-label="Rincian status">
      <ul>
        {statuses.map((status) => (
          <li key={status}>{`${status}: ${counts.statusCounts[status] ?? 0}`}</li>
        ))}
      </ul>
    </section>
  );
}

function DailySection({ daily, hasDailyDetail }: { daily: DailyRow[]; hasDailyDetail: boolean }): React.JSX.Element | null {
  if (!hasDailyDetail) return null;
  return (
    <section aria-label="Rincian harian">
      <ul>
        {daily.map((row) => (
          <li key={row.date}>{`${row.date}: ${row.count}`}</li>
        ))}
      </ul>
    </section>
  );
}

function LatestRequestSection({ request }: { request: GeographicMapPoint['latest_request'] }): React.JSX.Element | null {
  if (!request) return null;
  return (
    <section aria-label="Request terbaru">
      <p>{`Order ${request.order_id} — ${request.status}`}</p>
    </section>
  );
}

function ProductSection({
  label,
  products,
}: {
  label: string;
  products: NonNullable<GeographicMapPoint['product_summary']>;
}): React.JSX.Element {
  return (
    <section aria-label="Ringkasan produk">
      <h3>{label}</h3>
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
  );
}

function OrderLink({
  point,
  filter,
  window,
}: {
  point: GeographicMapPoint;
  filter: DrawerFilter;
  window: SnapshotWindow;
}): React.JSX.Element {
  return <a href={buildOrderLink(point, filter, window)}>Lihat semua order</a>;
}

function productLabel(point: GeographicMapPoint, filter: DrawerFilter): string {
  if (point.product_summary_truncated) return 'Top 5 produk';
  return filter.period !== '30d' ? 'Ringkasan produk — snapshot-window' : 'Ringkasan produk';
}

export default function OutletDrawer({
  point,
  openingFilter,
  currentFilter,
  window,
  onClose,
}: OutletDrawerProps) {
  const snapshot = useFrozenSnapshot(point, openingFilter, window);
  const dialogRef = useRef<HTMLDivElement | null>(null);
  useFocusTrap(dialogRef, onClose);

  const counts = computeFilteredCounts(
    snapshot.point,
    snapshot.filter.statuses,
    snapshot.filter.period,
    snapshot.window,
  );
  const daily = dailyRows(snapshot.point, snapshot.filter, snapshot.window);
  const products = Array.isArray(snapshot.point.product_summary) ? snapshot.point.product_summary : [];
  const filterChanged = !filtersEqual(snapshot.filter, currentFilter);

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

      {filterChanged && <FreezeBanner />}
      <TerritorySection point={snapshot.point} counts={counts} />
      <StatusSection statuses={snapshot.filter.statuses} counts={counts} />
      <DailySection daily={daily} hasDailyDetail={counts.hasDailyDetail} />
      <LatestRequestSection request={snapshot.point.latest_request} />
      <ProductSection label={productLabel(snapshot.point, snapshot.filter)} products={products} />
      <OrderLink point={snapshot.point} filter={snapshot.filter} window={snapshot.window} />
    </div>
  );
}

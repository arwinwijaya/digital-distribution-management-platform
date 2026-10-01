'use client';

import { useEffect, useRef, useState } from 'react';
import type { KeyboardEvent } from 'react';
import type { OutletDashboardData } from '@/dummy';
import { periodWindow, toOutletStatus } from './outlet-helpers';
import { Card, PageHeader, StatCard } from '@/components/ui';

type Section = 'orders' | 'shopping' | 'credit' | 'favorites';
type DashboardError = Error & { status?: number; inline?: boolean };
type Props = {
  data: OutletDashboardData | null;
  ordersError?: DashboardError | null;
  shoppingError?: DashboardError | null;
  creditError?: DashboardError | null;
  favoritesError?: DashboardError | null;
  onRetryOrders?: () => void;
  onRetryShopping?: () => void;
  onRetryCredit?: () => void;
  onRetryFavorites?: () => void;
  onPeriodChange?: (days: 7 | 30 | 90) => void;
};

function safeNumber(value: unknown): number {
  const parsed = Number(value);
  return Number.isFinite(parsed) ? parsed : 0;
}
function rupiah(value: unknown): string {
  return `Rp ${safeNumber(value).toLocaleString('id-ID', { maximumFractionDigits: 0 })}`;
}

function SectionError({ section, error, onRetry }: { section: Section; error: DashboardError; onRetry?: () => void }) {
  const button = useRef<HTMLButtonElement>(null);
  const forbidden = error.status === 403;
  const retry = () => {
    onRetry?.();
    button.current?.focus();
  };
  const onKeyDown = (event: KeyboardEvent<HTMLButtonElement>) => {
    if (event.key === 'Enter' || event.key === ' ') {
      event.preventDefault();
      retry();
    }
  };
  return <div data-testid="outlet-section-error"><Card className="my-3 p-4">
    <div role="alert" aria-live="polite">
      <span aria-hidden="true">⚠️</span>{' '}
      {forbidden ? 'Akun ini tidak terhubung ke outlet. Hubungi admin.' : `Data ${section === 'orders' ? 'pesanan' : section === 'shopping' ? 'belanja' : section === 'credit' ? 'kredit' : 'favorit'} tidak dapat dimuat.`}
      {!forbidden && onRetry && <button ref={button} type="button" data-testid="outlet-retry" onClick={retry} onKeyDown={onKeyDown} className="ml-3 rounded border px-3 py-1 focus-visible:outline-2 focus-visible:outline-offset-2">Coba lagi</button>}
    </div>
  </Card></div>;
}

function OrdersSummary({ summary }: { summary: OutletDashboardData['summary'] }) {
  const statuses = Object.entries(summary.statuses);
  return <>
    <section data-testid="outlet-orders-summary" aria-label="Ringkasan pesanan" className="grid auto-rows-auto gap-4 sm:grid-cols-2 lg:grid-cols-3">
      {statuses.map(([status, count]) => {
        const mapped = toOutletStatus(status);
        return <div key={status} title={mapped.tooltip}><StatCard label={mapped.label} value={safeNumber(count).toLocaleString('id-ID')} icon="📦" /></div>;
      })}
    </section>
    <div data-testid="outlet-orders-recent"><Card className="mt-5 p-5">
        <h2 className="mb-3 text-base font-semibold">Pesanan terbaru</h2>
        {summary.truncation.capped && <p className="mb-3 text-sm text-gray-500">Menampilkan 1.000 pesanan terbaru dari {safeNumber(summary.truncation.total).toLocaleString('id-ID')} pesanan</p>}
        {!summary.recent.length ? <p>Belum ada pesanan</p> : <ul className="divide-y">
          {[...summary.recent].sort((a, b) => b.created_at.localeCompare(a.created_at)).slice(0, 10).map((order) => {
            const status = toOutletStatus(order.status);
            return <li key={order.id} className="py-3">
              <a href={`/orders?order_id=${encodeURIComponent(order.order_id)}`} className="text-primary-700 underline">
                {order.order_id} · {order.created_at.slice(0, 10)} · <span title={status.tooltip}>{status.label}</span> · {rupiah(order.total_amount)}
              </a>
              <span className="ml-2 text-sm text-gray-500">{status.hint}</span>
              {status.ctaKey === 'view_invoice' && <a className="ml-2 underline" href="/invoices">Lihat tagihan</a>}
            </li>;
          })}
        </ul>}
        {summary.truncation.capped && <p className="mt-3 border-t pt-3 text-sm text-gray-500">Tidak ada data lebih baru (batas 1.000 pesanan terbaru)</p>}
    </Card></div>
  </>;
}

function Shopping({ data, period, onPeriodChange }: { data: OutletDashboardData; period: 7 | 30 | 90; onPeriodChange?: Props['onPeriodChange'] }) {
  // Data is fetched/recomputed for the selected window by the page; do not zero it locally.
  return <div data-testid="outlet-shopping-summary"><Card className="p-5">
    <h2 className="mb-3 text-base font-semibold">Ringkasan belanja</h2>
    <label htmlFor="outlet-period" className="mr-2">Periode belanja</label>
    <select id="outlet-period" value={period} onChange={(event) => onPeriodChange?.(Number(event.target.value) as 7 | 30 | 90)} className="rounded border p-1">
      {[7, 30, 90].map((days) => <option key={days} value={days}>{days} hari terakhir</option>)}
    </select>
    <p className="mt-3">{periodWindow(period).label}</p>
    {safeNumber(data.shopping.count) === 0 ? <p>{data.summary.total === 0 ? 'Belum ada pesanan' : 'Tidak ada transaksi pada periode ini'}</p> : <>
      <p>Total belanja: {rupiah(data.shopping.total)}</p><p>Jumlah transaksi: {safeNumber(data.shopping.count).toLocaleString('id-ID')}</p>
    </>}
  </Card></div>;
}

function Credit({ credit }: { credit: OutletDashboardData['credit'] }) {
  if (credit.hidden || credit.credit_limit === null) return null;
  return <div data-testid="outlet-credit-card"><Card className="p-5">
    <h2 className="mb-3 text-base font-semibold">Status kredit</h2>
    <p>Batas kredit: {rupiah(credit.credit_limit)}</p>
    <p>Piutang berjalan: {rupiah(credit.outstanding_balance)}</p>
    <p>Kredit tersedia: {rupiah(credit.available_credit)}</p>
  </Card></div>;
}

function Favorites({ favorites }: { favorites: OutletDashboardData['favorites'] }) {
  return <div data-testid="outlet-favorites"><Card className="p-5">
    <h2 className="mb-3 text-base font-semibold">Produk favorit</h2>
    {!favorites.length ? <p>Belum ada produk favorit</p> : <ol className="space-y-2">{favorites.slice(0, 5).map((item) => <li key={item.product_id}>
      {item.display_name?.trim() || `Produk #${item.product_id}`} · {safeNumber(item.total_qty).toLocaleString('id-ID')} unit · <a href="/orders" className="underline">Pesan lagi</a>
    </li>)}</ol>}
  </Card></div>;
}

export default function OutletDashboard({ data, ordersError, shoppingError, creditError, favoritesError, onRetryOrders, onRetryShopping, onRetryCredit, onRetryFavorites, onPeriodChange }: Props) {
  const [period, setPeriod] = useState<7 | 30 | 90>(30);
  useEffect(() => {
    const failures: [Section, DashboardError | null | undefined][] = [['orders', ordersError], ['shopping', shoppingError], ['credit', creditError], ['favorites', favoritesError]];
    for (const [section, error] of failures) if (error) console.warn(`Outlet dashboard ${section} unavailable`);
  }, [ordersError, shoppingError, creditError, favoritesError]);
  const selectPeriod = (days: 7 | 30 | 90) => { setPeriod(days); onPeriodChange?.(days); };
  return <div className="mx-auto max-w-6xl" data-testid="outlet-dashboard" aria-live="polite" data-credit-hidden={data?.credit?.hidden && data.credit.credit_limit === null ? 'true' : undefined}>
    <PageHeader title="Dasbor outlet" description="Pantau pesanan, belanja, kredit, dan produk favorit Anda." />
    {ordersError ? <SectionError section="orders" error={ordersError} onRetry={onRetryOrders} /> : data && <OrdersSummary summary={data.summary} />}
    <section className="mt-5 grid auto-rows-auto gap-5 md:grid-cols-2" aria-label="Ringkasan belanja dan kredit">
      {shoppingError ? <SectionError section="shopping" error={shoppingError} onRetry={onRetryShopping} /> : data && <Shopping data={data} period={period} onPeriodChange={selectPeriod} />}
      {creditError ? <SectionError section="credit" error={creditError} onRetry={onRetryCredit} /> : data && <Credit credit={data.credit} />}
    </section>
    <section className="mt-5" aria-label="Produk favorit">
      {favoritesError ? <SectionError section="favorites" error={favoritesError} onRetry={onRetryFavorites} /> : data && <Favorites favorites={data.favorites} />}
    </section>
  </div>;
}

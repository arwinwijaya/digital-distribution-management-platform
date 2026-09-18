'use client';

import StatCard from '@/components/ui/StatCard';
import { formatRupiah, formatCount } from '@/lib/format';
import { formatDeltaChip } from '@/app/analytics/presentation';
import type { AnalyticsInsight } from '@/app/analytics/types';

/**
 * Metric strip for the Analitik home.
 *
 * Deltable metrics render a chip from `formatDeltaChip()`; point-in-time counts
 * (`outlets_total`, `products_total`) intentionally carry no chip because they
 * have no comparable baseline.
 */
export default function MetricStrip({ insight }: { insight: AnalyticsInsight }) {
  const { metrics, metrics_delta: delta } = insight;

  const deltable = [
    { key: 'orders_total' as const, label: 'Pesanan', value: formatCount(metrics.orders_total) },
    { key: 'sales_total' as const, label: 'Penjualan', value: formatRupiah(metrics.sales_total) },
    { key: 'payments_total' as const, label: 'Pembayaran', value: formatRupiah(metrics.payments_total) },
    { key: 'outstanding_total' as const, label: 'Tunggakan 30 hari', value: formatRupiah(metrics.outstanding_total) },
  ];

  return (
    <div className="space-y-3">
      <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        {deltable.map(({ key, label, value }) => {
          const chip = formatDeltaChip(delta[key]);
          return <StatCard key={key} label={label} value={value} change={chip.text} changeType={chip.type} />;
        })}
        <StatCard label="Outlet" value={formatCount(metrics.outlets_total)} />
        <StatCard label="Produk" value={formatCount(metrics.products_total)} />
      </div>
      <p className="text-xs text-gray-500">
        Perbandingan {insight.comparison.period.start_date} – {insight.comparison.period.end_date} terhadap{' '}
        {insight.comparison.previous_period.start_date} – {insight.comparison.previous_period.end_date}.
      </p>
    </div>
  );
}

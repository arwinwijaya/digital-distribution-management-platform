'use client';

import { Badge, EmptyState } from '@/components/ui';
import { formatRupiah } from '@/lib/format';
import type { NeedsAttentionItem } from '@/app/analytics/types';

/**
 * "Perlu perhatian" strip. Rows come straight from the server-side qualification
 * (decline ≥ 20% or point-in-time outstanding > 0) so the UI never re-derives
 * the business rule.
 */
export default function NeedsAttention({ items }: { items: NeedsAttentionItem[] }) {
  if (items.length === 0) {
    return (
      <EmptyState
        title="Tidak ada outlet yang perlu perhatian"
        description="Semua outlet menunjukkan tren penjualan dan tunggakan yang wajar pada periode ini."
      />
    );
  }

  return (
    <ul className="divide-y divide-gray-100">
      {items.map((item) => {
        const isDecline = item.reason === 'sales_decline';
        return (
          <li key={item.outlet_id} className="flex items-center justify-between gap-4 py-3">
            <div className="min-w-0">
              <p className="truncate font-medium text-gray-900">{item.outlet_name}</p>
              <p className="text-xs text-gray-500">
                {isDecline
                  ? `Penjualan turun ${Math.abs(item.delta_percent ?? 0).toLocaleString('id-ID', { minimumFractionDigits: 1, maximumFractionDigits: 1 })}%`
                  : 'Risiko tunggakan menumpuk'}
              </p>
            </div>
            <div className="flex shrink-0 items-center gap-2">
              {!isDecline && (
                <span className="text-sm font-semibold text-danger-700">
                  {formatRupiah(item.outstanding_total)}
                </span>
              )}
              <Badge variant={isDecline ? 'red' : 'yellow'}>
                {isDecline ? 'Penjualan turun' : 'Total tunggakan'}
              </Badge>
            </div>
          </li>
        );
      })}
    </ul>
  );
}

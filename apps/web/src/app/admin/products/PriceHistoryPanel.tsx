'use client';

import React from 'react';

import { formatDateTime } from '@/lib/admin-table';

import type { PriceHistoryResult } from './api';

export type PriceHistoryFetchParams = {
  limit: number;
  cursor?: number;
  signal?: AbortSignal;
};

export type PriceHistoryPanelProps = {
  fetchHistory: (params: PriceHistoryFetchParams) => Promise<PriceHistoryResult>;
};

/**
 * Expandable price history panel (minimal cycle: initial page only).
 *
 * Requests the first page with `limit: 5` through the supplied typed
 * history-fetch callback and renders the rows; the load-more control is
 * shown only while the fetched page reports `hasMore`. Later cycles add
 * pagination, retry/404 and abort lifecycle handling.
 */
export default function PriceHistoryPanel({ fetchHistory }: PriceHistoryPanelProps) {
  const [entries, setEntries] = React.useState<PriceHistoryResult['data']>([]);
  const [hasMore, setHasMore] = React.useState(false);

  React.useEffect(() => {
    let active = true;
    fetchHistory({ limit: 5 }).then((page) => {
      if (!active) return;
      setEntries(page.data ?? []);
      setHasMore(Boolean(page.hasMore));
    });
    return () => {
      active = false;
    };
  }, [fetchHistory]);

  if (entries.length === 0) {
    return <p>Belum ada riwayat harga</p>;
  }

  return (
    <div>
      <ul>
        {entries.map((entry) => (
          <li key={entry.id}>
            Rp {Number(entry.new_price ?? 0).toLocaleString('id-ID')} · {formatDateTime(entry.changed_at ?? null)}
          </li>
        ))}
      </ul>
      {hasMore ? <button type="button">Muat lebih banyak</button> : null}
    </div>
  );
}

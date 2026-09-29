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
  onClose?: () => void;
};

type PriceHistoryError = {
  kind: 'error' | 'deleted';
  message: string;
};

type ErrorWithDetails = {
  message?: unknown;
  status?: unknown;
};

function classifyPriceHistoryError(error: unknown): PriceHistoryError {
  const details = error && typeof error === 'object' ? error as ErrorWithDetails : {};
  const message = typeof details.message === 'string' && details.message.trim()
    ? details.message
    : 'Riwayat harga tidak dapat dimuat.';
  const status = details.status;
  const isDeleted = status === 404 || /(?:deleted|dihapus|not found|tidak ditemukan)/i.test(message);

  return isDeleted
    ? { kind: 'deleted', message: 'Produk ini sudah dihapus.' }
    : { kind: 'error', message };
}

/**
 * Expandable price history panel (initial page with retry and deleted-product handling).
 *
 * Requests the first page with `limit: 5` through the supplied typed
 * history-fetch callback and renders the rows; the load-more control is
 * shown only while the fetched page reports `hasMore`.
 */
export default function PriceHistoryPanel({ fetchHistory, onClose }: PriceHistoryPanelProps) {
  const [entries, setEntries] = React.useState<PriceHistoryResult['data']>([]);
  const [hasMore, setHasMore] = React.useState(false);
  const [error, setError] = React.useState<PriceHistoryError | null>(null);
  const [retryKey, setRetryKey] = React.useState(0);

  React.useEffect(() => {
    let active = true;
    setError(null);
    fetchHistory({ limit: 5 })
      .then((page) => {
        if (!active) return;
        setEntries(page.data ?? []);
        setHasMore(Boolean(page.hasMore));
      })
      .catch((requestError: unknown) => {
        if (!active) return;
        const nextError = classifyPriceHistoryError(requestError);
        setError(nextError);
        if (nextError.kind === 'deleted') onClose?.();
      });
    return () => {
      active = false;
    };
  }, [fetchHistory, onClose, retryKey]);

  if (error) {
    return (
      <div role="alert">
        <p>{error.message}</p>
        {error.kind === 'error' ? (
          <button type="button" onClick={() => setRetryKey((key) => key + 1)}>Coba lagi</button>
        ) : null}
      </div>
    );
  }

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

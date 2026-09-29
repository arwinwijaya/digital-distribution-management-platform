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
 * Expandable price history panel with in-flight guard, request identity,
 * and stale-response suppression for concurrent/async seam safety.
 */
export default function PriceHistoryPanel({ fetchHistory, onClose }: PriceHistoryPanelProps) {
  const [entries, setEntries] = React.useState<PriceHistoryResult['data']>([]);
  const [hasMore, setHasMore] = React.useState(false);
  const [error, setError] = React.useState<PriceHistoryError | null>(null);
  const [retryKey, setRetryKey] = React.useState(0);
  const [loadingMore, setLoadingMore] = React.useState(false);
  const requestId = React.useRef(0);

  // Initial load effect
  React.useEffect(() => {
    const currentRequestId = ++requestId.current;
    const controller = new AbortController();

    setError(null);
    fetchHistory({ limit: 5, signal: controller.signal })
      .then((page) => {
        if (requestId.current !== currentRequestId) return;
        setEntries(page.data ?? []);
        setHasMore(Boolean(page.hasMore));
      })
      .catch((requestError: unknown) => {
        if (requestId.current !== currentRequestId) return;
        if (requestError instanceof DOMException && requestError.name === 'AbortError') return;
        const nextError = classifyPriceHistoryError(requestError);
        setError(nextError);
        if (nextError.kind === 'deleted') onClose?.();
      });

    return () => {
      controller.abort();
    };
  }, [fetchHistory, onClose, retryKey]);

  const handleLoadMore = React.useCallback(async () => {
    if (loadingMore) return;
    if (!hasMore) return;

    const currentRequestId = ++requestId.current;
    const controller = new AbortController();
    setLoadingMore(true);

    try {
      const page = await fetchHistory({ limit: 5, cursor: entries.length, signal: controller.signal });
      if (requestId.current !== currentRequestId) return;
      setEntries((prev) => [...prev, ...(page.data ?? [])]);
      setHasMore(Boolean(page.hasMore));
    } catch (requestError) {
      if (requestId.current !== currentRequestId) return;
      if (requestError instanceof DOMException && requestError.name === 'AbortError') return;
      // Load-more errors are surfaced as retryable inline errors without closing the panel.
      const nextError = classifyPriceHistoryError(requestError);
      setError(nextError);
    } finally {
      if (requestId.current === currentRequestId) {
        setLoadingMore(false);
      }
    }
  }, [fetchHistory, entries.length, hasMore, loadingMore]);

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
      {hasMore ? (
        <button
          type="button"
          onClick={handleLoadMore}
          disabled={loadingMore}
        >
          {loadingMore ? 'Memuat…' : 'Muat lebih banyak'}
        </button>
      ) : null}
    </div>
  );
}
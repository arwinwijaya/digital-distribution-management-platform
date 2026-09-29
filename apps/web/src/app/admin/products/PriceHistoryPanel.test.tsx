import '@testing-library/jest-dom';
import { fireEvent, render, screen, waitFor } from '@testing-library/react';

import PriceHistoryPanel from './PriceHistoryPanel';
import type { PriceHistoryEntry, PriceHistoryResult } from './api';

type FetchHistory = Parameters<typeof PriceHistoryPanel>[0]['fetchHistory'];

function entry(id: number): PriceHistoryEntry {
  return {
    id,
    product_id: 7,
    old_price: String(id * 1000),
    new_price: String(id * 1000 + 500),
    changed_by: 1,
    changed_at: `2026-01-${String(id).padStart(2, '0')}T10:00:00Z`,
  };
}

function result(data: PriceHistoryEntry[], hasMore = false, nextCursor: number | null = null): PriceHistoryResult {
  return { data, hasMore, limit: 5, cursor: 0, nextCursor };
}

function deferred<T>() {
  let resolve!: (value: T) => void;
  let reject!: (reason?: unknown) => void;
  const promise = new Promise<T>((resolvePromise, rejectPromise) => {
    resolve = resolvePromise;
    reject = rejectPromise;
  });
  return { promise, resolve, reject };
}

describe('PriceHistoryPanel', () => {
  it('loads five entries initially from a 12-entry history and shows the load-more control', async () => {
    const all = Array.from({ length: 12 }, (_, index) => entry(index + 1));
    const fetchHistory: jest.MockedFunction<FetchHistory> = jest.fn(async ({ limit }) =>
      result(all.slice(0, limit), true, limit),
    );

    render(<PriceHistoryPanel fetchHistory={fetchHistory} />);

    await waitFor(() => expect(screen.getAllByRole('listitem')).toHaveLength(5));
    expect(fetchHistory.mock.calls[0][0]).toEqual(expect.objectContaining({ limit: 5 }));
    expect(fetchHistory.mock.calls[0][0].signal).toBeInstanceOf(AbortSignal);
    expect(screen.getByRole('button', { name: 'Muat lebih banyak' })).toBeInTheDocument();
  });

  it('renders exactly five entries and hides load more when there is no next page', async () => {
    const fetchHistory: jest.MockedFunction<FetchHistory> = jest.fn(async () => result(
      Array.from({ length: 5 }, (_, index) => entry(index + 1)),
    ));

    render(<PriceHistoryPanel fetchHistory={fetchHistory} />);

    await waitFor(() => expect(screen.getAllByRole('listitem')).toHaveLength(5));
    expect(screen.queryByRole('button', { name: 'Muat lebih banyak' })).not.toBeInTheDocument();
  });

  it('shows a clear empty state and hides load more for an empty history', async () => {
    const fetchHistory: jest.MockedFunction<FetchHistory> = jest.fn(async () => result([]));

    render(<PriceHistoryPanel fetchHistory={fetchHistory} />);

    await waitFor(() => expect(screen.getByText('Belum ada riwayat harga')).toBeInTheDocument());
    expect(screen.queryByRole('listitem')).not.toBeInTheDocument();
    expect(screen.queryByRole('button', { name: 'Muat lebih banyak' })).not.toBeInTheDocument();
  });

  it('shows a retryable error and recovers after exactly one retry request', async () => {
    const fetchHistory: jest.MockedFunction<FetchHistory> = jest.fn()
      .mockRejectedValueOnce(new Error('Request timeout'))
      .mockResolvedValueOnce(result([entry(1)]));

    render(<PriceHistoryPanel fetchHistory={fetchHistory} />);

    await waitFor(() => expect(screen.getByRole('alert')).toHaveTextContent('Request timeout'));
    expect(screen.getByRole('button', { name: 'Coba lagi' })).toBeInTheDocument();
    expect(fetchHistory).toHaveBeenCalledTimes(1);

    fireEvent.click(screen.getByRole('button', { name: 'Coba lagi' }));

    await waitFor(() => expect(screen.getByRole('listitem')).toBeInTheDocument());
    expect(fetchHistory).toHaveBeenCalledTimes(2);
    expect(screen.queryByRole('button', { name: 'Coba lagi' })).not.toBeInTheDocument();
  });

  it('shows a deleted-product message and safely closes on a 404 rejection', async () => {
    const deletedError = Object.assign(new Error('Product not found'), { status: 404 });
    const fetchHistory: jest.MockedFunction<FetchHistory> = jest.fn().mockRejectedValue(deletedError);
    const onClose = jest.fn();

    render(<PriceHistoryPanel fetchHistory={fetchHistory} onClose={onClose} />);

    await waitFor(() => expect(screen.getByRole('alert')).toHaveTextContent(/dihapus/i));
    expect(onClose).toHaveBeenCalledTimes(1);
    expect(screen.queryByRole('button', { name: 'Coba lagi' })).not.toBeInTheDocument();
  });

  it('disables repeated load-more clicks while one request is pending', async () => {
    const firstPage = deferred<PriceHistoryResult>();
    const nextPage = deferred<PriceHistoryResult>();
    const fetchHistory: jest.MockedFunction<FetchHistory> = jest.fn(({ cursor }) =>
      cursor === undefined ? firstPage.promise : nextPage.promise,
    );

    render(<PriceHistoryPanel fetchHistory={fetchHistory} />);
    firstPage.resolve(result(Array.from({ length: 5 }, (_, index) => entry(index + 1)), true, 5));

    const loadMore = await screen.findByRole('button', { name: 'Muat lebih banyak' });
    fireEvent.click(loadMore);
    fireEvent.click(loadMore);

    expect(fetchHistory).toHaveBeenCalledTimes(2);
    expect(loadMore).toBeDisabled();
    expect(fetchHistory.mock.calls[1][0]).toEqual(expect.objectContaining({ limit: 5, cursor: 5 }));
    expect(fetchHistory.mock.calls[1][0].signal).toBeInstanceOf(AbortSignal);

    nextPage.resolve(result([entry(6)], false, null));
    await waitFor(() => expect(screen.getAllByRole('listitem')).toHaveLength(6));
    expect(screen.queryByRole('button', { name: 'Muat lebih banyak' })).not.toBeInTheDocument();
  });

  it('ignores a slower first-page response after switching products', async () => {
    const productA = deferred<PriceHistoryResult>();
    const productB = deferred<PriceHistoryResult>();
    const fetchA: jest.MockedFunction<FetchHistory> = jest.fn(() => productA.promise);
    const fetchB: jest.MockedFunction<FetchHistory> = jest.fn(() => productB.promise);

    const view = render(<PriceHistoryPanel fetchHistory={fetchA} />);
    const requestA = fetchA.mock.calls[0][0];
    view.rerender(<PriceHistoryPanel fetchHistory={fetchB} />);
    expect(requestA.signal).toBeInstanceOf(AbortSignal);
    expect(requestA.signal?.aborted).toBe(true);

    productA.resolve(result([entry(1)]));
    await Promise.resolve();
    expect(screen.queryByText(/Rp 1\.500/)).not.toBeInTheDocument();

    productB.resolve(result([entry(2)]));
    await waitFor(() => expect(screen.getByText(/Rp 2\.500/)).toBeInTheDocument());
    expect(screen.queryByText(/Rp 1\.500/)).not.toBeInTheDocument();
  });

  it('ignores a response that resolves after the panel unmounts', async () => {
    const pending = deferred<PriceHistoryResult>();
    const fetchHistory: jest.MockedFunction<FetchHistory> = jest.fn(() => pending.promise);
    const view = render(<PriceHistoryPanel fetchHistory={fetchHistory} />);
    const signal = fetchHistory.mock.calls[0][0].signal;

    view.unmount();
    expect(signal).toBeInstanceOf(AbortSignal);
    expect(signal?.aborted).toBe(true);

    pending.resolve(result([entry(1)]));
    await Promise.resolve();
    expect(screen.queryByRole('listitem')).not.toBeInTheDocument();
  });
});

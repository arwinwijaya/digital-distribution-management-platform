import '@testing-library/jest-dom';
import { render, screen, waitFor } from '@testing-library/react';

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

describe('PriceHistoryPanel', () => {
  it('loads five entries initially from a 12-entry history and shows the load-more control', async () => {
    const all = Array.from({ length: 12 }, (_, index) => entry(index + 1));
    const fetchHistory: jest.MockedFunction<FetchHistory> = jest.fn(async ({ limit }) =>
      result(all.slice(0, limit), true, limit),
    );

    render(<PriceHistoryPanel fetchHistory={fetchHistory} />);

    await waitFor(() => expect(screen.getAllByRole('listitem')).toHaveLength(5));
    expect(fetchHistory.mock.calls[0][0]).toEqual({ limit: 5 });
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
});

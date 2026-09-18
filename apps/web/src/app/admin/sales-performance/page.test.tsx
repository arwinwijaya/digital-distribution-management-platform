import React from 'react';
import '@testing-library/jest-dom';
import { render, screen, waitFor, fireEvent, act } from '@testing-library/react';

const mockGetStoredToken = jest.fn(() => 'test-token');
jest.mock('@/lib/api', () => ({
  apiUrl: (p: string) => `http://localhost:8000/api${p}`,
  authHeaders: (token: string) => ({ Authorization: `Bearer ${token}` }),
  getStoredToken: (...args: unknown[]) => (mockGetStoredToken as (...a: unknown[]) => string | null)(...args),
}));
import { useDummyStore } from '@/dummy/store';
import { installDummy } from '@/dummy/install';

const adminResponse = {
  status: 'success',
  data: [
    {
      user_id: 1,
      name: 'Sales A',
      email: 'salesa@example.com',
      period: '2026-09',
      target: '30000000.00',
      achievement: '12000000.00',
      percentage: '40.00',
      order_count: 3,
    },
    {
      user_id: 2,
      name: 'Sales B',
      email: 'salesb@example.com',
      period: '2026-09',
      target: '60000000.00',
      achievement: '30000000.00',
      percentage: '50.00',
      order_count: 5,
    },
  ],
  meta: { has_more: false, limit: 15, cursor: 0, total: 2 },
};

// Mutable response so individual tests can swap in a different payload.
let currentAdminResponse: unknown = adminResponse;

describe('admin sales performance page', () => {
  let originalFetch: typeof fetch | undefined;

  beforeEach(() => {
    useDummyStore.getState().reset();
    installDummy();
    mockGetStoredToken.mockReturnValue('test-token');
    currentAdminResponse = adminResponse;
    originalFetch = (globalThis as unknown as { fetch?: typeof fetch }).fetch;
    (globalThis as unknown as { fetch: unknown }).fetch = jest.fn(async (url: unknown) => {
      const urlString = String(url);
      if (urlString.includes('/admin/sales/performance')) {
        return { ok: true, status: 200, json: async () => currentAdminResponse } as Response;
      }
      return { ok: false, status: 404, json: async () => ({ status: 'error', message: 'not found' }) } as Response;
    });
  });

  afterEach(() => {
    if (originalFetch) {
      (globalThis as unknown as { fetch: unknown }).fetch = originalFetch;
    } else {
      delete (globalThis as unknown as { fetch?: unknown }).fetch;
    }
    jest.restoreAllMocks();
  });

  it('memuat ulang data saat Mode Dummy diaktifkan', async () => {
    const api = await import('@/app/admin/sales-performance/api');
    const spy = jest.spyOn(api, 'fetchAdminSalesPerformance');
    const { default: Page } = await import('@/app/admin/sales-performance/page');
    render(<Page />);

    await waitFor(() => expect(spy).toHaveBeenCalled());
    const before = spy.mock.calls.length;

    act(() => {
      useDummyStore.getState().toggle();
    });

    await waitFor(() => expect(spy.mock.calls.length).toBeGreaterThan(before));
  });

  it('renders all sales performance with period filter', async () => {
    const { default: Page } = await import('@/app/admin/sales-performance/page');
    render(<Page />);

    await waitFor(() => {
      expect(screen.getByText('Sales A')).toBeInTheDocument();
    });
    expect(screen.getByText('Sales B')).toBeInTheDocument();
    expect(screen.getByText('Kinerja sales')).toBeInTheDocument();
    expect(screen.getAllByText('40.00%').length).toBeGreaterThan(0);
    expect(screen.getAllByText('50.00%').length).toBeGreaterThan(0);

    const fetchMock = (globalThis as unknown as { fetch: jest.Mock }).fetch;
    const urls = fetchMock.mock.calls.map((c) => String(c[0]));
    expect(urls.some((u) => u.includes('/admin/sales/performance?'))).toBe(true);
  });

  it('applies period filter on Terapkan click', async () => {
    const { default: Page } = await import('@/app/admin/sales-performance/page');
    render(<Page />);

    await waitFor(() => {
      expect(screen.getByText('Sales A')).toBeInTheDocument();
    });

    fireEvent.change(screen.getByLabelText('Pilih periode'), { target: { value: '2026-08' } });

    await waitFor(() => {
      const fetchMock = (globalThis as unknown as { fetch: jest.Mock }).fetch;
      const urls = fetchMock.mock.calls.map((c) => String(c[0]));
      expect(urls.some((u) => u.includes('period=2026-08'))).toBe(true);
    });
  });

  describe('default achievement sort + summary', () => {
  // Server returns name ASC; achievements chosen so STRING compare !== NUMERIC
  // compare (string-desc would wrongly put '9000000.00' first).
  const orderResponse = {
    status: 'success',
    data: [
      {
        user_id: 1,
        name: 'Sales 12M',
        email: 'sales12@example.com',
        period: '2026-09',
        target: '20000000.00',
        achievement: '12000000.00',
        percentage: '60.00',
        order_count: 4,
      },
      {
        user_id: 2,
        name: 'Sales 3M',
        email: 'sales3@example.com',
        period: '2026-09',
        target: '20000000.00',
        achievement: '3000000.00',
        percentage: '15.00',
        order_count: 1,
      },
      {
        user_id: 3,
        name: 'Sales 9M',
        email: 'sales9@example.com',
        period: '2026-09',
        target: '20000000.00',
        achievement: '9000000.00',
        percentage: '45.00',
        order_count: 3,
      },
    ],
    meta: { has_more: false, limit: 15, cursor: 0, total: 3 },
  };

  it('renders rows in achievement DESC order by default (numeric via parseMoney)', async () => {
    currentAdminResponse = orderResponse;
    const { default: Page } = await import('@/app/admin/sales-performance/page');
    render(<Page />);

    await waitFor(() => {
      expect(screen.getByText('Sales 12M')).toBeInTheDocument();
    });

    const names = screen
      .getAllByRole('row')
      .slice(1)
      .map((row) => row.querySelector('td')?.textContent ?? '');

    expect(names).toEqual(['Sales 12M', 'Sales 9M', 'Sales 3M']);
  });

  it('renders the summary strip with the server total', async () => {
    currentAdminResponse = { ...adminResponse, meta: { ...adminResponse.meta, total: 42 } };
    const { default: Page } = await import('@/app/admin/sales-performance/page');
    render(<Page />);

    await waitFor(() => {
      expect(screen.getByTestId('table-summary')).toHaveTextContent('42 sales');
    });
  });

  it('never renders timestamp columns and never sends sort=achievement', async () => {
    const { default: Page } = await import('@/app/admin/sales-performance/page');
    render(<Page />);

    await waitFor(() => {
      expect(screen.getByText('Sales A')).toBeInTheDocument();
    });

    expect(screen.queryByText('Dibuat')).toBeNull();
    expect(screen.queryByText('Diperbarui')).toBeNull();

    const fetchMock = (globalThis as unknown as { fetch: jest.Mock }).fetch;
    const urls = fetchMock.mock.calls
      .map((c) => String(c[0]))
      .filter((u) => u.includes('/admin/sales/performance'));
    expect(urls.length).toBeGreaterThan(0);
    for (const url of urls) {
      expect(url).not.toContain('sort=achievement');
      expect(url).not.toContain('next_cursor');
    }
  });
});
});

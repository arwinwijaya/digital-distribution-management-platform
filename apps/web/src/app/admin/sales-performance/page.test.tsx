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
  has_more: false,
  next_cursor: null,
};

describe('admin sales performance page', () => {
  let originalFetch: typeof fetch | undefined;

  beforeEach(() => {
    useDummyStore.getState().reset();
    installDummy();
    mockGetStoredToken.mockReturnValue('test-token');
    originalFetch = (globalThis as unknown as { fetch?: typeof fetch }).fetch;
    (globalThis as unknown as { fetch: unknown }).fetch = jest.fn(async (url: unknown) => {
      const urlString = String(url);
      if (urlString.includes('/admin/sales/performance')) {
        return { ok: true, status: 200, json: async () => adminResponse } as Response;
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
});

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

const outletsResponse = {
  status: 'success',
  data: {
    data: [
      { id: 1, name: 'Warung Asri', category: 'warung', score: 12, is_active: true, territory_id: 1, address: 'Jl Merdeka' },
      { id: 2, name: 'Toko Sejahtera', category: 'grosir', score: 5, is_active: false, territory_id: 2 },
    ],
    has_more: false,
    limit: 15,
    cursor: 0,
  },
};

const summaryResponse = {
  status: 'success',
  data: { outlet_id: 1, outlet_name: 'Warung Asri', total_orders: 7, total_amount: '250000.00', last_order_date: '2026-09-10T08:00:00Z' },
};

const ordersResponse = {
  status: 'success',
  data: [{ id: 10, order_id: 'ORD-001', status: 'completed', total_amount: '50000.00' }],
  meta: { limit: 10, has_more: false, cursor: 0 },
};

const updateResponse = {
  status: 'success',
  data: { id: 1, name: 'Warung Asri Baru', category: 'warung', score: 12, is_active: true },
};

describe('admin outlets page', () => {
  let originalFetch: typeof fetch | undefined;

  beforeEach(() => {
    useDummyStore.getState().reset();
    installDummy();
    mockGetStoredToken.mockReturnValue('test-token');
    originalFetch = (globalThis as unknown as { fetch?: typeof fetch }).fetch;
    (globalThis as unknown as { fetch: unknown }).fetch = jest.fn(async (url: unknown, options?: { method?: string }) => {
      const urlString = String(url);
      if (urlString.includes('/admin/outlets/') && urlString.includes('/summary')) return { ok: true, status: 200, json: async () => summaryResponse } as Response;
      if (urlString.includes('/admin/outlets/') && urlString.includes('/orders')) return { ok: true, status: 200, json: async () => ordersResponse } as Response;
      if (urlString.includes('/admin/outlets') && (options?.method === 'PATCH' || options?.method === 'patch')) return { ok: true, status: 200, json: async () => updateResponse } as Response;
      if (urlString.includes('/admin/outlets')) return { ok: true, status: 200, json: async () => outletsResponse } as Response;
      return { ok: true, status: 200, json: async () => ({}) } as Response;
    });
  });

  afterEach(() => {
    if (originalFetch) (globalThis as unknown as { fetch: unknown }).fetch = originalFetch;
    else delete (globalThis as unknown as { fetch?: unknown }).fetch;
    jest.restoreAllMocks();
  });

  it('memuat ulang data saat Mode Dummy diaktifkan', async () => {
    const api = await import('@/app/admin/outlets/api');
    const spy = jest.spyOn(api, 'fetchAdminOutlets');
    const { default: Page } = await import('@/app/admin/outlets/page');
    render(<Page />);

    await waitFor(() => expect(spy).toHaveBeenCalled());
    const before = spy.mock.calls.length;

    act(() => {
      useDummyStore.getState().toggle();
    });

    await waitFor(() => expect(spy.mock.calls.length).toBeGreaterThan(before));
  });

  it('renders outlet list with filters and category/score columns', async () => {
    const { default: Page } = await import('@/app/admin/outlets/page');
    render(<Page />);

    await waitFor(() => expect(screen.getByText('Warung Asri')).toBeInTheDocument());
    expect(screen.getByText('Toko Sejahtera')).toBeInTheDocument();
    expect(screen.getByText('Kelola outlet')).toBeInTheDocument();
    expect(screen.getByText('Terapkan filter')).toBeInTheDocument();
  });

  it('opens edit and shows summary on outlet click', async () => {
    const { default: Page } = await import('@/app/admin/outlets/page');
    render(<Page />);

    await waitFor(() => expect(screen.getByText('Warung Asri')).toBeInTheDocument());

    fireEvent.click(screen.getAllByText('Ubah')[0]);
    await waitFor(() => expect(screen.getByText(/Ubah outlet/)).toBeInTheDocument());
    expect(screen.getByDisplayValue('Warung Asri')).toBeInTheDocument();
  });
});

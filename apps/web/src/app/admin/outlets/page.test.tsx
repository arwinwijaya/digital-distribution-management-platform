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
      { id: 1, name: 'Warung Asri', category: 'warung', score: 12, is_active: true, territory_id: 1, address: 'Jl Merdeka', created_at: '2026-09-01T08:00:00Z', updated_at: '2026-09-10T10:00:00Z' },
      { id: 2, name: 'Toko Sejahtera', category: 'grosir', score: 5, is_active: false, territory_id: 2, created_at: '2026-09-02T08:00:00Z', updated_at: '2026-09-11T10:00:00Z' },
    ],
    has_more: false,
    limit: 15,
    cursor: 0,
  },
  meta: {
    total: 2,
    summary: { active: 1, inactive: 1 },
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
  let fetchMock: jest.Mock;

  beforeEach(() => {
    useDummyStore.getState().reset();
    installDummy();
    mockGetStoredToken.mockReturnValue('test-token');
    originalFetch = (globalThis as unknown as { fetch?: typeof fetch }).fetch;
    fetchMock = jest.fn(async (url: unknown, options?: { method?: string }) => {
      const urlString = String(url);
      if (urlString.includes('/admin/outlets/') && urlString.includes('/summary')) return { ok: true, status: 200, json: async () => summaryResponse } as Response;
      if (urlString.includes('/admin/outlets/') && urlString.includes('/orders')) return { ok: true, status: 200, json: async () => ordersResponse } as Response;
      if (urlString.includes('/admin/outlets') && (options?.method === 'PATCH' || options?.method === 'patch')) return { ok: true, status: 200, json: async () => updateResponse } as Response;
      if (urlString.includes('/admin/outlets')) return { ok: true, status: 200, json: async () => outletsResponse } as Response;
      return { ok: true, status: 200, json: async () => ({}) } as Response;
    });
    (globalThis as unknown as { fetch: unknown }).fetch = fetchMock;
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

  it('renders summary strip with total, aktif and nonaktif counts', async () => {
    const { default: Page } = await import('@/app/admin/outlets/page');
    render(<Page />);

    await waitFor(() => {
      const summary = screen.getByTestId('table-summary').textContent ?? '';
      expect(summary).toContain('2 outlet');
    });
    const summary = screen.getByTestId('table-summary').textContent ?? '';
    expect(summary).toContain('1 aktif');
    expect(summary).toContain('1 nonaktif');
    expect(summary).toContain('\u00b7');
  });

  it('renders created_at and updated_at columns as formatted dates (not raw ISO)', async () => {
    const { default: Page } = await import('@/app/admin/outlets/page');
    render(<Page />);

    await waitFor(() => expect(screen.getByText('Warung Asri')).toBeInTheDocument());

    // Headers must include 'Dibuat' and 'Diperbarui'
    expect(screen.getByText('Dibuat')).toBeInTheDocument();
    expect(screen.getByText('Diperbarui')).toBeInTheDocument();

    // The raw ISO should not appear anywhere in the rendered document.
    expect(screen.queryByText('2026-09-01T08:00:00Z')).not.toBeInTheDocument();
    expect(screen.queryByText('2026-09-02T08:00:00Z')).not.toBeInTheDocument();
    // formatDateTime('id-ID', medium date + short time) produces a string like
    // "1 Sep 2026 15.00" or similar; the date year token must be present.
    const body = document.body.textContent ?? '';
    expect(body).toMatch(/2026/);
  });

  it('opens edit and shows summary on outlet click', async () => {
    const { default: Page } = await import('@/app/admin/outlets/page');
    render(<Page />);

    await waitFor(() => expect(screen.getByText('Warung Asri')).toBeInTheDocument());

    fireEvent.click(screen.getAllByText('Ubah')[0]);
    await waitFor(() => expect(screen.getByText(/Ubah outlet/)).toBeInTheDocument());
    expect(screen.getByDisplayValue('Warung Asri')).toBeInTheDocument();
  });

  describe('sort header click + reset cursor + paging', () => {
    const outletsResponseWithMore = {
      status: 'success',
      data: {
        data: [
          { id: 1, name: 'Warung Asri', category: 'warung', score: 12, is_active: true, territory_id: 1, address: 'Jl Merdeka', created_at: '2026-09-01T08:00:00Z', updated_at: '2026-09-10T10:00:00Z' },
          { id: 2, name: 'Toko Sejahtera', category: 'grosir', score: 5, is_active: false, territory_id: 2, created_at: '2026-09-02T08:00:00Z', updated_at: '2026-09-11T10:00:00Z' },
        ],
        has_more: true,
        limit: 15,
        cursor: 0,
      },
      meta: {
        total: 100,
        summary: { active: 50, inactive: 50 },
      },
    };

    beforeEach(() => {
      // Override fetchMock to return has_more: true for pagination tests
      fetchMock.mockImplementation(async (url: unknown, options?: { method?: string }) => {
        const urlString = String(url);
        if (urlString.includes('/admin/outlets/') && urlString.includes('/summary')) return { ok: true, status: 200, json: async () => summaryResponse } as Response;
        if (urlString.includes('/admin/outlets/') && urlString.includes('/orders')) return { ok: true, status: 200, json: async () => ordersResponse } as Response;
        if (urlString.includes('/admin/outlets') && (options?.method === 'PATCH' || options?.method === 'patch')) return { ok: true, status: 200, json: async () => updateResponse } as Response;
        if (urlString.includes('/admin/outlets')) return { ok: true, status: 200, json: async () => outletsResponseWithMore } as Response;
        return { ok: true, status: 200, json: async () => ({}) } as Response;
      });
    });

    it('clicking "Nama" header toggles sort=name,order=desc then asc, and resets cursor', async () => {
      const { default: Page } = await import('@/app/admin/outlets/page');
      render(<Page />);
      await waitFor(() => expect(screen.getByText('Warung Asri')).toBeInTheDocument());

      // Find the "Nama Outlet" header and click it
      const nameHeader = screen.getByText('Nama Outlet');
      fireEvent.click(nameHeader);

      await waitFor(() => {
        const calls = fetchMock.mock.calls;
        const lastCall = calls[calls.length - 1];
        const url = String(lastCall[0]);
        expect(url).toContain('sort=name');
        expect(url).toContain('order=desc');
        expect(url).toContain('cursor=0');
      });

      // Click again to toggle order (re-query: the table unmounts while loading)
      await waitFor(() => expect(screen.getByText('Nama Outlet')).toBeInTheDocument());
      fireEvent.click(screen.getByText('Nama Outlet'));

      await waitFor(() => {
        const calls = fetchMock.mock.calls;
        const lastCall = calls[calls.length - 1];
        const url = String(lastCall[0]);
        expect(url).toContain('sort=name');
        expect(url).toContain('order=asc');
        expect(url).toContain('cursor=0');
      });
    });

    it('changing filter while on page 3 resets cursor to 0', async () => {
      // First, simulate a page with cursor=30 (page 3)
      const { default: Page } = await import('@/app/admin/outlets/page');
      render(<Page />);
      await waitFor(() => expect(screen.getByText('Warung Asri')).toBeInTheDocument());

      const clickNext = async (expectedCursor: number) => {
        await waitFor(() => expect(screen.getByText('Berikutnya')).toBeInTheDocument());
        fireEvent.click(screen.getByText('Berikutnya'));
        await waitFor(() => {
          const calls = fetchMock.mock.calls;
          const lastCall = calls[calls.length - 1];
          expect(String(lastCall[0])).toContain(`cursor=${expectedCursor}`);
        });
      };

      // Advance to page 2 then page 3 (cursor 30)
      await clickNext(15);
      await clickNext(30);

      // Now change a filter
      const searchInput = screen.getByPlaceholderText('Nama outlet');
      fireEvent.change(searchInput, { target: { value: 'Warung' } });
      fireEvent.click(screen.getByText('Terapkan filter'));

      await waitFor(() => {
        const calls = fetchMock.mock.calls;
        const lastCall = calls[calls.length - 1];
        const url = String(lastCall[0]);
        expect(url).toContain('search=Warung');
        expect(url).toContain('cursor=0');
      });
    });
  });

  describe('density toggle + dummy parity', () => {
    it('density toggle changes the table padding class and persists to localStorage', async () => {
      const { default: Page } = await import('@/app/admin/outlets/page');
      render(<Page />);
      await waitFor(() => expect(screen.getByText('Warung Asri')).toBeInTheDocument());

      // Default density -> legacy padding
      let headerCell = screen.getByText('Nama Outlet').closest('th') as HTMLTableCellElement;
      expect(headerCell).toHaveClass('py-3');

      // Switch to Compact
      fireEvent.click(screen.getByRole('button', { name: 'Compact' }));
      await waitFor(() => {
        headerCell = screen.getByText('Nama Outlet').closest('th') as HTMLTableCellElement;
        expect(headerCell).toHaveClass('py-2');
      });
      expect(localStorage.getItem('admin:table-density')).toBe('compact');

      // Switch to Comfortable
      fireEvent.click(screen.getByRole('button', { name: 'Comfortable' }));
      await waitFor(() => {
        headerCell = screen.getByText('Nama Outlet').closest('th') as HTMLTableCellElement;
        expect(headerCell).toHaveClass('py-4');
      });
      expect(localStorage.getItem('admin:table-density')).toBe('comfortable');
    });

    it('dummy branch sorts with the same compareRows ordering (created_at DESC fallback id DESC)', async () => {
      // Turn dummy mode ON: the page must read dummy data (zero network) and
      // still apply the default created_at DESC ordering through compareRows.
      const { useDummyStore } = await import('@/dummy/store');
      useDummyStore.getState().toggle();
      expect(useDummyStore.getState().isDummy).toBe(true);

      const { fetchAdminOutlets } = await import('@/app/admin/outlets/api');
      const { compareRows } = await import('@/lib/admin-table');

      // Default (no sort): created_at DESC.
      const defaultResult = await fetchAdminOutlets('t-token', { limit: 200 });
      const expectedDefault = [...defaultResult.outlets].sort((a, b) =>
        compareRows(a as unknown as Record<string, unknown>, b as unknown as Record<string, unknown>, 'created_at', 'desc'),
      );
      expect(defaultResult.outlets.map((o) => o.id)).toEqual(expectedDefault.map((o) => o.id));
      expect(defaultResult.total).toBe(defaultResult.outlets.length);
      expect(defaultResult.summary).toEqual({
        active: defaultResult.outlets.filter((o) => o.is_active).length,
        inactive: defaultResult.outlets.filter((o) => !o.is_active).length,
      });

      // Explicit sort by name ASC must match compareRows('name','asc').
      const byName = await fetchAdminOutlets('t-token', { limit: 200, sort: 'name', order: 'asc' });
      const expectedByName = [...byName.outlets].sort((a, b) =>
        compareRows(a as unknown as Record<string, unknown>, b as unknown as Record<string, unknown>, 'name', 'asc'),
      );
      expect(byName.outlets.map((o) => o.id)).toEqual(expectedByName.map((o) => o.id));

      // The real network endpoint must NOT be called while dummy mode is ON.
      const outletCalls = fetchMock.mock.calls.filter((c) => String(c[0]).includes('/admin/outlets'));
      expect(outletCalls.length).toBe(0);

      const { default: Page } = await import('@/app/admin/outlets/page');
      render(<Page />);
      await waitFor(() => expect(screen.getByTestId('table-summary')).toBeInTheDocument());
      const summary = screen.getByTestId('table-summary').textContent ?? '';
      expect(summary).toMatch(/\d+ outlet/);
    });
  });
});

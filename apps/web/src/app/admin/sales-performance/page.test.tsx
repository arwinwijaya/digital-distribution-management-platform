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

  // ── Cycle 2: server name sort + offset paging + period reset ─────────────
  describe('server sort + offset paging + period reset', () => {
    let fetchMock: jest.Mock;

    /** T10 offset-cursor contract: rows in `data`, paging in `meta`. */
    function salesResponse(overrides?: { data?: unknown[]; total?: number; hasMore?: boolean; cursor?: number }) {
      const data = overrides?.data ?? [
        { user_id: 1, name: 'Zeta Sales', email: 'zeta@example.com', period: '2026-09', target: '20000000.00', achievement: '1000000.00', percentage: '5.00', order_count: 1 },
        { user_id: 2, name: 'Alpha Sales', email: 'alpha@example.com', period: '2026-09', target: '20000000.00', achievement: '9000000.00', percentage: '45.00', order_count: 4 },
        { user_id: 3, name: 'Mika Sales', email: 'mika@example.com', period: '2026-09', target: '20000000.00', achievement: '5000000.00', percentage: '25.00', order_count: 2 },
      ];
      const total = overrides?.total ?? data.length;
      return {
        status: 'success',
        data,
        meta: {
          has_more: overrides?.hasMore ?? false,
          limit: 15,
          cursor: overrides?.cursor ?? 0,
          total,
        },
      };
    }

    function lastUrl(): string {
      const calls = fetchMock.mock.calls.filter((c) => String(c[0]).includes('/admin/sales/performance'));
      return String(calls[calls.length - 1][0]);
    }

    function renderedNames(): string[] {
      return screen
        .getAllByRole('row')
        .slice(1)
        .map((row) => row.querySelector('td')?.textContent ?? '');
    }

    const now = new Date();
    const expectedPeriod = `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}`;

    beforeEach(() => {
      fetchMock = jest.fn(async (url: unknown) => {
        const urlString = String(url);
        if (urlString.includes('/admin/sales/performance')) {
          return { ok: true, status: 200, json: async () => salesResponse({ total: 100, hasMore: true }) } as Response;
        }
        return { ok: true, status: 200, json: async () => ({}) } as Response;
      });
      (globalThis as unknown as { fetch: unknown }).fetch = fetchMock;
    });

    it('sorts by the Sales header on the server: desc then asc, always cursor=0', async () => {
      const { default: Page } = await import('@/app/admin/sales-performance/page');
      render(<Page />);
      await waitFor(() => expect(screen.getByText('Zeta Sales')).toBeInTheDocument());

      fireEvent.click(screen.getByText('Sales'));
      await waitFor(() => {
        const url = lastUrl();
        expect(url).toContain('sort=name');
        expect(url).toContain('order=desc');
        expect(url).toContain('cursor=0');
      });

      fireEvent.click(screen.getByText('Sales'));
      await waitFor(() => {
        const url = lastUrl();
        expect(url).toContain('sort=name');
        expect(url).toContain('order=asc');
        expect(url).toContain('cursor=0');
      });

      const allUrls = fetchMock.mock.calls.map((c) => String(c[0]));
      for (const url of allUrls) {
        expect(url).not.toContain('sort=achievement');
      }
    });

    it('renders the server order (not the client-side achievement order) after sorting by Sales', async () => {
      const { default: Page } = await import('@/app/admin/sales-performance/page');
      render(<Page />);
      await waitFor(() => expect(screen.getByText('Zeta Sales')).toBeInTheDocument());

      // Default client-side achievement DESC would be [Alpha, Mika, Zeta].
      expect(renderedNames()).toEqual(['Alpha Sales', 'Mika Sales', 'Zeta Sales']);

      fireEvent.click(screen.getByText('Sales'));
      await waitFor(() => expect(lastUrl()).toContain('sort=name'));

      // Server returned [Zeta, Alpha, Mika] (name DESC) — it MUST win.
      await waitFor(() => {
        expect(renderedNames()).toEqual(['Zeta Sales', 'Alpha Sales', 'Mika Sales']);
      });
    });

    it('pages with an OFFSET cursor and preserves sort + period', async () => {
      const { default: Page } = await import('@/app/admin/sales-performance/page');
      render(<Page />);
      await waitFor(() => expect(screen.getByText('Zeta Sales')).toBeInTheDocument());

      fireEvent.click(screen.getByText('Sales'));
      await waitFor(() => expect(lastUrl()).toContain('sort=name'));

      fireEvent.click(screen.getByText('Berikutnya'));
      await waitFor(() => {
        const url = lastUrl();
        expect(url).toContain('cursor=15');
        expect(url).toContain('sort=name');
        expect(url).toContain(`period=${expectedPeriod}`);
      });
      expect(lastUrl()).not.toContain('next_cursor');
      expect(lastUrl()).not.toContain('sort=achievement');
    });

    it('resets to cursor=0 when the period changes and preserves the active sort', async () => {
      const { default: Page } = await import('@/app/admin/sales-performance/page');
      render(<Page />);
      await waitFor(() => expect(screen.getByText('Zeta Sales')).toBeInTheDocument());

      fireEvent.click(screen.getByText('Sales'));
      await waitFor(() => expect(lastUrl()).toContain('sort=name'));

      fireEvent.click(screen.getByText('Berikutnya'));
      await waitFor(() => expect(lastUrl()).toContain('cursor=15'));

      fireEvent.change(screen.getByLabelText('Periode (YYYY-MM)'), { target: { value: '2026-08' } });

      await waitFor(() => {
        const url = lastUrl();
        expect(url).toContain('cursor=0');
        expect(url).toContain('sort=name');
        expect(url).toContain('period=2026-08');
      });
      expect(lastUrl()).not.toContain('sort=achievement');
    });

    it('keeps inertness: only the Sales header is sortable', async () => {
      const { default: Page } = await import('@/app/admin/sales-performance/page');
      render(<Page />);
      await waitFor(() => expect(screen.getByText('Zeta Sales')).toBeInTheDocument());

      for (const header of ['Email', 'Target', 'Pencapaian', 'Persentase', 'Pesanan']) {
        expect(screen.getByText(header).closest('th')).not.toHaveAttribute('aria-sort');
      }

      const salesTh = screen.getByText('Sales').closest('th') as HTMLElement;
      expect(salesTh).toHaveAttribute('aria-sort', 'none');

      fireEvent.click(screen.getByText('Sales'));
      await waitFor(() => expect(salesTh).toHaveAttribute('aria-sort', 'descending'));

      for (const header of ['Email', 'Target', 'Pencapaian', 'Persentase', 'Pesanan']) {
        expect(screen.getByText(header).closest('th')).not.toHaveAttribute('aria-sort');
      }
    });
  });

  // ── Cycle 3: density toggle + dummy parity ───────────────────────────────
  describe('density toggle + dummy parity', () => {
    let fetchMock: jest.Mock;

    beforeEach(() => {
      localStorage.clear();
      useDummyStore.getState().reset();
      installDummy();
      fetchMock = (globalThis as unknown as { fetch: jest.Mock }).fetch;
    });

    it('density toggle changes the table header padding class and persists to localStorage', async () => {
      const { default: Page } = await import('@/app/admin/sales-performance/page');
      render(<Page />);
      await waitFor(() => expect(screen.getByText('Sales A')).toBeInTheDocument());

      let headerCell = screen.getByText('Sales').closest('th') as HTMLTableCellElement;
      expect(headerCell).toHaveClass('py-3');

      fireEvent.click(screen.getByRole('button', { name: 'Compact' }));
      await waitFor(() => {
        headerCell = screen.getByText('Sales').closest('th') as HTMLTableCellElement;
        expect(headerCell).toHaveClass('py-2');
      });
      expect(localStorage.getItem('admin:table-density')).toBe('compact');

      fireEvent.click(screen.getByRole('button', { name: 'Comfortable' }));
      await waitFor(() => {
        headerCell = screen.getByText('Sales').closest('th') as HTMLTableCellElement;
        expect(headerCell).toHaveClass('py-4');
      });
      expect(localStorage.getItem('admin:table-density')).toBe('comfortable');
    });

    it('dummy fetchAdminSalesPerformance mirrors achievement DESC + offset slice + total + summary (zero network)', async () => {
      const { fetchAdminSalesPerformance } = await import('@/app/admin/sales-performance/api');
      useDummyStore.getState().toggle();
      expect(useDummyStore.getState().isDummy).toBe(true);

      const all = await fetchAdminSalesPerformance('t-token', { limit: 200 });
      expect(all.rows.length).toBeGreaterThanOrEqual(4);
      expect(all.rows.map((r) => Number(r.achievement))).toEqual(
        [...all.rows.map((r) => Number(r.achievement))].sort((a, b) => b - a),
      );
      expect(all.total).toBe(all.rows.length);
      expect(all.summary).toEqual({ total: all.total });

      const page = await fetchAdminSalesPerformance('t-token', { limit: 2, cursor: 2 });
      expect(page.rows.map((r) => r.user_id)).toEqual(all.rows.slice(2, 4).map((r) => r.user_id));
      expect(page.nextCursor).toBe(4);

      expect(fetchMock.mock.calls.filter((c) => String(c[0]).includes('/admin/sales/performance')).length).toBe(0);
    });

    it('dummy mode renders the summary strip with zero network', async () => {
      useDummyStore.getState().toggle();
      const { default: Page } = await import('@/app/admin/sales-performance/page');
      render(<Page />);

      await waitFor(() => {
        expect(screen.getByTestId('table-summary').textContent ?? '').toMatch(/\d+ sales/);
      });
      expect(fetchMock.mock.calls.filter((c) => String(c[0]).includes('/admin/sales/performance')).length).toBe(0);
    });
  });

});

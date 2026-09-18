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

const promotionsResponse = {
  status: 'success',
  data: [
    { id: 1, name: 'Diskon 10%', discount_type: 'percent', discount_value: '10.00', start_date: '2026-09-10', end_date: '2026-09-20', is_active: true, broadcast_at: null },
    { id: 2, name: 'Potongan Rp5000', discount_type: 'fixed', discount_value: '5000.00', start_date: '2026-09-10', end_date: '2026-09-20', is_active: true, broadcast_at: null },
  ],
  has_more: false,
  next_cursor: null,
};

const createResponse = {
  status: 'success',
  data: { id: 3, name: 'Diskon Baru', discount_type: 'percent', discount_value: '15.00', start_date: '2026-09-10', end_date: '2026-09-20', is_active: true, broadcast_at: null },
};

describe('admin promotions page', () => {
  let originalFetch: typeof fetch | undefined;

  beforeEach(() => {
    useDummyStore.getState().reset();
    installDummy();
    mockGetStoredToken.mockReturnValue('test-token');
    originalFetch = (globalThis as unknown as { fetch?: typeof fetch }).fetch;
    (globalThis as unknown as { fetch: unknown }).fetch = jest.fn(async (url: unknown, options?: { method?: string }) => {
      const urlString = String(url);
      if (urlString.includes('/admin/promotions') && (options?.method === 'POST' || options?.method === 'post') && urlString.includes('/broadcast')) {
        return { ok: false, status: 404, json: async () => ({ status: 'error', message: 'Not found' }) } as Response;
      }
      if (urlString.includes('/admin/promotions') && (options?.method === 'POST' || options?.method === 'post')) {
        return { ok: true, status: 201, json: async () => createResponse } as Response;
      }
      if (urlString.includes('/admin/promotions') && (options?.method === 'DELETE' || options?.method === 'delete')) {
        return { ok: true, status: 200, json: async () => ({ status: 'success' }) } as Response;
      }
      if (urlString.includes('/admin/promotions') && (options?.method === 'PATCH' || options?.method === 'patch')) {
        return { ok: true, status: 200, json: async () => ({ status: 'success', data: { id: 1, name: 'Diskon 10% v2' } }) } as Response;
      }
      if (urlString.includes('/admin/promotions')) {
        return { ok: true, status: 200, json: async () => promotionsResponse } as Response;
      }
      return { ok: true, status: 200, json: async () => ({}) } as Response;
    });
  });

  afterEach(() => {
    if (originalFetch) (globalThis as unknown as { fetch: unknown }).fetch = originalFetch;
    else delete (globalThis as unknown as { fetch?: unknown }).fetch;
    jest.restoreAllMocks();
  });

  it('memuat ulang data saat Mode Dummy diaktifkan', async () => {
    const api = await import('@/app/admin/promotions/api');
    const spy = jest.spyOn(api, 'fetchPromotions');
    const { default: Page } = await import('@/app/admin/promotions/page');
    render(<Page />);

    await waitFor(() => expect(spy).toHaveBeenCalled());
    const before = spy.mock.calls.length;

    act(() => {
      useDummyStore.getState().toggle();
    });

    await waitFor(() => expect(spy.mock.calls.length).toBeGreaterThan(before));
  });

  it('renders CRUD list with Siarkan button and handles broadcast 404 gracefully', async () => {
    const { default: Page } = await import('@/app/admin/promotions/page');
    render(<Page />);

    await waitFor(() => expect(screen.getByText('Diskon 10%')).toBeInTheDocument());
    expect(screen.getByText('Potongan Rp5000')).toBeInTheDocument();
    expect(screen.getAllByText('Siarkan').length).toBeGreaterThan(0);

    fireEvent.click(screen.getAllByText('Siarkan')[0]);

    await waitFor(() => {
      expect(screen.getByText('Broadcast endpoint belum tersedia')).toBeInTheDocument();
    });
  });

  it('creates promotions via modal', async () => {
    const { default: Page } = await import('@/app/admin/promotions/page');
    render(<Page />);

    await waitFor(() => expect(screen.getByText('Diskon 10%')).toBeInTheDocument());

    fireEvent.click(screen.getByText('+ Buat promosi'));
    await waitFor(() => expect(screen.getByText('Buat promosi')).toBeInTheDocument());

    fireEvent.change(screen.getByLabelText('Nama'), { target: { value: 'Diskon Baru' } });
    fireEvent.change(screen.getByLabelText('Nilai diskon'), { target: { value: '15' } });
    fireEvent.change(screen.getByLabelText('Mulai'), { target: { value: '2026-09-10' } });
    fireEvent.change(screen.getByLabelText('Selesai'), { target: { value: '2026-09-20' } });
    const selects = screen.getAllByLabelText(/Aktif/);
    fireEvent.change(selects[0], { target: { value: 'true' } });

    fireEvent.click(screen.getByText('Simpan'));

    await waitFor(() => expect(screen.getByText('Promosi dibuat.')).toBeInTheDocument());
  });

  // ── Cycle 1: default sort + 4-state summary + timestamp columns ──────────
  describe('default sort, summary 4-state and timestamp columns', () => {
    let fetchMock: jest.Mock;

    /** The T7 offset-cursor contract: rows in `data`, meta + 4-state summary. */
    function t7Response(overrides?: { data?: unknown[]; summary?: unknown; total?: number }) {
      const data = overrides?.data ?? [
        { id: 2, name: 'Promo Baru', discount_type: 'percentage', discount_value: '10.00', start_date: '2026-09-10', end_date: '2026-09-20', is_active: true, broadcast_at: null, created_at: '2026-09-05T08:00:00Z', updated_at: '2026-09-10T10:00:00Z' },
        { id: 1, name: 'Promo Lama', discount_type: 'fixed', discount_value: '5000.00', start_date: '2026-09-01', end_date: '2026-09-08', is_active: true, broadcast_at: null, created_at: '2026-09-01T08:00:00Z', updated_at: '2026-09-11T10:00:00Z' },
      ];
      const total = overrides?.total ?? data.length;
      return {
        status: 'success',
        data,
        meta: {
          has_more: false,
          limit: 15,
          cursor: 0,
          total,
          summary: overrides?.summary ?? { total, active: 2, scheduled: 0, ended: 0 },
        },
      };
    }

    function lastPromotionsUrl(): string {
      const calls = fetchMock.mock.calls.filter((c) => String(c[0]).includes('/admin/promotions'));
      return String(calls[calls.length - 1][0]);
    }

    beforeEach(() => {
      fetchMock = jest.fn(async (url: unknown) => {
        const urlString = String(url);
        if (urlString.includes('/admin/promotions')) return { ok: true, status: 200, json: async () => t7Response() } as Response;
        return { ok: true, status: 200, json: async () => ({}) } as Response;
      });
      (globalThis as unknown as { fetch: unknown }).fetch = fetchMock;
    });

    it('sends sort=created_at&order=desc&cursor=0 on first load', async () => {
      const { default: Page } = await import('@/app/admin/promotions/page');
      render(<Page />);
      await waitFor(() => expect(screen.getByText('Promo Baru')).toBeInTheDocument());

      const url = lastPromotionsUrl();
      expect(url).toContain('sort=created_at');
      expect(url).toContain('order=desc');
      expect(url).toContain('cursor=0');
      // Offset cursor only — never an id-based next_cursor.
      expect(url).not.toContain('next_cursor');
    });

    it('renders rows newest-first and the 4-state summary strip', async () => {
      const { default: Page } = await import('@/app/admin/promotions/page');
      render(<Page />);
      await waitFor(() => expect(screen.getByText('Promo Baru')).toBeInTheDocument());

      // Server order (newest-first) is preserved: Promo Baru before Promo Lama.
      const rows = screen.getAllByRole('row');
      const text = rows.map((r) => r.textContent ?? '').join('|');
      expect(text.indexOf('Promo Baru')).toBeGreaterThanOrEqual(0);
      expect(text.indexOf('Promo Baru')).toBeLessThan(text.indexOf('Promo Lama'));

      await waitFor(() => {
        const summary = screen.getByTestId('table-summary').textContent ?? '';
        expect(summary).toMatch(/2 promosi/);
      });
      const summary = screen.getByTestId('table-summary').textContent ?? '';
      expect(summary).toMatch(/aktif/);
      expect(summary).toContain('2 aktif');
      expect(summary).toContain('0 terjadwal');
      expect(summary).toContain('0 berakhir');
    });

    it('renders Dibuat/Diperbarui headers and formatted (non-raw-ISO) cells', async () => {
      const { default: Page } = await import('@/app/admin/promotions/page');
      render(<Page />);
      await waitFor(() => expect(screen.getByText('Promo Baru')).toBeInTheDocument());

      expect(screen.getByText('Dibuat')).toBeInTheDocument();
      expect(screen.getByText('Diperbarui')).toBeInTheDocument();
      // Raw ISO strings must NOT leak into the rendered cells.
      expect(screen.queryByText('2026-09-05T08:00:00Z')).not.toBeInTheDocument();
      expect(screen.queryByText('2026-09-01T08:00:00Z')).not.toBeInTheDocument();

      const row = screen.getByText('Promo Baru').closest('tr') as HTMLTableRowElement;
      const cells = Array.from(row.querySelectorAll('td'));
      // Columns: name, discount, start_date, end_date, status, created_at, updated_at, action
      expect(cells[5].textContent?.trim().length).toBeGreaterThan(0);
      expect(cells[6].textContent?.trim().length).toBeGreaterThan(0);
      expect(cells[5]).not.toHaveTextContent('2026-09-05T08:00:00Z');
      expect(cells[6]).not.toHaveTextContent('2026-09-10T10:00:00Z');
    });

    it('renders an em dash for null timestamps instead of "Invalid Date"', async () => {
      fetchMock.mockImplementation(async (url: unknown) => {
        const urlString = String(url);
        if (urlString.includes('/admin/promotions')) {
          return { ok: true, status: 200, json: async () => t7Response({
            data: [{ id: 99, name: 'Promo Tanpa Tanggal', discount_type: 'percentage', discount_value: '10.00', start_date: '2026-09-10', end_date: '2026-09-20', is_active: true, broadcast_at: null, created_at: null, updated_at: null }],
            total: 1,
            summary: { total: 1, active: 1, scheduled: 0, ended: 0 },
          }) } as Response;
        }
        return { ok: true, status: 200, json: async () => ({}) } as Response;
      });

      const { default: Page } = await import('@/app/admin/promotions/page');
      render(<Page />);
      await waitFor(() => expect(screen.getByText('Promo Tanpa Tanggal')).toBeInTheDocument());

      const row = screen.getByText('Promo Tanpa Tanggal').closest('tr') as HTMLTableRowElement;
      const cells = Array.from(row.querySelectorAll('td'));
      expect(cells[5]).toHaveTextContent('\u2014');
      expect(cells[6]).toHaveTextContent('\u2014');
      expect(screen.queryByText('Invalid Date')).not.toBeInTheDocument();
    });
  });

  // ── Cycle 2: sort header click + offset paging ───────────────────────────
  describe('sort header click + offset paging', () => {
    let fetchMock: jest.Mock;

    /** T7 offset-cursor contract: rows in `data`, paging + 4-state summary in `meta`. */
    function t7Response(overrides?: { data?: unknown[]; total?: number; hasMore?: boolean; cursor?: number }) {
      const data = overrides?.data ?? [
        { id: 2, name: 'Promo Baru', discount_type: 'percentage', discount_value: '10.00', start_date: '2026-09-10', end_date: '2026-09-20', is_active: true, broadcast_at: null, created_at: '2026-09-05T08:00:00Z', updated_at: '2026-09-10T10:00:00Z' },
        { id: 1, name: 'Promo Lama', discount_type: 'fixed', discount_value: '5000.00', start_date: '2026-09-01', end_date: '2026-09-08', is_active: true, broadcast_at: null, created_at: '2026-09-01T08:00:00Z', updated_at: '2026-09-11T10:00:00Z' },
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
          summary: { total, active: 2, scheduled: 0, ended: 0 },
        },
      };
    }

    function lastPromotionsUrl(): string {
      const calls = fetchMock.mock.calls.filter((c) => String(c[0]).includes('/admin/promotions'));
      return String(calls[calls.length - 1][0]);
    }

    function promotionsCallCount(): number {
      return fetchMock.mock.calls.filter((c) => String(c[0]).includes('/admin/promotions')).length;
    }

    beforeEach(() => {
      fetchMock = jest.fn(async (url: unknown) => {
        const urlString = String(url);
        if (urlString.includes('/admin/promotions')) {
          return { ok: true, status: 200, json: async () => t7Response({ total: 100, hasMore: true, cursor: 0 }) } as Response;
        }
        return { ok: true, status: 200, json: async () => ({}) } as Response;
      });
      (globalThis as unknown as { fetch: unknown }).fetch = fetchMock;
    });

    it('toggles "Mulai" desc→asc and always sends an offset cursor (never next_cursor)', async () => {
      const { default: Page } = await import('@/app/admin/promotions/page');
      render(<Page />);
      await waitFor(() => expect(screen.getByText('Promo Baru')).toBeInTheDocument());

      // First click on a new column starts at desc.
      fireEvent.click(screen.getByText('Mulai'));
      await waitFor(() => {
        const url = lastPromotionsUrl();
        expect(url).toContain('sort=start_date');
        expect(url).toContain('order=desc');
        expect(url).toContain('cursor=0');
      });
      expect(lastPromotionsUrl()).not.toContain('next_cursor');

      // The table swaps to a loading state while fetching — wait for it to return.
      await screen.findByText('Mulai');

      // Second click flips to asc, still page 1.
      fireEvent.click(screen.getByText('Mulai'));
      await waitFor(() => {
        const url = lastPromotionsUrl();
        expect(url).toContain('sort=start_date');
        expect(url).toContain('order=asc');
        expect(url).toContain('cursor=0');
      });
      expect(lastPromotionsUrl()).not.toContain('next_cursor');
    });

    it('pages with an OFFSET cursor (15) and preserves the active sort', async () => {
      const { default: Page } = await import('@/app/admin/promotions/page');
      render(<Page />);
      await waitFor(() => expect(screen.getByText('Promo Baru')).toBeInTheDocument());

      // Activate sort=start_date first.
      fireEvent.click(screen.getByText('Mulai'));
      await waitFor(() => expect(lastPromotionsUrl()).toContain('sort=start_date'));
      await screen.findByText('Mulai');

      // Page 2 must move to offset 15, NOT an id cursor, and keep the sort.
      fireEvent.click(screen.getByText('Berikutnya'));
      await waitFor(() => {
        const url = lastPromotionsUrl();
        expect(url).toContain('cursor=15');
        expect(url).toContain('sort=start_date');
      });
      expect(lastPromotionsUrl()).not.toContain('next_cursor');
    });

    it('reflects aria-sort on the sortable headers and keeps "Aksi" inert', async () => {
      const { default: Page } = await import('@/app/admin/promotions/page');
      render(<Page />);
      await waitFor(() => expect(screen.getByText('Promo Baru')).toBeInTheDocument());

      // Default sort column is created_at (desc) → "Dibuat" descending, "Mulai" none.
      expect(screen.getByText('Dibuat').closest('th')).toHaveAttribute('aria-sort', 'descending');
      expect(screen.getByText('Mulai').closest('th')).toHaveAttribute('aria-sort', 'none');

      fireEvent.click(screen.getByText('Mulai'));
      await waitFor(() => {
        expect(screen.getByText('Mulai').closest('th')).toHaveAttribute('aria-sort', 'descending');
      });
      await screen.findByText('Mulai');
      expect(screen.getByText('Dibuat').closest('th')).toHaveAttribute('aria-sort', 'none');

      // "Aksi" is not sortable: no aria-sort and clicking it must not fetch.
      const aksi = screen.getByText('Aksi').closest('th') as HTMLElement;
      expect(aksi).not.toHaveAttribute('aria-sort');
      const before = promotionsCallCount();
      fireEvent.click(aksi);
      expect(promotionsCallCount()).toBe(before);
    });

    it('resets to cursor=0 when sorting while on a later page', async () => {
      const { default: Page } = await import('@/app/admin/promotions/page');
      render(<Page />);
      await waitFor(() => expect(screen.getByText('Promo Baru')).toBeInTheDocument());

      // Move to page 2 (offset 15).
      fireEvent.click(screen.getByText('Berikutnya'));
      await waitFor(() => expect(lastPromotionsUrl()).toContain('cursor=15'));
      await screen.findByText('Mulai');

      // Sorting must jump back to page 1 while carrying the new sort.
      fireEvent.click(screen.getByText('Mulai'));
      await waitFor(() => {
        const url = lastPromotionsUrl();
        expect(url).toContain('cursor=0');
        expect(url).toContain('sort=start_date');
      });
    });
  });
  // ── Cycle 3: density toggle + dummy parity ───────────────────────────────
  describe('density toggle + dummy parity', () => {
    let fetchMock: jest.Mock;

    /** T7 offset-cursor contract: rows in `data`, meta + 4-state summary. */
    function t7Response() {
      const data = [
        { id: 2, name: 'Promo Baru', discount_type: 'percentage', discount_value: '10.00', start_date: '2026-09-10', end_date: '2026-09-20', is_active: true, broadcast_at: null, created_at: '2026-09-05T08:00:00Z', updated_at: '2026-09-10T10:00:00Z' },
        { id: 1, name: 'Promo Lama', discount_type: 'fixed', discount_value: '5000.00', start_date: '2026-09-01', end_date: '2026-09-08', is_active: true, broadcast_at: null, created_at: '2026-09-01T08:00:00Z', updated_at: '2026-09-11T10:00:00Z' },
      ];
      return {
        status: 'success',
        data,
        meta: { has_more: false, limit: 15, cursor: 0, total: data.length, summary: { total: 2, active: 2, scheduled: 0, ended: 0 } },
      };
    }

    beforeEach(() => {
      // Density + dummy state must not leak across tests.
      localStorage.clear();
      useDummyStore.getState().reset();
      fetchMock = jest.fn(async (url: unknown) => {
        const urlString = String(url);
        if (urlString.includes('/admin/promotions')) return { ok: true, status: 200, json: async () => t7Response() } as Response;
        return { ok: true, status: 200, json: async () => ({}) } as Response;
      });
      (globalThis as unknown as { fetch: unknown }).fetch = fetchMock;
    });

    it('density toggle changes the table header padding class and persists to localStorage', async () => {
      const { default: Page } = await import('@/app/admin/promotions/page');
      render(<Page />);
      await waitFor(() => expect(screen.getByText('Promo Baru')).toBeInTheDocument());

      // Default density → header <th> keeps the legacy `py-3`.
      let headerCell = screen.getByText('Nama').closest('th') as HTMLTableCellElement;
      expect(headerCell).toHaveClass('py-3');

      fireEvent.click(screen.getByRole('button', { name: 'Compact' }));
      await waitFor(() => {
        headerCell = screen.getByText('Nama').closest('th') as HTMLTableCellElement;
        expect(headerCell).toHaveClass('py-2');
      });
      expect(localStorage.getItem('admin:table-density')).toBe('compact');

      fireEvent.click(screen.getByRole('button', { name: 'Comfortable' }));
      await waitFor(() => {
        headerCell = screen.getByText('Nama').closest('th') as HTMLTableCellElement;
        expect(headerCell).toHaveClass('py-4');
      });
      expect(localStorage.getItem('admin:table-density')).toBe('comfortable');
    });

    it('dummy listDummyPromotions mirrors sort + offset slice + total + summary (zero network)', async () => {
      // Pin `new Date()` so the 4-state split is deterministic (dummy dates are 2026-01-* / 2026-02-*).
      jest.useFakeTimers();
      try {
        jest.setSystemTime(new Date('2026-02-14T10:00:00+07:00'));

        const { fetchPromotions } = await import('@/app/admin/promotions/api');
        const { compareRows } = await import('@/lib/admin-table');

        useDummyStore.getState().toggle();
        expect(useDummyStore.getState().isDummy).toBe(true);

        // Default order equals compareRows(..., 'created_at', 'desc') over the returned set.
        const all = await fetchPromotions('t-token', { limit: 200 });
        const expected = [...all.promotions].sort((a, b) =>
          compareRows(a as unknown as Record<string, unknown>, b as unknown as Record<string, unknown>, 'created_at', 'desc'),
        );
        expect(all.promotions.map((p) => p.id)).toEqual(expected.map((p) => p.id));
        expect(all.total).toBe(all.promotions.length);

        // 4-state summary, pinned to the fake clock: every dummy promo has ended.
        expect(all.summary).toEqual({ total: all.total, active: 0, scheduled: 0, ended: all.total });
        const { active = 0, scheduled = 0, ended = 0 } = all.summary ?? {};
        expect(active + scheduled + ended).toBe(all.total);

        // Offset slice: cursor 3, limit 3 → rows 4..6 of the sorted list; nextCursor = cursor + limit.
        const page = await fetchPromotions('t-token', { limit: 3, cursor: 3 });
        expect(page.promotions.map((p) => p.id)).toEqual(all.promotions.slice(3, 6).map((p) => p.id));
        expect(page.nextCursor).toBe(6);

        // Explicit start_date ASC equals compareRows('start_date', 'asc').
        const byStart = await fetchPromotions('t-token', { limit: 200, sort: 'start_date', order: 'asc' });
        const expectedByStart = [...byStart.promotions].sort((a, b) =>
          compareRows(a as unknown as Record<string, unknown>, b as unknown as Record<string, unknown>, 'start_date', 'asc'),
        );
        expect(byStart.promotions.map((p) => p.id)).toEqual(expectedByStart.map((p) => p.id));

        // Invalid sort falls back without throwing and stays deterministic across two calls.
        const bogusA = await fetchPromotions('t-token', { limit: 200, sort: 'bogus' });
        const bogusB = await fetchPromotions('t-token', { limit: 200, sort: 'bogus' });
        expect(bogusA.promotions.map((p) => p.id)).toEqual(bogusB.promotions.map((p) => p.id));
        expect(bogusA.promotions).toHaveLength(all.promotions.length);

        // Zero network while dummy mode is ON.
        expect(fetchMock.mock.calls.filter((c) => String(c[0]).includes('/admin/promotions')).length).toBe(0);
      } finally {
        jest.useRealTimers();
      }
    });

    it('dummy mode renders the summary strip with zero network', async () => {
      useDummyStore.getState().toggle();
      const { default: Page } = await import('@/app/admin/promotions/page');
      render(<Page />);

      await waitFor(() => {
        const summary = screen.getByTestId('table-summary').textContent ?? '';
        expect(summary).toMatch(/\d+ promosi/);
      });
      expect(fetchMock.mock.calls.filter((c) => String(c[0]).includes('/admin/promotions')).length).toBe(0);
    });
  });

});

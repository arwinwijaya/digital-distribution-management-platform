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

const productsResponse = {
  status: 'success',
  data: [
    { id: 1, name: 'Kopi Kapal', sku: 'SKU-001', price: '15000.00', stock_quantity: 40 },
    { id: 2, name: 'Gula Pasir 1kg', sku: 'SKU-002', price: '19000.00', stock_quantity: 0 },
  ],
};

const historyResponse = {
  status: 'success',
  data: {
    data: [{ id: 5, product_id: 1, old_price: '14000.00', new_price: '15000.00', changed_by: 1, changed_at: '2026-09-12T10:00:00Z' }],
    meta: { limit: 20, cursor: 0, has_more: false, next_cursor: null },
  },
};

const updateResponse = {
  status: 'success',
  data: { id: 1, name: 'Kopi Kapal', price: '16000.00' },
};

/** A list response with meta.total/meta.summary + timestamp columns. */
function listResponse(overrides?: { data?: unknown[]; total?: number; outOfStock?: number; hasMore?: boolean }) {
  const data = overrides?.data ?? [
    { id: 2, name: 'Kopi Kapal', sku: 'SKU-001', price: '15000.00', stock_quantity: 40, created_at: '2026-09-05T08:00:00Z', updated_at: '2026-09-10T10:00:00Z' },
    { id: 1, name: 'Gula Pasir 1kg', sku: 'SKU-002', price: '19000.00', stock_quantity: 0, created_at: '2026-09-01T08:00:00Z', updated_at: '2026-09-11T10:00:00Z' },
  ];
  const total = overrides?.total ?? data.length;
  const outOfStock = overrides?.outOfStock ?? 1;
  return {
    status: 'success',
    data,
    meta: {
      has_more: overrides?.hasMore ?? false,
      limit: 15,
      cursor: 0,
      total,
      summary: { total, out_of_stock: outOfStock },
    },
  };
}

function jsonResponse(body: unknown): Response {
  return { ok: true, status: 200, json: async () => body } as Response;
}

function lastProductsUrl(fetchMock: jest.Mock): string {
  const calls = fetchMock.mock.calls.filter((c) => String(c[0]).includes('/products'));
  return String(calls[calls.length - 1][0]);
}

describe('admin products page', () => {
  let originalFetch: typeof fetch | undefined;
  let fetchMock: jest.Mock;

  beforeEach(() => {
    localStorage.clear();
    useDummyStore.getState().reset();
    installDummy();
    mockGetStoredToken.mockReturnValue('test-token');
    originalFetch = (globalThis as unknown as { fetch?: typeof fetch }).fetch;
    fetchMock = jest.fn(async (url: unknown, options?: { method?: string }) => {
      const urlString = String(url);
      if (urlString.includes('/admin/products/') && urlString.includes('/prices')) return jsonResponse(historyResponse);
      if (urlString.includes('/admin/products') && (options?.method === 'PATCH' || options?.method === 'patch')) return jsonResponse(updateResponse);
      if (urlString.includes('/products')) return jsonResponse(productsResponse);
      return jsonResponse({});
    });
    (globalThis as unknown as { fetch: unknown }).fetch = fetchMock;
  });

  afterEach(() => {
    if (originalFetch) (globalThis as unknown as { fetch: unknown }).fetch = originalFetch;
    else delete (globalThis as unknown as { fetch?: unknown }).fetch;
    jest.restoreAllMocks();
  });

  it('memuat ulang data saat Mode Dummy diaktifkan', async () => {
    const api = await import('@/app/admin/products/api');
    const spy = jest.spyOn(api, 'fetchAdminProducts');
    const { default: Page } = await import('@/app/admin/products/page');
    render(<Page />);

    await waitFor(() => expect(spy).toHaveBeenCalled());
    const before = spy.mock.calls.length;

    act(() => {
      useDummyStore.getState().toggle();
    });

    await waitFor(() => expect(spy.mock.calls.length).toBeGreaterThan(before));
  });

  it('renders product list and price edit', async () => {
    const { default: Page } = await import('@/app/admin/products/page');
    render(<Page />);

    await waitFor(() => expect(screen.getByText('Kopi Kapal')).toBeInTheDocument());
    expect(screen.getByText('Gula Pasir 1kg')).toBeInTheDocument();

    fireEvent.click(screen.getAllByText('Ubah harga')[0]);
    await waitFor(() => expect(screen.getByText(/Ubah harga —/)).toBeInTheDocument());

    fireEvent.click(screen.getByText('Simpan harga'));
    await waitFor(() => expect(screen.getByText('Harga produk diperbarui.')).toBeInTheDocument());
  });

  it('shows price history on product click', async () => {
    const { default: Page } = await import('@/app/admin/products/page');
    render(<Page />);

    await waitFor(() => expect(screen.getByText('Kopi Kapal')).toBeInTheDocument());
    fireEvent.click(screen.getByText('Kopi Kapal'));
    await waitFor(() => expect(screen.getByText(/Riwayat harga —/)).toBeInTheDocument());
  });

  // ── Cycle 1: default sort + summary + timestamps ─────────────────────────
  describe('default sort, summary strip and timestamp columns', () => {
    it('requests the default created_at DESC sort on first load', async () => {
      const { default: Page } = await import('@/app/admin/products/page');
      render(<Page />);
      await waitFor(() => expect(fetchMock).toHaveBeenCalled());

      const url = lastProductsUrl(fetchMock);
      expect(url).toContain('sort=created_at');
      expect(url).toContain('order=desc');
      expect(url).toContain('cursor=0');
    });

    it('renders rows newest-first (dummy products fall back to id DESC when created_at is absent)', async () => {
      // Dummy products carry no created_at, so the created_at DESC default must
      // fall back to the deterministic id DESC tiebreaker (nulls-last).
      const { fetchAdminProducts } = await import('@/app/admin/products/api');
      useDummyStore.getState().toggle();
      expect(useDummyStore.getState().isDummy).toBe(true);

      const result = await fetchAdminProducts('t-token', { limit: 200 });
      const ids = result.products.map((p) => p.id);
      expect(ids.length).toBeGreaterThan(1);
      const descending = [...ids].sort((a, b) => b - a);
      expect(ids).toEqual(descending);
      expect(result.products[0].id).toBe(Math.max(...ids));

      // Zero network while dummy mode is ON.
      expect(fetchMock.mock.calls.filter((c) => String(c[0]).includes('/products')).length).toBe(0);
    });

    it('renders the summary strip as "N produk · M stok habis"', async () => {
      fetchMock.mockImplementation(async (url: unknown) => {
        const urlString = String(url);
        if (urlString.includes('/products')) return jsonResponse(listResponse({ total: 2, outOfStock: 1 }));
        return jsonResponse({});
      });

      const { default: Page } = await import('@/app/admin/products/page');
      render(<Page />);

      await waitFor(() => {
        const summary = screen.getByTestId('table-summary').textContent ?? '';
        expect(summary).toContain('2 produk');
      });
      const summary = screen.getByTestId('table-summary').textContent ?? '';
      expect(summary).toContain('1 stok habis');
      expect(summary).toContain('\u00b7');
    });

    it('renders Dibuat/Diperbarui as formatted dates (not raw ISO)', async () => {
      fetchMock.mockImplementation(async (url: unknown) => {
        const urlString = String(url);
        if (urlString.includes('/products')) return jsonResponse(listResponse());
        return jsonResponse({});
      });

      const { default: Page } = await import('@/app/admin/products/page');
      render(<Page />);
      await waitFor(() => expect(screen.getByText('Kopi Kapal')).toBeInTheDocument());

      expect(screen.getByText('Dibuat')).toBeInTheDocument();
      expect(screen.getByText('Diperbarui')).toBeInTheDocument();
      expect(screen.queryByText('2026-09-05T08:00:00Z')).not.toBeInTheDocument();
      expect(screen.queryByText('2026-09-01T08:00:00Z')).not.toBeInTheDocument();
      expect(document.body.textContent ?? '').toMatch(/2026/);
    });

    it('renders an em dash for null timestamps instead of "Invalid Date"', async () => {
      fetchMock.mockImplementation(async (url: unknown) => {
        const urlString = String(url);
        if (urlString.includes('/products')) {
          return jsonResponse(listResponse({
            data: [{ id: 99, name: 'Produk Tanpa Tanggal', sku: 'SKU-099', price: '5000.00', stock_quantity: 1, created_at: null, updated_at: null }],
            total: 1,
            outOfStock: 0,
          }));
        }
        return jsonResponse({});
      });

      const { default: Page } = await import('@/app/admin/products/page');
      render(<Page />);
      await waitFor(() => expect(screen.getByText('Produk Tanpa Tanggal')).toBeInTheDocument());

      const row = screen.getByText('Produk Tanpa Tanggal').closest('tr') as HTMLTableRowElement;
      const cells = Array.from(row.querySelectorAll('td'));
      // Columns: name, sku, price, stock_quantity, created_at, updated_at, action
      expect(cells[4]).toHaveTextContent('\u2014');
      expect(cells[5]).toHaveTextContent('\u2014');
      expect(screen.queryByText('Invalid Date')).not.toBeInTheDocument();
    });
  });

  // ── Cycle 2: sort header click + search reset cursor + paging ────────────
  describe('sort header click + reset cursor + paging', () => {
    const pagedResponse = () => listResponse({ total: 100, outOfStock: 3, hasMore: true });

    beforeEach(() => {
      fetchMock.mockImplementation(async (url: unknown, options?: { method?: string }) => {
        const urlString = String(url);
        if (urlString.includes('/admin/products/') && urlString.includes('/prices')) return jsonResponse(historyResponse);
        if (urlString.includes('/admin/products') && (options?.method === 'PATCH' || options?.method === 'patch')) return jsonResponse(updateResponse);
        if (urlString.includes('/products')) return jsonResponse(pagedResponse());
        return jsonResponse({});
      });
    });

    it('clicking "Harga" sends sort=price&order=desc&cursor=0 then flips to order=asc', async () => {
      const { default: Page } = await import('@/app/admin/products/page');
      render(<Page />);
      await waitFor(() => expect(screen.getByText('Kopi Kapal')).toBeInTheDocument());

      fireEvent.click(screen.getByText('Harga'));
      await waitFor(() => {
        const url = lastProductsUrl(fetchMock);
        expect(url).toContain('sort=price');
        expect(url).toContain('order=desc');
        expect(url).toContain('cursor=0');
      });

      await waitFor(() => expect(screen.getByText('Harga')).toBeInTheDocument());
      fireEvent.click(screen.getByText('Harga'));
      await waitFor(() => {
        const url = lastProductsUrl(fetchMock);
        expect(url).toContain('sort=price');
        expect(url).toContain('order=asc');
        expect(url).toContain('cursor=0');
      });
    });

    it('changing search while on page >1 resets cursor to 0', async () => {
      const { default: Page } = await import('@/app/admin/products/page');
      render(<Page />);
      await waitFor(() => expect(screen.getByText('Kopi Kapal')).toBeInTheDocument());

      // Advance to page 2 (cursor=15).
      fireEvent.click(screen.getByText('Berikutnya'));
      await waitFor(() => expect(lastProductsUrl(fetchMock)).toContain('cursor=15'));

      const searchInput = screen.getByPlaceholderText('Nama atau SKU');
      fireEvent.change(searchInput, { target: { value: 'Kopi' } });
      fireEvent.click(screen.getByText('Cari'));

      await waitFor(() => {
        const url = lastProductsUrl(fetchMock);
        expect(url).toContain('search=Kopi');
        expect(url).toContain('cursor=0');
      });
    });

    it('paging preserves the active sort and filter', async () => {
      const { default: Page } = await import('@/app/admin/products/page');
      render(<Page />);
      await waitFor(() => expect(screen.getByText('Kopi Kapal')).toBeInTheDocument());

      const searchInput = screen.getByPlaceholderText('Nama atau SKU');
      fireEvent.change(searchInput, { target: { value: 'Kopi' } });
      fireEvent.click(screen.getByText('Cari'));
      await waitFor(() => expect(lastProductsUrl(fetchMock)).toContain('search=Kopi'));

      await waitFor(() => expect(screen.getByText('Harga')).toBeInTheDocument());
      fireEvent.click(screen.getByText('Harga'));
      await waitFor(() => expect(lastProductsUrl(fetchMock)).toContain('sort=price'));

      await waitFor(() => expect(screen.getByText('Berikutnya')).toBeInTheDocument());
      fireEvent.click(screen.getByText('Berikutnya'));
      await waitFor(() => {
        const url = lastProductsUrl(fetchMock);
        expect(url).toContain('cursor=15');
        expect(url).toContain('sort=price');
        expect(url).toContain('search=Kopi');
      });
    });

    it('marks the default created_at header aria-sort="descending" and leaves Aksi inert', async () => {
      const { default: Page } = await import('@/app/admin/products/page');
      render(<Page />);
      await waitFor(() => expect(screen.getByText('Kopi Kapal')).toBeInTheDocument());

      const createdHeader = () => screen.getByText('Dibuat').closest('th') as HTMLTableCellElement;
      expect(createdHeader()).toHaveAttribute('aria-sort', 'descending');

      fireEvent.click(createdHeader());
      await waitFor(() => {
        const url = lastProductsUrl(fetchMock);
        expect(url).toContain('sort=created_at');
        expect(url).toContain('order=asc');
        expect(url).toContain('cursor=0');
      });
      await waitFor(() => expect(createdHeader()).toHaveAttribute('aria-sort', 'ascending'));

      const actionHeader = screen.getByText('Aksi').closest('th') as HTMLTableCellElement;
      expect(actionHeader).not.toHaveAttribute('aria-sort');
      const callsBefore = fetchMock.mock.calls.length;
      fireEvent.click(actionHeader);
      await act(async () => { await Promise.resolve(); });
      expect(fetchMock.mock.calls.length).toBe(callsBefore);
    });
  });
});

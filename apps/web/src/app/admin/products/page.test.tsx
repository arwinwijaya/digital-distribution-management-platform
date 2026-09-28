import React from 'react';
import '@testing-library/jest-dom';
import { render, screen, waitFor, fireEvent, act, within } from '@testing-library/react';

const mockGetStoredToken = jest.fn(() => 'test-token');
jest.mock('@/lib/api', () => ({
  apiUrl: (p: string) => `http://localhost:8000/api${p}`,
  authHeaders: (token: string) => ({ Authorization: `Bearer ${token}` }),
  getStoredToken: (...args: unknown[]) => (mockGetStoredToken as (...a: unknown[]) => string | null)(...args),
}));
import { useDummyStore } from '@/dummy/store';
import { installDummy } from '@/dummy/install';
import { SORT_ALLOWLISTS } from '@/lib/admin-table';

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
function listResponse(overrides?: { data?: unknown[]; total?: number; outOfStock?: number; hasMore?: boolean; categories?: string[] }) {
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
      ...(overrides?.categories ? { categories: overrides.categories } : {}),
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

  it('expands the local product detail on name trigger and keeps price action independent', async () => {
    const { default: Page } = await import('@/app/admin/products/page');
    render(<Page />);

    await waitFor(() => expect(screen.getByText('Kopi Kapal')).toBeInTheDocument());

    // Closed: trigger collapsed, no detail panel, aria-controls points at the panel id.
    const trigger = screen.getByRole('button', { name: 'Kopi Kapal' });
    expect(trigger).toHaveAttribute('aria-expanded', 'false');
    expect(trigger).toHaveAttribute('aria-controls', 'product-detail-1');
    expect(screen.queryByText('Nilai stok')).not.toBeInTheDocument();

    // Keyboard: Enter opens the panel, focus stays on the trigger.
    trigger.focus();
    fireEvent.keyDown(trigger, { key: 'Enter' });
    await waitFor(() => expect(trigger).toHaveAttribute('aria-expanded', 'true'));
    expect(trigger).toHaveAttribute('aria-controls', 'product-detail-1');
    expect(document.getElementById('product-detail-1')).toBeInTheDocument();
    expect(screen.getByText(/Nilai stok/)).toBeInTheDocument();
    expect(document.activeElement).toBe(trigger);

    // Space closes it again.
    fireEvent.keyDown(trigger, { key: ' ' });
    await waitFor(() => expect(trigger).toHaveAttribute('aria-expanded', 'false'));
    expect(document.getElementById('product-detail-1')).not.toBeInTheDocument();

    // Independent price action: opens the price editor without expanding the row.
    const priceAction = screen.getAllByRole('button', { name: 'Ubah harga' })[0];
    fireEvent.click(priceAction);
    await waitFor(() => expect(screen.getByText(/Ubah harga —/)).toBeInTheDocument());
    expect(trigger).toHaveAttribute('aria-expanded', 'false');
    expect(screen.queryByText('Nilai stok')).not.toBeInTheDocument();
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
      // Columns: name, sku, category, status, price, stock, created_at, updated_at, action
      expect(cells[6]).toHaveTextContent('\u2014');
      expect(cells[7]).toHaveTextContent('\u2014');
      expect(screen.queryByText('Invalid Date')).not.toBeInTheDocument();
    });
  });

  // ── Cycle 1: identity columns, fallbacks and status precedence ──────────
  describe('identity columns, fallbacks and status precedence', () => {
    const identityData = [
      {
        id: 11,
        name: 'Kopi Kapal',
        sku: 'SKU-001',
        category: 'Minuman',
        price: '15000.00',
        stock_quantity: 40,
        is_active: true,
        supplier: { id: 1, name: 'PT Segar', subscription_status: 'active' },
        created_at: '2026-09-05T08:00:00Z',
        updated_at: '2026-09-10T10:00:00Z',
      },
      {
        id: 12,
        name: 'Gula Pasir 1kg',
        sku: 'SKU-002',
        category: null,
        price: '19000.00',
        stock_quantity: null,
        is_active: true,
        supplier: { id: 1, name: 'PT Segar', subscription_status: 'active' },
        created_at: null,
        updated_at: null,
      },
      {
        id: 13,
        name: 'Beras Premium',
        sku: 'SKU-003',
        category: 'Sembako',
        price: '65000.00',
        stock_quantity: 5,
        is_active: true,
        supplier: { id: 2, name: 'CV Tani', subscription_status: 'expired' },
        created_at: null,
        updated_at: null,
      },
      {
        id: 14,
        name: 'Produk Lama',
        sku: 'SKU-004',
        category: 'Sembako',
        price: '10000.00',
        stock_quantity: 0,
        is_active: false,
        supplier: { id: 2, name: 'CV Tani', subscription_status: 'expired' },
        created_at: null,
        updated_at: null,
      },
      {
        id: 15,
        name: 'Tanpa Supplier',
        sku: 'SKU-005',
        category: 'Lain',
        price: '20000.00',
        stock_quantity: 3,
        is_active: true,
        supplier: null,
        created_at: null,
        updated_at: null,
      },
    ];

    beforeEach(() => {
      fetchMock.mockImplementation(async (url: unknown) => {
        const urlString = String(url);
        if (urlString.includes('/products')) {
          return jsonResponse({
            status: 'success',
            data: identityData,
            meta: {
              has_more: false,
              limit: 15,
              cursor: 0,
              total: identityData.length,
              summary: { total: identityData.length, out_of_stock: 1 },
            },
          });
        }
        return jsonResponse({});
      });
    });

    it('renders the required identity headers in order', async () => {
      const { default: Page } = await import('@/app/admin/products/page');
      render(<Page />);
      await waitFor(() => expect(screen.getByText('Kopi Kapal')).toBeInTheDocument());

      const headers = Array.from(document.querySelectorAll('thead th')).map((th) => th.textContent ?? '');
      // Sortable headers append a direction glyph; strip it for comparison.
      const labels = headers.map((h) => h.replace(/[↑↓↕]/g, '').trim());
      expect(labels).toEqual(['Nama', 'SKU', 'Kategori', 'Status', 'Harga Jual', 'Stok (unit)', 'Dibuat', 'Diperbarui', 'Aksi']);
    });

    it('shows the category cell with an em dash fallback for null/empty category', async () => {
      const { default: Page } = await import('@/app/admin/products/page');
      render(<Page />);
      await waitFor(() => expect(screen.getByText('Kopi Kapal')).toBeInTheDocument());

      const cellsOf = (name: string) =>
        Array.from((screen.getByText(name).closest('tr') as HTMLTableRowElement).querySelectorAll('td'));

      expect(cellsOf('Kopi Kapal')[2]).toHaveTextContent('Minuman');
      expect(cellsOf('Gula Pasir 1kg')[2]).toHaveTextContent('\u2014');
      expect(cellsOf('Gula Pasir 1kg')[2]).not.toHaveTextContent('Minuman');
    });

    it('explains the order price via a tooltip on the price column', async () => {
      const { default: Page } = await import('@/app/admin/products/page');
      render(<Page />);
      await waitFor(() => expect(screen.getByText('Kopi Kapal')).toBeInTheDocument());

      const tooltips = screen.getAllByTitle('Harga jual yang digunakan dalam order');
      expect(tooltips.length).toBeGreaterThan(0);
      expect(tooltips[0]).toHaveTextContent(/Rp\s*15\.000/);
    });

    it('renders null stock as normalized 0 with Habis', async () => {
      const { default: Page } = await import('@/app/admin/products/page');
      render(<Page />);
      await waitFor(() => expect(screen.getByText('Gula Pasir 1kg')).toBeInTheDocument());

      const row = screen.getByText('Gula Pasir 1kg').closest('tr') as HTMLTableRowElement;
      const stockCell = row.querySelectorAll('td')[5];
      expect(stockCell).toHaveTextContent('0');
      expect(stockCell).toHaveTextContent('Habis');
      expect(stockCell).not.toHaveTextContent('\u2014');
    });

    it('derives one main status badge with Nonaktif > Tidak bisa dibeli > Aktif precedence', async () => {
      const { default: Page } = await import('@/app/admin/products/page');
      render(<Page />);
      await waitFor(() => expect(screen.getByText('Kopi Kapal')).toBeInTheDocument());

      const statusOf = (name: string) => {
        const row = screen.getByText(name).closest('tr') as HTMLTableRowElement;
        return row.querySelectorAll('td')[3].textContent ?? '';
      };

      expect(statusOf('Kopi Kapal').trim()).toBe('Aktif');
      expect(statusOf('Tanpa Supplier').trim()).toBe('Aktif');
      expect(statusOf('Beras Premium').trim()).toBe('Tidak bisa dibeli');
      expect(statusOf('Produk Lama').trim()).toBe('Nonaktif');
      expect(statusOf('Produk Lama')).not.toContain('Tidak bisa dibeli');
    });
  });

  // ── T4 Cycle 1: server-side filters + reset/summary/options ─────────────
  describe('server-side product filters', () => {
    it('renders complete status and stock health options with correct labels and API values', async () => {
      fetchMock.mockImplementation(async (url: unknown) => {
        const requestUrl = new URL(String(url));
        if (requestUrl.pathname.endsWith('/products')) {
          const response = listResponse({ data: [], total: 0, outOfStock: 0, hasMore: false });
          response.meta.categories = ['Minuman'];
          return jsonResponse(response);
        }
        return jsonResponse({});
      });

      const { default: Page } = await import('@/app/admin/products/page');
      render(<Page />);
      await waitFor(() => expect(screen.getByLabelText('Status')).toBeInTheDocument());

      // Wait for categories to load from metadata
      await waitFor(() => expect(screen.getByRole('option', { name: 'Minuman' })).toBeInTheDocument());

      const statusSelect = within(screen.getByLabelText('Status'));
      expect(statusSelect.getByRole('option', { name: 'Semua' })).toBeInTheDocument();
      expect(statusSelect.getByRole('option', { name: 'Aktif' })).toBeInTheDocument();
      expect(statusSelect.getByRole('option', { name: 'Nonaktif' })).toBeInTheDocument();
      expect(statusSelect.getByRole('option', { name: 'Tidak bisa dibeli' })).toBeInTheDocument();

      const healthSelect = within(screen.getByLabelText('Kesehatan stok'));
      expect(healthSelect.getByRole('option', { name: 'Semua' })).toBeInTheDocument();
      expect(healthSelect.getByRole('option', { name: 'Habis' })).toBeInTheDocument();
      expect(healthSelect.getByRole('option', { name: 'Rendah' })).toBeInTheDocument();
      expect(healthSelect.getByRole('option', { name: 'Aman' })).toBeInTheDocument();

      const categorySelect = within(screen.getByLabelText('Kategori'));
      expect(categorySelect.getByRole('option', { name: 'Semua kategori' })).toBeInTheDocument();
      expect(categorySelect.getByRole('option', { name: 'Minuman' })).toBeInTheDocument();
      expect(categorySelect.getByRole('option', { name: 'Tanpa kategori' })).toBeInTheDocument();

      // Selecting each status sends the exact backend value
      fireEvent.change(screen.getByLabelText('Status'), { target: { value: 'active' } });
      await waitFor(() => expect(lastProductsUrl(fetchMock)).toContain('status=active'));
      fireEvent.change(screen.getByLabelText('Status'), { target: { value: 'inactive' } });
      await waitFor(() => expect(lastProductsUrl(fetchMock)).toContain('status=inactive'));
      fireEvent.change(screen.getByLabelText('Status'), { target: { value: 'unpurchasable' } });
      await waitFor(() => expect(lastProductsUrl(fetchMock)).toContain('status=unpurchasable'));

      fireEvent.change(screen.getByLabelText('Kesehatan stok'), { target: { value: 'out' } });
      await waitFor(() => expect(lastProductsUrl(fetchMock)).toContain('stock_health=out'));
      fireEvent.change(screen.getByLabelText('Kesehatan stok'), { target: { value: 'low' } });
      await waitFor(() => expect(lastProductsUrl(fetchMock)).toContain('stock_health=low'));
      fireEvent.change(screen.getByLabelText('Kesehatan stok'), { target: { value: 'ok' } });
      await waitFor(() => expect(lastProductsUrl(fetchMock)).toContain('stock_health=ok'));
    });

    it('selecting Tanpa kategori sends the explicit no-category sentinel and filters to empty rows', async () => {
      fetchMock.mockImplementation(async (url: unknown) => {
        const requestUrl = new URL(String(url));
        if (requestUrl.pathname.endsWith('/products')) {
          const isNoCat = requestUrl.searchParams.get('category') === '__none__';
          return jsonResponse(listResponse({
            data: isNoCat ? [{ id: 99, name: 'Uncategorized', sku: 'SKU-99', category: '', price: '1000.00', stock_quantity: 3 }] : [],
            total: isNoCat ? 1 : 0,
            outOfStock: 0,
            hasMore: false,
            categories: ['Minuman'],
          }));
        }
        return jsonResponse({});
      });

      const { default: Page } = await import('@/app/admin/products/page');
      render(<Page />);
      await waitFor(() => expect(screen.getByLabelText('Kategori')).toBeInTheDocument());
      fireEvent.change(screen.getByLabelText('Kategori'), { target: { value: '__none__' } });
      await waitFor(() => {
        const url = lastProductsUrl(fetchMock);
        expect(url).toContain('category=__none__');
      });
      await waitFor(() => expect(screen.getByText('Uncategorized')).toBeInTheDocument());
    });

    it('excludes invalid status/stock_health/category values from requests', async () => {
      fetchMock.mockImplementation(async (url: unknown) => {
        if (String(url).includes('/products')) {
          return jsonResponse(listResponse({ data: [], total: 0, outOfStock: 0, hasMore: false }));
        }
        return jsonResponse({});
      });

      const { default: Page } = await import('@/app/admin/products/page');
      render(<Page />);
      await waitFor(() => expect(screen.getByLabelText('Status')).toBeInTheDocument());

      // Invalid status via direct onChange with bogus value (not in option list)
      fireEvent.change(screen.getByLabelText('Status'), { target: { value: 'bogus' } });
      await waitFor(() => {
        const url = lastProductsUrl(fetchMock);
        expect(url).not.toContain('status=');
      });

      // Invalid stock health
      fireEvent.change(screen.getByLabelText('Kesehatan stok'), { target: { value: 'never' } });
      await waitFor(() => {
        const url = lastProductsUrl(fetchMock);
        expect(url).not.toContain('stock_health=');
      });

      // Invalid category (not in metadata) — setting value not present in options
      fireEvent.change(screen.getByLabelText('Kategori'), { target: { value: 'DoesNotExist' } });
      await waitFor(() => {
        const url = lastProductsUrl(fetchMock);
        // category=DoesNotExist would be sent but backend ignores invalid scalars; frontend should not include it
        // For our implementation, we validate against metadata — expect it to be excluded
        // But since backend ignores, the key behavior is frontend doesn't store invalid state
        // Check that our URL doesn't contain it (we guard in adapter)
        expect(url).not.toContain('category=DoesNotExist');
      });
    });

    it('clearing a filter back to Semua removes its param instead of re-sending the old value', async () => {
      fetchMock.mockImplementation(async (url: unknown) => {
        if (String(url).includes('/products')) {
          return jsonResponse(listResponse({ data: [], total: 0, outOfStock: 0, hasMore: false, categories: ['Minuman'] }));
        }
        return jsonResponse({});
      });

      const { default: Page } = await import('@/app/admin/products/page');
      render(<Page />);
      await waitFor(() => expect(screen.getByLabelText('Status')).toBeInTheDocument());

      // Apply each filter, then clear it back to its "Semua" option.
      fireEvent.change(screen.getByLabelText('Status'), { target: { value: 'active' } });
      await waitFor(() => expect(lastProductsUrl(fetchMock)).toContain('status=active'));
      fireEvent.change(screen.getByLabelText('Status'), { target: { value: '' } });
      await waitFor(() => expect(lastProductsUrl(fetchMock)).not.toContain('status='));

      fireEvent.change(screen.getByLabelText('Kesehatan stok'), { target: { value: 'low' } });
      await waitFor(() => expect(lastProductsUrl(fetchMock)).toContain('stock_health=low'));
      fireEvent.change(screen.getByLabelText('Kesehatan stok'), { target: { value: '' } });
      await waitFor(() => expect(lastProductsUrl(fetchMock)).not.toContain('stock_health='));

      fireEvent.change(screen.getByLabelText('Kategori'), { target: { value: 'Minuman' } });
      await waitFor(() => expect(lastProductsUrl(fetchMock)).toContain('category=Minuman'));
      fireEvent.change(screen.getByLabelText('Kategori'), { target: { value: '' } });
      await waitFor(() => expect(lastProductsUrl(fetchMock)).not.toContain('category='));
    });

    it('combines filters, resets cursor, preserves sort, closes expansion, and renders filtered metadata', async () => {
      fetchMock.mockImplementation(async (url: unknown) => {
        const requestUrl = new URL(String(url));
        if (requestUrl.pathname.endsWith('/products')) {
          const filtered = requestUrl.searchParams.get('category') === 'Minuman'
            && requestUrl.searchParams.get('status') === 'active'
            && requestUrl.searchParams.get('stock_health') === 'low';
          const response = listResponse({
            data: filtered
              ? [{ id: 77, name: 'Teh Filtered', sku: 'SKU-077', category: 'Minuman', price: '12000.00', stock_quantity: 5 }]
              : [{ id: 2, name: 'Kopi Kapal', sku: 'SKU-001', price: '15000.00', stock_quantity: 40 }],
            total: filtered ? 1 : 100,
            outOfStock: filtered ? 0 : 1,
            hasMore: !filtered,
          });
          response.meta.categories = ['Minuman', 'Sembako'];
          return jsonResponse(response);
        }
        return jsonResponse({});
      });

      const { default: Page } = await import('@/app/admin/products/page');
      render(<Page />);
      await waitFor(() => expect(screen.getByText('Kopi Kapal')).toBeInTheDocument());

      fireEvent.click(screen.getByText('Harga Jual'));
      await waitFor(() => expect(lastProductsUrl(fetchMock)).toContain('sort=price'));
      fireEvent.click(screen.getByText('Berikutnya'));
      await waitFor(() => expect(lastProductsUrl(fetchMock)).toContain('cursor=15'));

      const trigger = screen.getByRole('button', { name: 'Kopi Kapal' });
      fireEvent.click(trigger);
      await waitFor(() => expect(trigger).toHaveAttribute('aria-expanded', 'true'));

      await waitFor(() => expect(screen.getByLabelText('Kategori')).toBeInTheDocument());
      expect(screen.getByRole('option', { name: 'Semua kategori' })).toBeInTheDocument();
      expect(screen.getByRole('option', { name: 'Minuman' })).toBeInTheDocument();
      expect(screen.getByRole('option', { name: 'Sembako' })).toBeInTheDocument();
      expect(screen.getByRole('option', { name: 'Tanpa kategori' })).toBeInTheDocument();

      fireEvent.change(screen.getByLabelText('Kategori'), { target: { value: 'Minuman' } });
      fireEvent.change(screen.getByLabelText('Status'), { target: { value: 'active' } });
      fireEvent.change(screen.getByLabelText('Kesehatan stok'), { target: { value: 'low' } });

      await waitFor(() => {
        const url = lastProductsUrl(fetchMock);
        expect(url).toContain('category=Minuman');
        expect(url).toContain('status=active');
        expect(url).toContain('stock_health=low');
        expect(url).toContain('cursor=0');
        expect(url).toContain('sort=price');
        expect(url).toContain('order=desc');
      });
      await waitFor(() => expect(screen.getByText('Teh Filtered')).toBeInTheDocument());
      expect(screen.queryByText('Nilai stok')).not.toBeInTheDocument();
      expect(screen.getByTestId('table-summary')).toHaveTextContent('1 produk');
      expect(screen.getByTestId('table-summary')).toHaveTextContent('0 stok habis');
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

      fireEvent.click(screen.getByText('Harga Jual'));
      await waitFor(() => {
        const url = lastProductsUrl(fetchMock);
        expect(url).toContain('sort=price');
        expect(url).toContain('order=desc');
        expect(url).toContain('cursor=0');
      });

      await waitFor(() => expect(screen.getByText('Harga Jual')).toBeInTheDocument());
      fireEvent.click(screen.getByText('Harga Jual'));
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

      await waitFor(() => expect(screen.getByText('Harga Jual')).toBeInTheDocument());
      fireEvent.click(screen.getByText('Harga Jual'));
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

  // ── Cycle 3: adapter contract + dummy parity ────────────────────────────
  describe('adapter contract + dummy parity', () => {
    it('serializes clarity filters and parses supplier/meta contract in real mode', async () => {
      fetchMock.mockImplementation(async (url: unknown) => {
        if (String(url).includes('/products')) {
          return jsonResponse({
            status: 'success',
            data: [{
              id: 7,
              name: 'Teh Botol',
              category: 'Minuman',
              is_active: true,
              stock_quantity: 5,
              supplier: { id: 4, name: 'PT Segar', subscription_status: 'active' },
            }],
            meta: {
              has_more: false,
              limit: 15,
              cursor: 0,
              total: 1,
              summary: { total: 1, out_of_stock: 0 },
              categories: ['Minuman'],
            },
          });
        }
        return jsonResponse({});
      });

      const { fetchAdminProducts } = await import('@/app/admin/products/api');
      const result = await fetchAdminProducts('t-token', {
        category: 'Minuman',
        status: 'active',
        stockHealth: 'low',
        sort: 'status',
        order: 'asc',
        cursor: 0,
      });

      const url = new URL(lastProductsUrl(fetchMock));
      expect(url.searchParams.get('category')).toBe('Minuman');
      expect(url.searchParams.get('status')).toBe('active');
      expect(url.searchParams.get('stock_health')).toBe('low');
      expect(url.searchParams.get('sort')).toBe('status');
      expect(url.searchParams.get('order')).toBe('asc');
      expect(url.searchParams.get('cursor')).toBe('0');
      expect(url.searchParams.get('include_unpurchasable')).toBe('1');
      expect(result.products[0].supplier).toEqual({ id: 4, name: 'PT Segar', subscription_status: 'active' });
      expect(result.categories).toEqual(['Minuman']);
      expect(result.summary).toEqual({ total: 1, out_of_stock: 0 });
      expect(result.hasMore).toBe(false);
    });

    it('keeps category/status sorting synchronized with the backend allowlist', async () => {
      expect(SORT_ALLOWLISTS.products).toEqual(expect.arrayContaining(['category', 'status']));
    });

    it('keeps dummy filters/sort/categories/summary parity without network calls', async () => {
      const { fetchAdminProducts } = await import('@/app/admin/products/api');
      useDummyStore.getState().toggle();
      expect(useDummyStore.getState().isDummy).toBe(true);

      const all = await fetchAdminProducts('t-token', { limit: 200 });
      expect(all.categories).toEqual(Array.from(new Set(all.products.map((p) => p.category).filter(Boolean))).sort());
      expect(all.products.every((p) => p.supplier === null || p.supplier?.subscription_status)).toBe(true);

      const category = all.products.find((p) => p.category)?.category;
      expect(category).toBeTruthy();
      const filtered = await fetchAdminProducts('t-token', {
        category,
        status: 'active',
        stockHealth: 'low',
        sort: 'status',
        order: 'asc',
        limit: 200,
      });
      expect(filtered.products.every((p) => p.category?.trim() === category)).toBe(true);
      expect(filtered.products.every((p) => p.is_active === true)).toBe(true);
      expect(filtered.products.every((p) => (p.stock_quantity ?? 0) >= 1 && (p.stock_quantity ?? 0) < 11)).toBe(true);
      expect(filtered.summary).toEqual({
        total: filtered.total,
        out_of_stock: filtered.products.filter((p) => (p.stock_quantity ?? 0) <= 0).length,
      });
      expect(fetchMock.mock.calls.filter((c) => String(c[0]).includes('/products')).length).toBe(0);
    });

    it('density toggle changes the table padding class and persists to localStorage', async () => {
      fetchMock.mockImplementation(async (url: unknown) => {
        const urlString = String(url);
        if (urlString.includes('/products')) return jsonResponse(listResponse());
        return jsonResponse({});
      });

      const { default: Page } = await import('@/app/admin/products/page');
      render(<Page />);
      await waitFor(() => expect(screen.getByText('Kopi Kapal')).toBeInTheDocument());

      let headerCell = screen.getByText('Nama', { selector: 'th' }).closest('th') as HTMLTableCellElement;
      expect(headerCell).toHaveClass('py-3');

      fireEvent.click(screen.getByRole('button', { name: 'Compact' }));
      await waitFor(() => {
        headerCell = screen.getByText('Nama', { selector: 'th' }).closest('th') as HTMLTableCellElement;
        expect(headerCell).toHaveClass('py-2');
      });
      expect(localStorage.getItem('admin:table-density')).toBe('compact');

      fireEvent.click(screen.getByRole('button', { name: 'Comfortable' }));
      await waitFor(() => {
        headerCell = screen.getByText('Nama', { selector: 'th' }).closest('th') as HTMLTableCellElement;
        expect(headerCell).toHaveClass('py-4');
      });
      expect(localStorage.getItem('admin:table-density')).toBe('comfortable');
    });

    it('dummy listDummyAdminProducts mirrors sort + offset slice + total + summary', async () => {
      const { fetchAdminProducts } = await import('@/app/admin/products/api');
      const { compareRows } = await import('@/lib/admin-table');
      useDummyStore.getState().toggle();
      expect(useDummyStore.getState().isDummy).toBe(true);

      // Default: created_at DESC → id DESC fallback (dummy products have no created_at).
      const all = await fetchAdminProducts('t-token', { limit: 200 });
      const expectedDefault = [...all.products].sort((a, b) =>
        compareRows(a as unknown as Record<string, unknown>, b as unknown as Record<string, unknown>, 'created_at', 'desc'),
      );
      expect(all.products.map((p) => p.id)).toEqual(expectedDefault.map((p) => p.id));
      expect(all.total).toBe(all.products.length);
      expect(all.summary).toEqual({
        total: all.products.length,
        out_of_stock: all.products.filter((p) => (p.stock_quantity ?? 0) <= 0).length,
      });

      // Offset slice: cursor 5, limit 5 → ids 6..10 in id DESC order.
      const page = await fetchAdminProducts('t-token', { limit: 5, cursor: 5 });
      expect(page.products.map((p) => p.id)).toEqual(all.products.slice(5, 10).map((p) => p.id));
      expect(page.hasMore).toBe(all.products.length > 10);

      // Explicit name ASC matches compareRows('name','asc').
      const byName = await fetchAdminProducts('t-token', { limit: 200, sort: 'name', order: 'asc' });
      const expectedByName = [...byName.products].sort((a, b) =>
        compareRows(a as unknown as Record<string, unknown>, b as unknown as Record<string, unknown>, 'name', 'asc'),
      );
      expect(byName.products.map((p) => p.id)).toEqual(expectedByName.map((p) => p.id));

      // Zero network while dummy mode is ON.
      expect(fetchMock.mock.calls.filter((c) => String(c[0]).includes('/products')).length).toBe(0);
    });

    it('renders the summary strip from dummy data (zero network)', async () => {
      useDummyStore.getState().toggle();
      const { default: Page } = await import('@/app/admin/products/page');
      render(<Page />);

      await waitFor(() => {
        const summary = screen.getByTestId('table-summary').textContent ?? '';
        expect(summary).toMatch(/\d+ produk/);
        expect(summary).toContain('stok habis');
      });
      expect(fetchMock.mock.calls.filter((c) => String(c[0]).includes('/products')).length).toBe(0);
    });
  });
});

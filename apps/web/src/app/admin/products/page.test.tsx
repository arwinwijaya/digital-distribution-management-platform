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

describe('admin products page', () => {
  let originalFetch: typeof fetch | undefined;

  beforeEach(() => {
    useDummyStore.getState().reset();
    installDummy();
    mockGetStoredToken.mockReturnValue('test-token');
    originalFetch = (globalThis as unknown as { fetch?: typeof fetch }).fetch;
    (globalThis as unknown as { fetch: unknown }).fetch = jest.fn(async (url: unknown, options?: { method?: string }) => {
      const urlString = String(url);
      if (urlString.includes('/admin/products/') && urlString.includes('/prices')) return { ok: true, status: 200, json: async () => historyResponse } as Response;
      if (urlString.includes('/admin/products') && (options?.method === 'PATCH' || options?.method === 'patch')) return { ok: true, status: 200, json: async () => updateResponse } as Response;
      if (urlString.includes('/products')) return { ok: true, status: 200, json: async () => productsResponse } as Response;
      return { ok: true, status: 200, json: async () => ({}) } as Response;
    });
  });

  afterEach(() => {
    if (originalFetch) (globalThis as unknown as { fetch: unknown }).fetch = originalFetch;
    else delete (globalThis as unknown as { fetch?: unknown }).fetch;
    jest.restoreAllMocks();
  });

  it('memuat ulang data saat Mode Dummy diaktifkan', async () => {
    const api = await import('@/app/admin/products/api');
    const spy = jest.spyOn(api, 'fetchProducts');
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
});

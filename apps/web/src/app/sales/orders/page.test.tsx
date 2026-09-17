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
  data: [
    { id: 1, name: 'Warung Asri', category: 'warung', city: 'Jakarta', district: 'Cilandak', territory_id: 1 },
    { id: 2, name: 'Toko Sejahtera', category: 'grosir', city: 'Jakarta', district: 'Pasarminggu', territory_id: 1 },
  ],
};

const productsResponse = {
  status: 'success',
  data: [
    { id: 101, name: 'Beras Premium', price: '20000.00', stock_quantity: 50 },
    { id: 102, name: 'Minyak Goreng', price: '25000.00', stock_quantity: 30 },
  ],
};

const createdOrderResponse = {
  status: 'success',
  data: {
    id: 7,
    order_id: 'ORD-0007',
    outlet_id: 1,
    sales_user_id: 3,
    status: 'New',
    total_amount: '40000.00',
    items: [
      { id: 1, product_id: 101, product_name: 'Beras Premium', quantity: 2, unit_price: '20000.00', subtotal: '40000.00' },
    ],
  },
};

describe('sales orders page', () => {
  let originalFetch: typeof fetch | undefined;

  beforeEach(() => {
    useDummyStore.getState().reset();
    installDummy();
    mockGetStoredToken.mockReturnValue('test-token');
    originalFetch = (globalThis as unknown as { fetch?: typeof fetch }).fetch;
    (globalThis as unknown as { fetch: unknown }).fetch = jest.fn(async (url: unknown, options?: { method?: string; body?: string }) => {
      const urlString = String(url);
      if (urlString.includes('/sales/orders') && options?.method === 'POST') {
        return { ok: true, status: 201, json: async () => createdOrderResponse } as Response;
      }
      if (urlString.includes('/sales/outlets')) {
        return { ok: true, status: 200, json: async () => outletsResponse } as Response;
      }
      if (urlString.includes('/products')) {
        return { ok: true, status: 200, json: async () => productsResponse } as Response;
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
    const api = await import('@/app/sales/orders/api');
    const spy = jest.spyOn(api, 'fetchSalesOutlets');
    const { default: Page } = await import('@/app/sales/orders/page');
    render(<Page />);

    await waitFor(() => expect(spy).toHaveBeenCalled());
    const before = spy.mock.calls.length;

    act(() => {
      useDummyStore.getState().toggle();
    });

    await waitFor(() => expect(spy.mock.calls.length).toBeGreaterThan(before));
  });

  it('renders territory-scoped outlet selector sourced from GET /sales/outlets', async () => {
    const { default: Page } = await import('@/app/sales/orders/page');
    render(<Page />);

    await waitFor(() => {
      expect(screen.getByText((t) => t.includes('Warung Asri'))).toBeInTheDocument();
    });
    expect(screen.getByText((t) => t.includes('Toko Sejahtera'))).toBeInTheDocument();
    expect(screen.getByText('Buat pesanan sales')).toBeInTheDocument();

    const fetchMock = (globalThis as unknown as { fetch: jest.Mock }).fetch;
    const urls = fetchMock.mock.calls.map((c) => String(c[0]));
    expect(urls.some((u) => u.includes('/sales/outlets'))).toBe(true);
    // Never calls unrestricted admin outlet listing
    expect(urls.some((u) => u.includes('/admin/outlets'))).toBe(false);
  });

  it('submits order via POST /sales/orders with selected outlet and quantities', async () => {
    const { default: Page } = await import('@/app/sales/orders/page');
    render(<Page />);

    await waitFor(() => {
      expect(screen.getByText((t) => t.includes('Warung Asri'))).toBeInTheDocument();
    });

    const select = screen.getByLabelText('Pilih outlet (wilayah Anda)') as HTMLSelectElement;
    fireEvent.change(select, { target: { value: '1' } });

    const qtyInput = screen.getByLabelText('Jumlah Beras Premium') as HTMLInputElement;
    fireEvent.change(qtyInput, { target: { value: '2' } });

    fireEvent.click(screen.getByText('Buat pesanan'));

    await waitFor(() => {
      expect(screen.getByText(/ORD-0007/)).toBeInTheDocument();
    });

    const fetchMock = (globalThis as unknown as { fetch: jest.Mock }).fetch;
    const postCalls = fetchMock.mock.calls.filter((c) => String(c[0]).includes('/sales/orders') && c[1]?.method === 'POST');
    expect(postCalls.length).toBeGreaterThan(0);
    const body = JSON.parse(String(postCalls[0][1].body));
    expect(body.outlet_id).toBe(1);
    expect(body.items).toEqual([{ product_id: 101, quantity: 2 }]);
  });

  it('shows territory assignment warning when backend returns 403', async () => {
    const original = mockGetStoredToken.mockReturnValue('test-token');
    (globalThis as unknown as { fetch: unknown }).fetch = jest.fn(async (url: unknown) => {
      const urlString = String(url);
      if (urlString.includes('/sales/outlets')) {
        return { ok: false, status: 403, json: async () => ({ status: 'error', message: 'Sales user must be assigned to a territory.' }) } as Response;
      }
      return { ok: true, status: 200, json: async () => ({ status: 'success', data: [] }) } as Response;
    });

    const { default: Page } = await import('@/app/sales/orders/page');
    render(<Page />);

    await waitFor(() => {
      expect(screen.getByText(/Sales user must be assigned to a territory/)).toBeInTheDocument();
    });
  });
});

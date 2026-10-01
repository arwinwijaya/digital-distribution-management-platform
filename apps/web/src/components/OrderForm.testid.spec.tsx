import React from 'react';
import '@testing-library/jest-dom';
import { act, fireEvent, render, screen, waitFor } from '@testing-library/react';

jest.mock('@/lib/api', () => ({
  apiUrl: (path: string) => `http://localhost:8000/api${path}`,
  authHeaders: (token: string) => ({ Authorization: `Bearer ${token}` }),
  getStoredToken: () => 'test-token',
}));

import { useDummyStore } from '@/dummy/store';
import { installDummy } from '@/dummy/install';
import OrderForm from '@/components/OrderForm';

const productsResponse = {
  status: 'success',
  data: [
    { id: 1, name: 'Beras Premium', price: '20000.00', stock_quantity: 50, is_active: true },
    { id: 2, name: 'Minyak Goreng', price: '25000.00', stock_quantity: 30, is_active: true },
  ],
};

const orderCreatedResponse = {
  status: 'success',
  data: {
    id: 9001,
    order_id: 'ORD-TEST-001',
    status: 'New',
    total_amount: '65000.00',
    items: [
      { product_name: 'Beras Premium', quantity: 2, subtotal: '40000.00' },
      { product_name: 'Minyak Goreng', quantity: 1, subtotal: '25000.00' },
    ],
    status_history: [],
  },
};

describe('OrderForm — data-testid contract', () => {
  let originalFetch: typeof fetch | undefined;

  beforeEach(() => {
    localStorage.clear();
    useDummyStore.getState().reset();
    installDummy();
    originalFetch = (globalThis as unknown as { fetch?: typeof fetch }).fetch;
    (globalThis as unknown as { fetch: unknown }).fetch = jest.fn(async (url: unknown, opts?: { method?: string }) => {
      const s = String(url);
      if (s.includes('/products')) return { ok: true, status: 200, json: async () => productsResponse } as Response;
      if (s.includes('/orders') && opts?.method === 'POST') {
        return { ok: true, status: 201, json: async () => orderCreatedResponse } as Response;
      }
      if (/\/orders\//.test(s)) {
        return {
          ok: true,
          status: 200,
          json: async () => ({ status: 'success', data: orderCreatedResponse.data }),
        } as Response;
      }
      return { ok: false, status: 404, json: async () => ({ status: 'error', message: 'not found' }) } as Response;
    });
  });

  afterEach(() => {
    if (originalFetch) (globalThis as unknown as { fetch: unknown }).fetch = originalFetch;
    else delete (globalThis as unknown as { fetch?: unknown }).fetch;
    jest.clearAllMocks();
  });

  async function renderReady() {
    render(<OrderForm token="test-token" />);
    await waitFor(() => expect(screen.getByText('Beras Premium')).toBeInTheDocument());
  }

  it('exposes order-submit, order-track-input, order-track-submit after load', async () => {
    await renderReady();

    // Core submit/track testids must be present without needing a prior order creation.
    expect(screen.getByTestId('order-submit')).toBeInTheDocument();
    expect(screen.getByTestId('order-track-input')).toBeInTheDocument();
    expect(screen.getByTestId('order-track-submit')).toBeInTheDocument();
  });

  it('exposes order-success after creating an order', async () => {
    await renderReady();

    // Add quantities then submit — this triggers the order-success block
    const qtyInputs = screen.getAllByLabelText(/^Jumlah /);
    fireEvent.change(qtyInputs[0], { target: { value: '2' } });
    fireEvent.change(qtyInputs[1], { target: { value: '1' } });

    const submitBtn = screen.getByTestId('order-submit');
    await act(async () => {
      fireEvent.click(submitBtn);
    });

    await waitFor(() => expect(screen.getByTestId('order-success')).toHaveTextContent('ORD-TEST-001'));
  });
});

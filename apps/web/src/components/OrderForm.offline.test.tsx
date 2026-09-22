import React from 'react';
import '@testing-library/jest-dom';
import { render, screen, waitFor, fireEvent, act } from '@testing-library/react';

// Mock API
jest.mock('@/lib/api', () => ({
  apiUrl: (p: string) => `http://localhost:8000/api${p}`,
  authHeaders: (token: string) => ({ Authorization: `Bearer ${token}` }),
  getStoredToken: () => 'test-token',
}));

// Mock order queue
jest.mock('@/lib/offline/order-queue', () => ({
  createOrderQueue: jest.fn(),
  createDefaultAdapter: jest.fn(),
}));

import { useDummyStore } from '@/dummy/store';
import { installDummy } from '@/dummy/install';
import OrderForm from '@/components/OrderForm';
import { createOrderQueue, createDefaultAdapter } from '@/lib/offline/order-queue';

const productsResponse = {
  status: 'success',
  data: [
    { id: 1, name: 'Beras Premium', price: '20000.00', stock_quantity: 50, is_active: true },
    { id: 2, name: 'Minyak Goreng', price: '25000.00', stock_quantity: 30, is_active: true },
  ],
};

describe('OrderForm — offline queue integration', () => {
  let originalFetch: typeof fetch | undefined;
  let mockQueue: {
    enqueue: jest.Mock;
    list: jest.Mock;
    flushQueue: jest.Mock;
  };

  function setOnline(value: boolean) {
    Object.defineProperty(window.navigator, 'onLine', {
      configurable: true,
      get: () => value,
    });
  }

  beforeEach(() => {
    localStorage.clear();
    useDummyStore.getState().reset();
    installDummy();
    setOnline(true);
    const queuedItems: Array<{ id: string; payload: unknown; created_at: string }> = [];
    mockQueue = {
      enqueue: jest.fn().mockImplementation(async (item) => {
        queuedItems.push({ id: 'test-id', payload: item, created_at: new Date().toISOString() });
      }),
      list: jest.fn().mockImplementation(async () => queuedItems),
      flushQueue: jest.fn().mockResolvedValue({ sent: 0, failed: 0 }),
    };
    (createOrderQueue as jest.Mock).mockReturnValue(mockQueue);
    (createDefaultAdapter as jest.Mock).mockResolvedValue({});

    originalFetch = (globalThis as unknown as { fetch?: typeof fetch }).fetch;
    (globalThis as unknown as { fetch: unknown }).fetch = jest.fn(async (url: unknown) => {
      const urlString = String(url);
      if (urlString.includes('/products')) {
        return { ok: true, status: 200, json: async () => productsResponse } as Response;
      }
      if (urlString.includes('/orders')) {
        return { ok: true, status: 201, json: async () => ({
          status: 'success',
          data: { id: 999, order_id: 'ORD-999', status: 'pending', total_amount: '45000.00', items: [] }
        })} as Response;
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

  const renderForm = async () => {
    render(<OrderForm token="test-token" />);
    // Wait for products to load (at least 1 quantity input)
    await waitFor(() => expect(screen.getAllByLabelText(/^Jumlah /).length).toBeGreaterThan(0));
  };

  it('submits order to queue when offline and shows waiting message', async () => {
    setOnline(false);

    await renderForm();

    // Add items to cart
    fireEvent.change(screen.getByLabelText('Jumlah Beras Premium'), { target: { value: '2' } });
    fireEvent.change(screen.getByLabelText('Jumlah Minyak Goreng'), { target: { value: '1' } });

    // Submit
    fireEvent.click(screen.getByRole('button', { name: /kirim pesanan/i }));

    // Should enqueue and show waiting message
    await waitFor(() => expect(mockQueue.enqueue).toHaveBeenCalledTimes(1));
    await waitFor(() => expect(screen.getByText(/menunggu sinkronisasi/i)).toBeInTheDocument());
  });

  it('does not enqueue when dummy mode is ON', async () => {
    setOnline(false);
    // Enable dummy mode BEFORE render so products load from dummy
    useDummyStore.getState().toggle();

    await renderForm();

    // Use first quantity input (dummy products have different names)
    const qtyInput = screen.getAllByLabelText(/^Jumlah /)[0];
    fireEvent.change(qtyInput, { target: { value: '1' } });
    fireEvent.click(screen.getByRole('button', { name: /kirim pesanan/i }));

    // Queue should NOT be called
    await waitFor(() => expect(mockQueue.enqueue).not.toHaveBeenCalled());
  });

  it('auto-flushes queue when coming back online', async () => {
    setOnline(false);

    await renderForm();
    fireEvent.change(screen.getByLabelText('Jumlah Beras Premium'), { target: { value: '1' } });
    fireEvent.click(screen.getByRole('button', { name: /kirim pesanan/i }));

    await waitFor(() => expect(mockQueue.enqueue).toHaveBeenCalledTimes(1));
    expect(mockQueue.list).toHaveBeenCalled();

    // Now go back online
    setOnline(true);
    act(() => {
      window.dispatchEvent(new Event('online'));
    });

    // Should flush queue
    await waitFor(() => expect(mockQueue.flushQueue).toHaveBeenCalledTimes(1));
  });

  it('normal online submission still works (no queue)', async () => {
    // Ensure we're online (default)
    setOnline(true);

    await renderForm();
    fireEvent.change(screen.getByLabelText('Jumlah Beras Premium'), { target: { value: '1' } });
    fireEvent.click(screen.getByRole('button', { name: /kirim pesanan/i }));

    // Should NOT use queue
    await waitFor(() => expect(mockQueue.enqueue).not.toHaveBeenCalled());
    // Should show success message
    await waitFor(() => expect(screen.getByText('ORD-999')).toBeInTheDocument());
  });
});
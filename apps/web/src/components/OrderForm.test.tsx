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
import * as idempotency from '@/lib/offline/idempotency';
import * as dashboardApi from '@/app/dashboard/api';

const productsResponse = {
  status: 'success',
  data: [
    { id: 1, name: 'Beras Premium', price: '20000.00', stock_quantity: 50, is_active: true },
    { id: 2, name: 'Minyak Goreng', price: '25000.00', stock_quantity: 30, is_active: true },
  ],
};

describe('OrderForm — online submission idempotency contract', () => {
  let originalFetch: typeof fetch | undefined;
  let mockQueue: {
    enqueue: jest.Mock;
    list: jest.Mock;
    flushQueue: jest.Mock;
  };
  let generateSpy: jest.SpyInstance;

  beforeEach(() => {
    localStorage.clear();
    useDummyStore.getState().reset();
    installDummy();
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

    generateSpy = jest.spyOn(idempotency, 'generateOrderIdempotencyKey');
  });

  afterEach(() => {
    if (originalFetch) {
      (globalThis as unknown as { fetch: unknown }).fetch = originalFetch;
    } else {
      delete (globalThis as unknown as { fetch?: unknown }).fetch;
    }
    generateSpy.mockRestore();
    jest.restoreAllMocks();
  });

  const renderForm = async () => {
    render(<OrderForm token="test-token" />);
    await waitFor(() => expect(screen.getAllByLabelText(/^Jumlah /).length).toBeGreaterThan(0));
  };

  const captureOrderHeader = (): Record<string, string> => {
    const fetchMock = (globalThis as unknown as { fetch: jest.Mock }).fetch;
    const calls = fetchMock.mock.calls;
    const orderCall = calls[calls.length - 1]; // last /orders call
    if (!orderCall) return {};
    const init = orderCall[1] as RequestInit | undefined;
    const headers: Record<string, string> = {};
    if (init?.headers) {
      Object.entries(init.headers as Record<string, string>).forEach(([k, v]) => {
        headers[k] = String(v);
      });
    }
    return headers;
  };

  it('submits with the shared idempotency key derived from canonical signature', async () => {
    await renderForm();

    fireEvent.change(screen.getByLabelText('Jumlah Beras Premium'), { target: { value: '2' } });
    fireEvent.change(screen.getByLabelText('Jumlah Minyak Goreng'), { target: { value: '1' } });
    fireEvent.click(screen.getByRole('button', { name: /kirim pesanan/i }));

    await waitFor(() => expect(screen.getByText('ORD-999')).toBeInTheDocument());

    const headers = captureOrderHeader();
    expect(headers['Idempotency-Key']).toBeTruthy();
    expect(headers['Idempotency-Key']).toBe(generateSpy.mock.results[0].value);
    expect(generateSpy).toHaveBeenCalledWith(idempotency.canonicalSignature([
      { product_id: 1, quantity: 2 },
      { product_id: 2, quantity: 1 },
    ]));
  });

  it('retries with the same cart reuse the key (ref held on error)', async () => {
    let callCount = 0;
    const fetchMock = (globalThis as unknown as { fetch: jest.Mock }).fetch;
    fetchMock.mockImplementation(async (url: unknown) => {
      const urlString = String(url);
      if (urlString.includes('/products')) {
        return { ok: true, status: 200, json: async () => productsResponse } as Response;
      }
      if (urlString.includes('/orders')) {
        callCount++;
        if (callCount === 1) {
          return { ok: false, status: 500, json: async () => ({ status: 'error', message: 'server error' }) } as Response;
        }
        return { ok: true, status: 201, json: async () => ({
          status: 'success',
          data: { id: 999, order_id: 'ORD-999', status: 'pending', total_amount: '45000.00', items: [] }
        })} as Response;
      }
      return { ok: false, status: 404, json: async () => ({ status: 'error', message: 'not found' }) } as Response;
    });

    await renderForm();
    fireEvent.change(screen.getByLabelText('Jumlah Beras Premium'), { target: { value: '1' } });
    fireEvent.click(screen.getByRole('button', { name: /kirim pesanan/i }));

    // First attempt fails
    await waitFor(() => expect(screen.getByText(/server error/i)).toBeInTheDocument());
    const firstKey = captureOrderHeader()['Idempotency-Key'];

    // Retry with same cart
    fireEvent.click(screen.getByRole('button', { name: /kirim pesanan/i }));
    await waitFor(() => expect(screen.getByText('ORD-999')).toBeInTheDocument());
    const secondKey = captureOrderHeader()['Idempotency-Key'];

    expect(firstKey).toBe(secondKey);
  });

  it('a different cart gets a new key', async () => {
    await renderForm();

    // First order: Beras only
    fireEvent.change(screen.getByLabelText('Jumlah Beras Premium'), { target: { value: '1' } });
    fireEvent.click(screen.getByRole('button', { name: /kirim pesanan/i }));
    await waitFor(() => expect(screen.getByText('ORD-999')).toBeInTheDocument());
    const firstKey = captureOrderHeader()['Idempotency-Key'];

    // Second order: Minyak only (new intent, different cart)
    fireEvent.change(screen.getByLabelText('Jumlah Beras Premium'), { target: { value: '0' } });
    fireEvent.change(screen.getByLabelText('Jumlah Minyak Goreng'), { target: { value: '2' } });
    fireEvent.click(screen.getByRole('button', { name: /kirim pesanan/i }));
    await waitFor(() => expect(screen.getByText('ORD-999')).toBeInTheDocument());
    const secondKey = captureOrderHeader()['Idempotency-Key'];

    expect(firstKey).not.toBe(secondKey);
  });

  it('identical cart submitted as a new intent gets a fresh key after success', async () => {
    await renderForm();

    // First order
    fireEvent.change(screen.getByLabelText('Jumlah Beras Premium'), { target: { value: '1' } });
    fireEvent.click(screen.getByRole('button', { name: /kirim pesanan/i }));
    await waitFor(() => expect(screen.getByText('ORD-999')).toBeInTheDocument());
    const firstKey = captureOrderHeader()['Idempotency-Key'];

    // Re-add the same items (simulating a new intentional order)
    fireEvent.change(screen.getByLabelText('Jumlah Beras Premium'), { target: { value: '1' } });
    fireEvent.click(screen.getByRole('button', { name: /kirim pesanan/i }));
    await waitFor(() => expect(screen.getByText('ORD-999')).toBeInTheDocument());
    const secondKey = captureOrderHeader()['Idempotency-Key'];

    expect(firstKey).not.toBe(secondKey);
  });
});

describe('OrderForm — cache invalidation on outlet order submission', () => {
  let originalFetch: typeof fetch | undefined;
  let mockQueue: {
    enqueue: jest.Mock;
    list: jest.Mock;
    flushQueue: jest.Mock;
  };
  let invalidateSpy: jest.SpyInstance;

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
    // Ensure dummy OFF so online path is exercised
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

    invalidateSpy = jest.spyOn(dashboardApi, 'invalidateOutletOrdersCache').mockImplementation(() => {});
  });

  afterEach(() => {
    if (originalFetch) {
      (globalThis as unknown as { fetch: unknown }).fetch = originalFetch;
    } else {
      delete (globalThis as unknown as { fetch?: unknown }).fetch;
    }
    invalidateSpy.mockRestore();
    jest.restoreAllMocks();
  });

  const renderForm = async () => {
    render(<OrderForm token="test-token" />);
    await waitFor(() => expect(screen.getAllByLabelText(/^Jumlah /).length).toBeGreaterThan(0));
  };

  it('online submit success triggers cache invalidation once with same token', async () => {
    await renderForm();
    fireEvent.change(screen.getByLabelText('Jumlah Beras Premium'), { target: { value: '1' } });
    fireEvent.click(screen.getByRole('button', { name: /kirim pesanan/i }));
    await waitFor(() => expect(screen.getByText('ORD-999')).toBeInTheDocument());
    expect(invalidateSpy).toHaveBeenCalledTimes(1);
    expect(invalidateSpy).toHaveBeenCalledWith('test-token');
  });

  it('online submit failure does NOT invalidate cache', async () => {
    const fetchMock = (globalThis as unknown as { fetch: jest.Mock }).fetch;
    fetchMock.mockImplementation(async (url: unknown) => {
      const urlString = String(url);
      if (urlString.includes('/products')) {
        return { ok: true, status: 200, json: async () => productsResponse } as Response;
      }
      if (urlString.includes('/orders')) {
        return { ok: false, status: 500, json: async () => ({ status: 'error', message: 'server error' }) } as Response;
      }
      return { ok: false, status: 404, json: async () => ({ status: 'error', message: 'not found' }) } as Response;
    });
    await renderForm();
    fireEvent.change(screen.getByLabelText('Jumlah Beras Premium'), { target: { value: '1' } });
    fireEvent.click(screen.getByRole('button', { name: /kirim pesanan/i }));
    await waitFor(() => expect(screen.getByText(/server error/i)).toBeInTheDocument());
    expect(invalidateSpy).not.toHaveBeenCalled();
  });

  it('offline flush success (sent>0) triggers cache invalidation', async () => {
    mockQueue.flushQueue.mockResolvedValue({ sent: 1, failed: 0 });
    setOnline(false);
    await renderForm();
    // Go back online to trigger flush
    setOnline(true);
    act(() => { window.dispatchEvent(new Event('online')); });
    await waitFor(() => expect(mockQueue.flushQueue).toHaveBeenCalledTimes(1));
    await waitFor(() => expect(invalidateSpy).toHaveBeenCalledTimes(1));
    expect(invalidateSpy).toHaveBeenCalledWith('test-token');
  });

  it('offline flush with sent==0 does NOT invalidate cache', async () => {
    mockQueue.flushQueue.mockResolvedValue({ sent: 0, failed: 0 });
    setOnline(false);
    await renderForm();
    setOnline(true);
    act(() => { window.dispatchEvent(new Event('online')); });
    await waitFor(() => expect(mockQueue.flushQueue).toHaveBeenCalledTimes(1));
    // Give a tick for potential invalidation
    await act(async () => { await new Promise((r) => setTimeout(r, 20)); });
    expect(invalidateSpy).not.toHaveBeenCalled();
  });

  it('dummy mode submit does not break (no throw, no queue)', async () => {
    useDummyStore.getState().toggle();
    await renderForm();
    const qtyInput = screen.getAllByLabelText(/^Jumlah /)[0];
    fireEvent.change(qtyInput, { target: { value: '1' } });
    fireEvent.click(screen.getByRole('button', { name: /kirim pesanan/i }));
    await waitFor(() => expect(screen.getByText(/berhasil dibuat/i)).toBeInTheDocument());
    // Invalidate may be called as no-op in dummy; ensure no error and queue not used
    expect(mockQueue.enqueue).not.toHaveBeenCalled();
  });
});
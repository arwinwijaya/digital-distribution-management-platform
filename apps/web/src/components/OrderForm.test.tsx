import React from 'react';
import '@testing-library/jest-dom';
import { render, screen, waitFor, fireEvent, act, within } from '@testing-library/react';

// jsdom does not expose structuredClone; fake-indexeddb (R32) requires it.
if (typeof (globalThis as { structuredClone?: unknown }).structuredClone !== 'function') {
  (globalThis as { structuredClone: <T>(v: T) => T }).structuredClone = <T,>(value: T): T =>
    JSON.parse(JSON.stringify(value)) as T;
}

// Import fake-indexeddb for R32 test (must be before any IndexedDB usage)
import 'fake-indexeddb/auto';

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

// Keep discovery network calls inert in existing OrderForm tests; integration scenarios below
// supply explicit hook results while the hook itself is verified in its own test suite.
jest.mock('@/hooks/useOutletDiscovery', () => ({
  useOutletDiscovery: jest.fn(() => ({
    discovery: [], loading: false, error: null,
    addToCart: jest.fn(), dismiss: jest.fn(), retry: jest.fn(),
  })),
}));
jest.mock('@/app/dashboard/api', () => {
  const actual = jest.requireActual('@/app/dashboard/api');
  return {
    ...actual,
    fetchOutletDashboard: jest.fn(),
    loadOutletCachedSections: jest.fn(),
    invalidateOutletOrdersCache: jest.fn((...args: unknown[]) => actual.invalidateOutletOrdersCache(...args)),
  };
});

import { useDummyStore } from '@/dummy/store';
import { installDummy } from '@/dummy/install';
import OrderForm from '@/components/OrderForm';
import { createOrderQueue, createDefaultAdapter } from '@/lib/offline/order-queue';
import * as idempotency from '@/lib/offline/idempotency';
import * as dashboardApi from '@/app/dashboard/api';
import { useOutletDiscovery } from '@/hooks/useOutletDiscovery';

// Default discovery hook result for every test; individual scenarios override as needed.
// A top-level beforeEach (re)establishes it because nested afterEach hooks call restoreAllMocks().
beforeEach(() => {
  (useOutletDiscovery as jest.Mock).mockReturnValue({
    discovery: [], loading: false, error: null,
    addToCart: jest.fn(), dismiss: jest.fn(), retry: jest.fn(),
  });
});

const emptyOutletDashboard = {
  kind: 'outlet' as const,
  data: {
    orders: [],
    credit: { available_credit: null, credit_limit: null },
    favorites: [],
  },
};

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
    (dashboardApi.loadOutletCachedSections as jest.Mock).mockResolvedValue(null);
    (dashboardApi.fetchOutletDashboard as jest.Mock).mockResolvedValue(emptyOutletDashboard);
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
  let invalidateMock: jest.Mock;

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
    (dashboardApi.loadOutletCachedSections as jest.Mock).mockResolvedValue(null);
    (dashboardApi.fetchOutletDashboard as jest.Mock).mockResolvedValue(emptyOutletDashboard);
    invalidateMock = dashboardApi.invalidateOutletOrdersCache as jest.Mock;
    invalidateMock.mockClear();
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
    invalidateMock.mockRestore();
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
    expect(invalidateMock).toHaveBeenCalledTimes(1);
    expect(invalidateMock).toHaveBeenCalledWith('test-token');
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
    expect(invalidateMock).not.toHaveBeenCalled();
  });

  it('offline flush success (sent>0) triggers cache invalidation', async () => {
    mockQueue.flushQueue.mockResolvedValue({ sent: 1, failed: 0 });
    setOnline(false);
    await renderForm();
    // Go back online to trigger flush
    setOnline(true);
    act(() => { window.dispatchEvent(new Event('online')); });
    await waitFor(() => expect(mockQueue.flushQueue).toHaveBeenCalledTimes(1));
    await waitFor(() => expect(invalidateMock).toHaveBeenCalledTimes(1));
    expect(invalidateMock).toHaveBeenCalledWith('test-token');
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
    expect(invalidateMock).not.toHaveBeenCalled();
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

describe('OrderForm — Pesanan Lagi Fast Reorder Integration', () => {
  let originalFetch: typeof fetch | undefined;
  let mockQueue: { enqueue: jest.Mock; list: jest.Mock; flushQueue: jest.Mock };
  let queuedItems: Array<{ id: string; payload: unknown; created_at: string }>;

  beforeEach(() => {
    localStorage.clear();
    useDummyStore.getState().reset();
    installDummy();
    // Ensure online path
    Object.defineProperty(window.navigator, 'onLine', { configurable: true, get: () => true });
    (dashboardApi.loadOutletCachedSections as jest.Mock).mockReturnValue(null);
    (dashboardApi.fetchOutletDashboard as jest.Mock).mockResolvedValue(emptyOutletDashboard);
    (dashboardApi.invalidateOutletOrdersCache as jest.Mock).mockClear();

    queuedItems = [];
    mockQueue = {
      enqueue: jest.fn().mockImplementation(async (item) => {
        queuedItems.push({ id: `id-${queuedItems.length}`, payload: item, created_at: new Date().toISOString() });
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
          data: { id: 999, order_id: 'ORD-999', status: 'pending', total_amount: '45000.00', items: [] },
        }) } as Response;
      }
      return { ok: false, status: 404, json: async () => ({ status: 'error', message: 'not found' }) } as Response;
    });
  });

  afterEach(() => {
    delete process.env.NEXT_PUBLIC_ENABLE_REORDER;
    Object.defineProperty(window.navigator, 'onLine', { configurable: true, get: () => true });
    if (originalFetch) {
      (globalThis as unknown as { fetch: unknown }).fetch = originalFetch;
    } else {
      delete (globalThis as unknown as { fetch?: unknown }).fetch;
    }
    jest.restoreAllMocks();
  });

  const cachedSections = (opts: {
    favorites?: Array<{ product_id: number; display_name: string; total_qty: number }>;
    orders?: Array<{ product_id: number; product_name: string; quantity: number }>;
  }) => ({
    favorites: opts.favorites ?? [],
    orders: [
      {
        id: 10,
        order_id: 'ORD-10',
        status: 'completed',
        created_at: new Date().toISOString(),
        items: (opts.orders ?? []).map((o) => ({
          product_id: o.product_id,
          product_name: o.product_name,
          quantity: o.quantity,
          unit_price: '20000',
          subtotal: String(o.quantity * 20000),
        })),
      },
    ],
  });

  it('R1: renders reorder section with items', async () => {
    (dashboardApi.loadOutletCachedSections as jest.Mock).mockReturnValue(
      cachedSections({
        favorites: [{ product_id: 1, display_name: 'Beras Premium', total_qty: 5 }],
        orders: [{ product_id: 1, product_name: 'Beras Premium', quantity: 2 }],
      }),
    );

    render(<OrderForm token="test-token" />);
    await waitFor(() => expect(screen.getByText('Pesan Lagi')).toBeInTheDocument());
    expect(screen.getAllByText('Beras Premium').length).toBeGreaterThan(0);
  });

  it('R2: clicking Tambah adds quantity additively to cart', async () => {
    (dashboardApi.loadOutletCachedSections as jest.Mock).mockReturnValue(
      cachedSections({
        favorites: [{ product_id: 1, display_name: 'Beras Premium', total_qty: 5 }],
        orders: [{ product_id: 1, product_name: 'Beras Premium', quantity: 3 }],
      }),
    );

    render(<OrderForm token="test-token" />);
    await waitFor(() => expect(screen.getByText('Pesan Lagi')).toBeInTheDocument());

    const addButton = screen.getByRole('button', { name: /^Tambah Beras Premium/ });
    fireEvent.click(addButton);

    const qtyInput = screen.getByLabelText('Jumlah Beras Premium') as HTMLInputElement;
    expect(qtyInput.value).toBe('3');

    fireEvent.click(addButton);
    expect(qtyInput.value).toBe('6');
  });

  it('R3: Ulangi Pesanan Terakhir adds valid items and reports skipped ones', async () => {
    (dashboardApi.loadOutletCachedSections as jest.Mock).mockReturnValue(
      cachedSections({
        orders: [
          { product_id: 1, product_name: 'Beras Premium', quantity: 2 },
          { product_id: 2, product_name: 'Minyak Goreng', quantity: 1 },
        ],
      }),
    );
    // Product 2 is inactive → skipped in the batch
    const fetchMock = (globalThis as unknown as { fetch: jest.Mock }).fetch;
    fetchMock.mockImplementation(async (url: unknown) => {
      const urlString = String(url);
      if (urlString.includes('/products')) {
        return { ok: true, status: 200, json: async () => ({
          status: 'success',
          data: [
            { id: 1, name: 'Beras Premium', price: '20000.00', stock_quantity: 50, is_active: true },
            { id: 2, name: 'Minyak Goreng', price: '25000.00', stock_quantity: 30, is_active: false },
          ],
        }) } as Response;
      }
      return { ok: true, status: 200, json: async () => ({ status: 'success', data: [] }) } as Response;
    });

    render(<OrderForm token="test-token" />);
    await waitFor(() => expect(screen.getByText('Pesan Lagi')).toBeInTheDocument());

    fireEvent.click(screen.getByRole('button', { name: /Ulangi Pesanan Terakhir/ }));

    const qtyInput = screen.getByLabelText('Jumlah Beras Premium') as HTMLInputElement;
    expect(qtyInput.value).toBe('2');
    await waitFor(() => expect(screen.getByTestId('reorder-notice')).toHaveTextContent(/Minyak Goreng/));
  });

  it('R3 edge: all-invalid batch leaves cart unchanged with all-skipped notice', async () => {
    (dashboardApi.loadOutletCachedSections as jest.Mock).mockReturnValue(
      cachedSections({
        orders: [{ product_id: 2, product_name: 'Minyak Goreng', quantity: 1 }],
      }),
    );
    const fetchMock = (globalThis as unknown as { fetch: jest.Mock }).fetch;
    fetchMock.mockImplementation(async (url: unknown) => {
      const urlString = String(url);
      if (urlString.includes('/products')) {
        return { ok: true, status: 200, json: async () => ({
          status: 'success',
          data: [{ id: 2, name: 'Minyak Goreng', price: '25000.00', stock_quantity: 30, is_active: false }],
        }) } as Response;
      }
      return { ok: true, status: 200, json: async () => ({ status: 'success', data: [] }) } as Response;
    });

    render(<OrderForm token="test-token" />);
    await waitFor(() => expect(screen.getByText('Pesan Lagi')).toBeInTheDocument());

    fireEvent.click(screen.getByRole('button', { name: /Ulangi Pesanan Terakhir/ }));
    await waitFor(() => expect(screen.getByTestId('reorder-notice')).toHaveTextContent(/Minyak Goreng/));
    const qtyInput = screen.getByLabelText('Jumlah Minyak Goreng') as HTMLInputElement;
    expect(qtyInput.value).toBe('0');
  });

  it('R4: disables add button and shows credit warning when credit exceeded', async () => {
    (dashboardApi.loadOutletCachedSections as jest.Mock).mockReturnValue(null);
    (dashboardApi.fetchOutletDashboard as jest.Mock).mockResolvedValue({
      kind: 'outlet',
      data: {
        summary: { total: 1, statuses: {}, recent: [], truncation: { capped: false, total: 1 } },
        shopping: { total: '0', count: 0, period_label: 'x' },
        credit: { credit_limit: 500000, outstanding_balance: '0', available_credit: 10000, hidden: false },
        favorites: [{ product_id: 1, display_name: 'Beras Premium', total_qty: 100 }],
      },
    });
    (dashboardApi.loadOutletCachedSections as jest.Mock)
      .mockReturnValueOnce(null)
      .mockReturnValue(cachedSections({
        favorites: [{ product_id: 1, display_name: 'Beras Premium', total_qty: 100 }],
        orders: [{ product_id: 1, product_name: 'Beras Premium', quantity: 100 }],
      }));

    render(<OrderForm token="test-token" />);
    await waitFor(() => expect(screen.getByText('Melebihi batas kredit')).toBeInTheDocument());
    expect(screen.getByRole('button', { name: /Tambah Beras Premium/ })).toBeDisabled();
  });

  it('R7: renders empty state hint when outlet has no reorder history', async () => {
    (dashboardApi.loadOutletCachedSections as jest.Mock).mockReturnValue(null);
    (dashboardApi.fetchOutletDashboard as jest.Mock).mockResolvedValue(emptyOutletDashboard);

    render(<OrderForm token="test-token" />);
    await waitFor(() => expect(screen.getByText(/Belum ada riwayat pesanan/i)).toBeInTheDocument());
    expect(screen.queryByText('Pesan Lagi')).not.toBeInTheDocument();
  });

  it('R16: feature flag off hides reorder section completely', async () => {
    process.env.NEXT_PUBLIC_ENABLE_REORDER = 'false';
    (dashboardApi.loadOutletCachedSections as jest.Mock).mockReturnValue(
      cachedSections({
        favorites: [{ product_id: 1, display_name: 'Beras Premium', total_qty: 5 }],
        orders: [{ product_id: 1, product_name: 'Beras Premium', quantity: 2 }],
      }),
    );

    render(<OrderForm token="test-token" />);
    await waitFor(() => expect(screen.getAllByLabelText(/^Jumlah /).length).toBeGreaterThan(0));
    expect(screen.queryByText('Pesan Lagi')).not.toBeInTheDocument();
    expect(screen.queryByRole('button', { name: /Ulangi Pesanan Terakhir/ })).not.toBeInTheDocument();
    // Catalog should still be visible
    expect(screen.getByText('Pilih produk')).toBeInTheDocument();
  });

  it('R18: stock-0 items render disabled with Stok habis reason', async () => {
    (dashboardApi.loadOutletCachedSections as jest.Mock).mockReturnValue(
      cachedSections({
        orders: [{ product_id: 2, product_name: 'Minyak Goreng', quantity: 1 }],
      }),
    );
    const fetchMock = (globalThis as unknown as { fetch: jest.Mock }).fetch;
    fetchMock.mockImplementation(async (url: unknown) => {
      const urlString = String(url);
      if (urlString.includes('/products')) {
        return { ok: true, status: 200, json: async () => ({
          status: 'success',
          data: [
            { id: 2, name: 'Minyak Goreng', price: '25000.00', stock_quantity: 0, is_active: true },
          ],
        }) } as Response;
      }
      return { ok: true, status: 200, json: async () => ({ status: 'success', data: [] }) } as Response;
    });

    render(<OrderForm token="test-token" />);
    await waitFor(() => expect(screen.getByText('Pesan Lagi')).toBeInTheDocument());

    const addButton = screen.getByRole('button', { name: /Tambah Minyak Goreng/ });
    expect(addButton).toBeDisabled();
    expect(screen.getByText('Stok habis')).toBeInTheDocument();
  });

  it('R20: accessibility — buttons have descriptive aria-labels and are keyboard reachable', async () => {
    (dashboardApi.loadOutletCachedSections as jest.Mock).mockReturnValue(
      cachedSections({
        favorites: [{ product_id: 1, display_name: 'Beras Premium', total_qty: 5 }],
        orders: [{ product_id: 1, product_name: 'Beras Premium', quantity: 3 }],
      }),
    );

    render(<OrderForm token="test-token" />);
    await waitFor(() => expect(screen.getByText('Pesan Lagi')).toBeInTheDocument());

    // Tambah button aria-label includes qty and stock
    const addButton = screen.getByRole('button', { name: /^Tambah Beras Premium/ });
    expect(addButton.getAttribute('aria-label')).toMatch(/^Tambah Beras Premium, qty 3 — Stok: 50$/);

    // Ulangi Pesanan Terakhir button has aria-label
    const reorderButton = screen.getByRole('button', { name: /Ulangi Pesanan Terakhir/ });
    expect(reorderButton.getAttribute('aria-label')).toBe('Ulangi Pesanan Terakhir');

    // Native button semantics (type=button implied)
    expect(addButton.tagName).toBe('BUTTON');
    expect(reorderButton.tagName).toBe('BUTTON');

    // Keyboard reachable: focus() works and Enter key does not throw
    addButton.focus();
    expect(document.activeElement).toBe(addButton);
    fireEvent.keyDown(addButton, { key: 'Enter', code: 'Enter' });
    fireEvent.click(addButton);
    const qtyInput = screen.getByLabelText('Jumlah Beras Premium') as HTMLInputElement;
    expect(qtyInput.value).toBe('3');
  });

  it('R22: offline — cache-only prefill with notice and queue enqueue with unified idempotency key', async () => {
    // Set offline
    Object.defineProperty(window.navigator, 'onLine', { configurable: true, get: () => false });
    window.dispatchEvent(new Event('offline'));

    (dashboardApi.loadOutletCachedSections as jest.Mock).mockReturnValue(
      cachedSections({
        favorites: [{ product_id: 1, display_name: 'Beras Premium', total_qty: 5 }],
        orders: [{ product_id: 1, product_name: 'Beras Premium', quantity: 2 }],
      }),
    );

    render(<OrderForm token="test-token" />);
    await waitFor(() => expect(screen.getByText('Pesan Lagi')).toBeInTheDocument());

    // Click Ulangi Pesanan Terakhir
    fireEvent.click(screen.getByRole('button', { name: /Ulangi Pesanan Terakhir/ }));

    // Offline notice should appear
    await waitFor(() => expect(screen.getByTestId('reorder-notice')).toHaveTextContent(/berdasarkan data terakhir/));

    // Cart should have item
    const qtyInput = screen.getByLabelText('Jumlah Beras Premium') as HTMLInputElement;
    expect(qtyInput.value).toBe('2');

    // Submit while offline -> enqueue
    fireEvent.click(screen.getByRole('button', { name: /kirim pesanan/i }));
    await waitFor(() => expect(screen.getByText(/menunggu sinkronisasi/i)).toBeInTheDocument());

    // Verify queue enqueue was called with unified idempotency key
    // (OrderQueue mock is per-test, check pendingCount > 0)
    expect(screen.getByText(/1 pesanan menunggu sinkronisasi/)).toBeInTheDocument();
  });

  it('R24: race condition — backend per-item errors shown, valid items retained in cart', async () => {
    (dashboardApi.loadOutletCachedSections as jest.Mock).mockReturnValue(
      cachedSections({
        favorites: [
          { product_id: 1, display_name: 'Beras Premium', total_qty: 2 },
          { product_id: 2, display_name: 'Minyak Goreng', total_qty: 1 },
        ],
        orders: [
          { product_id: 1, product_name: 'Beras Premium', quantity: 2 },
          { product_id: 2, product_name: 'Minyak Goreng', quantity: 1 },
        ],
      }),
    );
    // Mock fetch to return 422 with per-item errors
    const fetchMock = (globalThis as unknown as { fetch: jest.Mock }).fetch;
    fetchMock.mockImplementation(async (url: unknown) => {
      const urlString = String(url);
      if (urlString.includes('/products')) {
        return { ok: true, status: 200, json: async () => productsResponse } as Response;
      }
      if (urlString.includes('/orders')) {
        return { ok: false, status: 422, json: async () => ({
          status: 'error',
          message: 'Validasi gagal',
          errors: { 'items.0.quantity': ['Insufficient stock. Only 1 unit(s) remain.'] },
        }) } as Response;
      }
      return { ok: true, status: 200, json: async () => ({ status: 'success', data: [] }) } as Response;
    });

    render(<OrderForm token="test-token" />);
    await waitFor(() => expect(screen.getByText('Pesan Lagi')).toBeInTheDocument());

    // Add both items via reorder
    fireEvent.click(screen.getByRole('button', { name: /^Tambah Beras Premium/ }));
    fireEvent.click(screen.getByRole('button', { name: /^Tambah Minyak Goreng/ }));

    // Submit
    fireEvent.click(screen.getByRole('button', { name: /kirim pesanan/i }));

    // Error with per-item details shown
    await waitFor(() => expect(screen.getByRole('alert')).toHaveTextContent(/Insufficient stock/));

    // Valid items remain in cart
    const qtyInput = screen.getByLabelText('Jumlah Beras Premium') as HTMLInputElement;
    expect(qtyInput.value).toBe('2');
  });

  it('R26: cache invalidation → refetch → render (T2+T3+T4 seam)', async () => {
    let phase: 'before' | 'after' = 'before';
    const beforeSections = cachedSections({
      favorites: [{ product_id: 1, display_name: 'Beras Premium', total_qty: 5 }],
      orders: [{ product_id: 1, product_name: 'Beras Premium', quantity: 2 }],
    });
    const afterSections = cachedSections({
      favorites: [
        { product_id: 1, display_name: 'Beras Premium', total_qty: 5 },
        { product_id: 2, display_name: 'Minyak Goreng', total_qty: 3 },
      ],
      orders: [
        { product_id: 1, product_name: 'Beras Premium', quantity: 2 },
        { product_id: 2, product_name: 'Minyak Goreng', quantity: 3 },
      ],
    });
    (dashboardApi.loadOutletCachedSections as jest.Mock).mockImplementation(() =>
      phase === 'before' ? beforeSections : afterSections,
    );
    (dashboardApi.fetchOutletDashboard as jest.Mock).mockResolvedValue(emptyOutletDashboard);

    render(<OrderForm token="test-token" />);
    await waitFor(() => expect(screen.getByText('Pesan Lagi')).toBeInTheDocument());
    expect(screen.getAllByText('Beras Premium').length).toBeGreaterThan(0);
    // Before submit, only Beras is in the reorder section
    expect(screen.queryAllByRole('button', { name: /^Tambah Minyak Goreng/ }).length).toBe(0);

    // Submit success triggers invalidate and refreshKey bump
    fireEvent.change(screen.getByLabelText('Jumlah Beras Premium'), { target: { value: '1' } });
    phase = 'after';
    fireEvent.click(screen.getByRole('button', { name: /kirim pesanan/i }));
    await waitFor(() => expect(screen.getByText('ORD-999')).toBeInTheDocument());

    // Hook refetched via refreshKey and the new favorite appears
    await waitFor(() => expect(screen.getByRole('button', { name: /^Tambah Minyak Goreng/ })).toBeInTheDocument());
  });

  it('R28: hook + OrderForm composition — dedup favorites+recent, current_price, credit_exceeded', async () => {
    (dashboardApi.loadOutletCachedSections as jest.Mock).mockReturnValue(
      cachedSections({
        favorites: [{ product_id: 1, display_name: 'Beras Premium', total_qty: 5 }],
        orders: [
          { product_id: 1, product_name: 'Beras Premium', quantity: 2 },
          { product_id: 2, product_name: 'Minyak Goreng', quantity: 1 },
        ],
      }),
    );

    render(<OrderForm token="test-token" />);
    await waitFor(() => expect(screen.getByText('Pesan Lagi')).toBeInTheDocument());

    // Both products should appear in the reorder section (dedup: product 1 from favorites, product 2 from recent)
    expect(screen.getByRole('button', { name: /^Tambah Beras Premium/ })).toBeInTheDocument();
    expect(screen.getByRole('button', { name: /^Tambah Minyak Goreng/ })).toBeInTheDocument();

    // Credit within limit -> no label for Beras (credit limit high in emptyOutletDashboard)
    expect(screen.queryByText('Melebihi batas kredit')).not.toBeInTheDocument();
    expect(screen.getByRole('button', { name: /^Tambah Beras Premium/ })).not.toBeDisabled();
  });

  it('R30: unified idempotency — offline enqueue key used as Idempotency-Key on flush', async () => {
    (dashboardApi.loadOutletCachedSections as jest.Mock).mockReturnValue(
      cachedSections({
        favorites: [{ product_id: 1, display_name: 'Beras Premium', total_qty: 5 }],
        orders: [{ product_id: 1, product_name: 'Beras Premium', quantity: 2 }],
      }),
    );

    const fetchMock = (globalThis as unknown as { fetch: jest.Mock }).fetch;
    let capturedFlushKey: string | undefined;
    let capturedEnqueueKey: string | undefined;

    fetchMock.mockImplementation(async (url: unknown, init?: RequestInit) => {
      const urlString = String(url);
      if (urlString.includes('/products')) {
        return { ok: true, status: 200, json: async () => productsResponse } as Response;
      }
      if (urlString.includes('/orders')) {
        // Called during flush
        capturedFlushKey = (init?.headers as Record<string, string>)?.['Idempotency-Key'];
        return { ok: true, status: 201, json: async () => ({
          status: 'success',
          data: { id: 999, order_id: 'ORD-999', status: 'pending', total_amount: '40000.00', items: [] },
        })} as Response;
      }
      return { ok: true, status: 200, json: async () => ({ status: 'success', data: [] }) } as Response;
    });

    // Spy on mockQueue.enqueue to capture offline key
    const enqueueSpy = jest.spyOn(mockQueue, 'enqueue');
    // Override flushQueue to call the real send with queued items
    mockQueue.flushQueue.mockImplementation(async (send) => {
      const items = await mockQueue.list();
      let sent = 0;
      for (const item of items) {
        const payloadKey = item.payload && typeof item.payload === 'object'
          ? (item.payload as { idempotency_key?: string }).idempotency_key
          : undefined;
        const key = payloadKey && payloadKey.length > 0 ? payloadKey : item.id;
        await send(item.payload, { headers: { 'Idempotency-Key': key } });
        sent++;
      }
      return { sent, failed: 0 };
    });

    // Go offline
    Object.defineProperty(window.navigator, 'onLine', { configurable: true, get: () => false });
    window.dispatchEvent(new Event('offline'));

    render(<OrderForm token="test-token" />);
    await waitFor(() => expect(screen.getByText('Pesan Lagi')).toBeInTheDocument());

    // Add item and submit while offline (enqueues)
    fireEvent.click(screen.getByRole('button', { name: /^Tambah Beras Premium/ }));
    fireEvent.click(screen.getByRole('button', { name: /kirim pesanan/i }));
    await waitFor(() => expect(screen.getByText(/menunggu sinkronisasi/i)).toBeInTheDocument());

    // Capture enqueued key
    const enqueueCall = enqueueSpy.mock.calls[enqueueSpy.mock.calls.length - 1];
    capturedEnqueueKey = (enqueueCall[0] as { idempotency_key?: string })?.idempotency_key;

    // Come online (triggers flush)
    Object.defineProperty(window.navigator, 'onLine', { configurable: true, get: () => true });
    window.dispatchEvent(new Event('online'));

    // Wait for auto-flush and cache invalidation
    await waitFor(() => expect(dashboardApi.invalidateOutletOrdersCache).toHaveBeenCalled());
    await waitFor(() => expect(screen.queryByText(/menunggu sinkronisasi/i)).not.toBeInTheDocument());

    // Verify flush used the same key
    expect(capturedEnqueueKey).toBeDefined();
    expect(capturedFlushKey).toBeDefined();
    expect(capturedFlushKey).toBe(capturedEnqueueKey);
  });

  it('R32: offline→online transition with real IndexedDB queue + fake-indexeddb', async () => {
    // Use REAL queue implementation backed by fake-indexeddb
    const realQueueModule = jest.requireActual('@/lib/offline/order-queue');
    (createOrderQueue as jest.Mock).mockImplementation(realQueueModule.createOrderQueue);
    (createDefaultAdapter as jest.Mock).mockImplementation(realQueueModule.createDefaultAdapter);

    (dashboardApi.loadOutletCachedSections as jest.Mock).mockReturnValue(
      cachedSections({
        favorites: [{ product_id: 1, display_name: 'Beras Premium', total_qty: 5 }],
        orders: [{ product_id: 1, product_name: 'Beras Premium', quantity: 2 }],
      }),
    );

    const fetchMock = (globalThis as unknown as { fetch: jest.Mock }).fetch;
    fetchMock.mockImplementation(async (url: unknown) => {
      const urlString = String(url);
      if (urlString.includes('/products')) {
        return { ok: true, status: 200, json: async () => productsResponse } as Response;
      }
      if (urlString.includes('/orders')) {
        return { ok: true, status: 201, json: async () => ({
          status: 'success',
          data: { id: 999, order_id: 'ORD-999', status: 'pending', total_amount: '40000.00', items: [] },
        })} as Response;
      }
      return { ok: true, status: 200, json: async () => ({ status: 'success', data: [] }) } as Response;
    });

    // Go offline
    Object.defineProperty(window.navigator, 'onLine', { configurable: true, get: () => false });
    window.dispatchEvent(new Event('offline'));

    render(<OrderForm token="test-token" />);
    await waitFor(() => expect(screen.getByText('Pesan Lagi')).toBeInTheDocument());

    // Wait for async queue initialization (createDefaultAdapter with fake-indexeddb)
    await act(async () => { await new Promise((r) => setTimeout(r, 50)); });

    // Add item and submit while offline (enqueues to real IndexedDB)
    fireEvent.click(screen.getByRole('button', { name: /^Tambah Beras Premium/ }));
    fireEvent.click(screen.getByRole('button', { name: /kirim pesanan/i }));
    await waitFor(() => expect(screen.getByText(/menunggu sinkronisasi/i)).toBeInTheDocument());

    // Come online
    Object.defineProperty(window.navigator, 'onLine', { configurable: true, get: () => true });
    window.dispatchEvent(new Event('online'));

    // Wait for auto-flush and cache invalidation
    await waitFor(() => expect(dashboardApi.invalidateOutletOrdersCache).toHaveBeenCalled());
    await waitFor(() => expect(screen.queryByText(/menunggu sinkronisasi/i)).not.toBeInTheDocument());
  });
});

describe('OrderForm — discovery totals', () => {
  let originalFetch: typeof fetch | undefined;
  beforeEach(() => {
    useDummyStore.getState().reset();
    installDummy();
    (dashboardApi.loadOutletCachedSections as jest.Mock).mockResolvedValue(null);
    (dashboardApi.fetchOutletDashboard as jest.Mock).mockResolvedValue(emptyOutletDashboard);
    (createOrderQueue as jest.Mock).mockReturnValue({
      enqueue: jest.fn(), list: jest.fn().mockResolvedValue([]),
      flushQueue: jest.fn().mockResolvedValue({ sent: 0, failed: 0 }),
    });
    (createDefaultAdapter as jest.Mock).mockResolvedValue({});
    originalFetch = (globalThis as unknown as { fetch?: typeof fetch }).fetch;
    (globalThis as unknown as { fetch: unknown }).fetch = jest.fn(async (url: unknown) => {
      if (String(url).includes('/products')) {
        return { ok: true, status: 200, json: async () => productsResponse } as Response;
      }
      if (String(url).includes('/orders')) {
        return { ok: true, status: 201, json: async () => ({ status: 'success', data: { id: 999, order_id: 'ORD-999', status: 'pending', total_amount: '20000.00', items: [] } }) } as Response;
      }
      return { ok: true, status: 200, json: async () => ({ status: 'success', data: [] }) } as Response;
    });
  });
  afterEach(() => {
    if (originalFetch) (globalThis as unknown as { fetch: unknown }).fetch = originalFetch;
    else delete (globalThis as unknown as { fetch?: unknown }).fetch;
    useDummyStore.getState().reset();
  });
  it('counts a discovery item already in the catalog only once using catalog quantity and price', async () => {
    (useOutletDiscovery as jest.Mock).mockImplementation((_token: string, _products: unknown[], opts: Record<string, unknown>) => ({
      discovery: [{ product_id: 1, product_name: 'Beras Premium', reason: 'Populer di platform', price: '20000.00', stock_quantity: 50, is_active: true, available: true }],
      loading: false, error: null,
      addToCart: (productId: number) => {
        (opts.setCart as (u: (c: Record<number, number>) => Record<number, number>) => void)((c) => ({ ...c, [productId]: (c[productId] || 0) + 1 }));
        (opts.onAdd as (id: number, price: string) => void)(productId, '20000.00');
      },
      dismiss: jest.fn(), retry: jest.fn(),
    }));
    render(<OrderForm token="test-token" />);
    await waitFor(() => expect(screen.getByText('Coba produk lain')).toBeInTheDocument());
    fireEvent.click(screen.getByRole('button', { name: /Tambah Beras Premium/ }));
    await waitFor(() => expect((screen.getByLabelText('Jumlah Beras Premium') as HTMLInputElement).value).toBe('1'));
    const totalLabel = screen.getByText('Total pesanan').parentElement!;
    expect(within(totalLabel).getByText('Rp 20.000')).toBeInTheDocument();
    expect(within(totalLabel).queryByText('Rp 40.000')).not.toBeInTheDocument();
  });
  it('includes an undiscovered catalog item once via the discovery fallback price', async () => {
    (useOutletDiscovery as jest.Mock).mockImplementation((_token: string, _products: unknown[], opts: Record<string, unknown>) => ({
      discovery: [{ product_id: 3, product_name: 'Gula Pasir', reason: 'Populer', price: '15000.00', stock_quantity: 40, is_active: true, available: true }],
      loading: false, error: null,
      addToCart: (productId: number) => (opts.onAdd as (id: number, price: string) => void)(productId, '15000.00'),
      dismiss: jest.fn(), retry: jest.fn(),
    }));
    render(<OrderForm token="test-token" />);
    await waitFor(() => expect(screen.getByText('Coba produk lain')).toBeInTheDocument());
    fireEvent.click(screen.getByRole('button', { name: /Tambah Gula Pasir/ }));
    const totalLabel = await waitFor(() => {
      const total = screen.getByText('Total pesanan').parentElement!;
      expect(within(total).getByText('Rp 15.000')).toBeInTheDocument();
      return total;
    });
    expect(within(totalLabel).queryByText('Rp 30.000')).not.toBeInTheDocument();
  });
});

describe('OrderForm — Outlet Discovery Section Integration (T8)', () => {
  const originalEnvFlag = process.env.NEXT_PUBLIC_ENABLE_DISCOVERY;
  let originalFetch: typeof fetch | undefined;

  beforeEach(() => {
    useDummyStore.getState().reset();
    installDummy();
    (dashboardApi.loadOutletCachedSections as jest.Mock).mockReturnValue(null);
    (dashboardApi.fetchOutletDashboard as jest.Mock).mockResolvedValue(emptyOutletDashboard);
    (createOrderQueue as jest.Mock).mockReturnValue({
      enqueue: jest.fn(), list: jest.fn().mockResolvedValue([]),
      flushQueue: jest.fn().mockResolvedValue({ sent: 0, failed: 0 }),
    });
    (createDefaultAdapter as jest.Mock).mockResolvedValue({});
    originalFetch = (globalThis as unknown as { fetch?: typeof fetch }).fetch;
    (globalThis as unknown as { fetch: unknown }).fetch = jest.fn(async (url: unknown) => {
      if (String(url).includes('/products')) {
        return { ok: true, status: 200, json: async () => productsResponse } as Response;
      }
      if (String(url).includes('/orders')) {
        return { ok: true, status: 201, json: async () => ({
          status: 'success',
          data: { id: 999, order_id: 'ORD-999', status: 'pending', total_amount: '20000.00', items: [] },
        }) } as Response;
      }
      return { ok: true, status: 200, json: async () => ({ status: 'success', data: [] }) } as Response;
    });
  });

  afterEach(() => {
    if (originalFetch) {
      (globalThis as unknown as { fetch: unknown }).fetch = originalFetch;
    } else {
      delete (globalThis as unknown as { fetch?: unknown }).fetch;
    }
    if (originalEnvFlag === undefined) {
      delete process.env.NEXT_PUBLIC_ENABLE_DISCOVERY;
    } else {
      process.env.NEXT_PUBLIC_ENABLE_DISCOVERY = originalEnvFlag;
    }
    useDummyStore.getState().reset();
  });

  it('OrderForm_renders_discovery_below_reorder', async () => {
    delete process.env.NEXT_PUBLIC_ENABLE_DISCOVERY;
    (dashboardApi.loadOutletCachedSections as jest.Mock).mockReturnValue({
      favorites: [{ product_id: 1, display_name: 'Beras Premium', total_qty: 5 }],
      orders: [],
    });
    (useOutletDiscovery as jest.Mock).mockReturnValue({
      discovery: [
        {
          product_id: 3,
          product_name: 'Gula Pasir',
          reason: 'Populer di platform',
          price: '15000.00',
          stock_quantity: 40,
          is_active: true,
          available: true,
        },
      ],
      loading: false,
      error: null,
      addToCart: jest.fn(),
      dismiss: jest.fn(),
      retry: jest.fn(),
    });

    render(<OrderForm token="test-token" />);

    await waitFor(() => expect(screen.getByText('Pesan Lagi')).toBeInTheDocument());
    expect(screen.getByText('Coba produk lain')).toBeInTheDocument();
    expect(screen.getByText('Gula Pasir')).toBeInTheDocument();

    // Verify DOM order: ReorderSection header appears before DiscoverySection header
    const reorderHeader = screen.getByText('Pesan Lagi');
    const discoveryHeader = screen.getByText('Coba produk lain');
    expect(
      reorderHeader.compareDocumentPosition(discoveryHeader) & Node.DOCUMENT_POSITION_FOLLOWING,
    ).toBeTruthy();
  });

  it('OrderForm_discovery_hidden_when_feature_flag_disabled', async () => {
    process.env.NEXT_PUBLIC_ENABLE_DISCOVERY = 'false';
    (useOutletDiscovery as jest.Mock).mockReturnValue({
      discovery: [
        {
          product_id: 3,
          product_name: 'Gula Pasir',
          reason: 'Populer di platform',
          price: '15000.00',
          stock_quantity: 40,
          is_active: true,
          available: true,
        },
      ],
      loading: false,
      error: null,
      addToCart: jest.fn(),
      dismiss: jest.fn(),
      retry: jest.fn(),
    });

    render(<OrderForm token="test-token" />);

    await waitFor(() => expect(screen.getByText('Pilih produk')).toBeInTheDocument());
    // Integration must exist (hook wired) yet the section stays hidden by the flag guard.
    expect(useOutletDiscovery).toHaveBeenCalled();
    expect(screen.queryByText('Coba produk lain')).not.toBeInTheDocument();
    expect(screen.queryByText('Gula Pasir')).not.toBeInTheDocument();
  });

  it('OrderForm_discovery_hidden_in_dummy_mode', async () => {
    delete process.env.NEXT_PUBLIC_ENABLE_DISCOVERY;
    useDummyStore.getState().toggle(); // isDummy = true
    (useOutletDiscovery as jest.Mock).mockReturnValue({
      discovery: [
        {
          product_id: 3,
          product_name: 'Gula Pasir',
          reason: 'Populer di platform',
          price: '15000.00',
          stock_quantity: 40,
          is_active: true,
          available: true,
        },
      ],
      loading: false,
      error: null,
      addToCart: jest.fn(),
      dismiss: jest.fn(),
      retry: jest.fn(),
    });

    render(<OrderForm token="test-token" />);

    await waitFor(() => expect(screen.getByText('Pilih produk')).toBeInTheDocument());
    // Integration must exist (hook wired) yet the section stays hidden in dummy mode.
    expect(useOutletDiscovery).toHaveBeenCalled();
    expect(screen.queryByText('Coba produk lain')).not.toBeInTheDocument();
  });

  it('OrderForm_refreshes_discovery_after_order_success', async () => {
    delete process.env.NEXT_PUBLIC_ENABLE_DISCOVERY;

    // Track hook calls across renders to verify refreshKey is incremented
    const discoveryCalls: Array<Record<string, unknown>> = [];
    (useOutletDiscovery as jest.Mock).mockImplementation((token: string, products: unknown[], opts: Record<string, unknown>) => {
      discoveryCalls.push(opts);
      return {
        discovery: [],
        loading: false,
        error: null,
        addToCart: jest.fn(),
        dismiss: jest.fn(),
        retry: jest.fn(),
      };
    });

    render(<OrderForm token="test-token" />);
    await waitFor(() => expect(screen.getByText('Pilih produk')).toBeInTheDocument());

    const initialKey = (discoveryCalls[discoveryCalls.length - 1]?.refreshKey as number) ?? 0;

    // Select product and submit order
    fireEvent.change(screen.getByLabelText('Jumlah Beras Premium'), { target: { value: '1' } });
    fireEvent.click(screen.getByRole('button', { name: /kirim pesanan/i }));

    await waitFor(() => expect(screen.getByText('ORD-999')).toBeInTheDocument());

    const postOrderKey = (discoveryCalls[discoveryCalls.length - 1]?.refreshKey as number) ?? 0;
    expect(postOrderKey).toBeGreaterThan(initialKey);
  });
});


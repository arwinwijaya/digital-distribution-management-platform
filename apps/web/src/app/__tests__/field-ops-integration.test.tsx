/**
 * Phase 8 — T18: cross-unit web integration (offline → flush → sent).
 *
 * Given: an order queued while offline (real order-queue unit, in-memory adapter).
 * When: the browser comes back online (online event) + auto-flush runs.
 * Then: the order is POSTed to the server (mock fetch end-to-end) and the
 * pending queue empties (pending count back to 0).
 *
 * Dummy mode stays OFF so all reads hit the mocked fetch (zero network in
 * dummy mode, real fetch path here). Uses fake timers for determinism of any
 * debounced paths; promise flushing is done explicitly with waitFor.
 */
import React from 'react';
import '@testing-library/jest-dom';
import { render, screen, waitFor, fireEvent, act } from '@testing-library/react';

jest.mock('@/lib/api', () => ({
  apiUrl: (p: string) => `http://localhost:8000/api${p}`,
  authHeaders: (token: string) => ({ Authorization: `Bearer ${token}` }),
  getStoredToken: () => 'test-token',
}));

// Keep the real order-queue unit; only stub the adapter factory.
jest.mock('@/lib/offline/order-queue', () => {
  const actual = jest.requireActual('@/lib/offline/order-queue');
  return { ...actual, createDefaultAdapter: jest.fn() };
});

import { useDummyStore } from '@/dummy/store';
import { installDummy } from '@/dummy/install';
import OrderForm from '@/components/OrderForm';
import { createDefaultAdapter } from '@/lib/offline/order-queue';
import type { StorageAdapter } from '@/lib/offline/order-queue';

function createMemoryAdapter(): StorageAdapter {
  const store = new Map<string, string>();
  return {
    get: (key) => Promise.resolve(store.get(key) ?? null),
    set: (key, value) => {
      store.set(key, value);
      return Promise.resolve();
    },
    delete: (key) => {
      store.delete(key);
      return Promise.resolve();
    },
    keys: () => Promise.resolve(Array.from(store.keys())),
  };
}

const productsResponse = {
  status: 'success',
  data: [
    { id: 1, name: 'Beras Premium', price: '20000.00', stock_quantity: 50, is_active: true },
    { id: 2, name: 'Minyak Goreng', price: '25000.00', stock_quantity: 30, is_active: true },
  ],
};

interface SentOrder {
  payload: unknown;
  headers: Record<string, string>;
}

describe('field-ops integration — offline → online flush → sent', () => {
  let originalFetch: typeof fetch | undefined;
  let fetchMock: jest.Mock;
  const serverOrders: SentOrder[] = [];

  function setOnline(value: boolean) {
    Object.defineProperty(window.navigator, 'onLine', {
      configurable: true,
      get: () => value,
    });
  }

  beforeEach(() => {
    localStorage.clear();
    serverOrders.length = 0;
    useDummyStore.getState().reset();
    installDummy();
    expect(useDummyStore.getState().isDummy).toBe(false);

    const adapter = createMemoryAdapter();
    (createDefaultAdapter as jest.Mock).mockResolvedValue(adapter);

    setOnline(false);

    originalFetch = (globalThis as unknown as { fetch?: typeof fetch }).fetch;
    fetchMock = jest.fn(async (url: unknown, options?: { method?: string; body?: string; headers?: unknown }) => {
      const urlString = String(url);
      if (urlString.includes('/products')) {
        return { ok: true, status: 200, json: async () => productsResponse } as Response;
      }
      if (urlString.includes('/orders') && options?.method === 'POST') {
        const headers = (options?.headers ?? {}) as Record<string, string>;
        serverOrders.push({ payload: options?.body ? JSON.parse(String(options.body)) : null, headers });
        return {
          ok: true,
          status: 201,
          json: async () => ({
            status: 'success',
            data: { id: 4242, order_id: 'ORD-FLUSH-1', status: 'pending', total_amount: '40000.00', items: [] },
          }),
        } as Response;
      }
      return { ok: false, status: 404, json: async () => ({ status: 'error', message: 'not found' }) } as Response;
    });
    (globalThis as unknown as { fetch: unknown }).fetch = fetchMock;
  });

  afterEach(() => {
    if (originalFetch) {
      (globalThis as unknown as { fetch: unknown }).fetch = originalFetch;
    } else {
      delete (globalThis as unknown as { fetch?: unknown }).fetch;
    }
    jest.restoreAllMocks();
  });

  it('Given offline order in queue, When online + flush, Then order sent to server and pending queue empties', async () => {
    // Given: offline, form renders with real queue underneath.
    render(<OrderForm token="test-token" />);
    await waitFor(() => expect(screen.getAllByLabelText(/^Jumlah /).length).toBeGreaterThan(0));

    fireEvent.change(screen.getByLabelText('Jumlah Beras Premium'), { target: { value: '2' } });
    fireEvent.click(screen.getByRole('button', { name: /kirim pesanan/i }));

    // Offline submit enqueues → pending banner appears.
    await waitFor(() => expect(screen.getByText(/menunggu sinkronisasi/i)).toBeInTheDocument());
    expect(serverOrders).toHaveLength(0);

    // When: browser comes back online; OrderForm auto-flush fires on the event.
    setOnline(true);
    act(() => {
      window.dispatchEvent(new Event('online'));
    });

    // Then: exactly one POST reaches the server with a stable idempotency key…
    await waitFor(() => expect(serverOrders).toHaveLength(1));
    const sent = serverOrders[0];
    expect(sent.payload).toMatchObject({
      items: [{ product_id: 1, quantity: 2 }],
    });
    expect(typeof sent.headers['Idempotency-Key']).toBe('string');
    expect(sent.headers['Idempotency-Key']).toBeTruthy();

    // …and the pending banner clears because the queue is empty again.
    await waitFor(() => expect(screen.queryByText(/menunggu sinkronisasi/i)).not.toBeInTheDocument());
  });
});

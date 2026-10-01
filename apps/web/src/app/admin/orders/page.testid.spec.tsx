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
import AdminOrdersPage from '@/app/admin/orders/page';

const ordersResponse = {
  status: 'success',
  data: [
    { id: 2, order_id: 'ORD-002', status: 'new', total_amount: '75000.00', items: [], created_at: '2026-09-10T10:00:00Z', updated_at: '2026-09-10T11:00:00Z' },
    { id: 1, order_id: 'ORD-001', status: 'completed', total_amount: '50000.00', items: [], created_at: '2026-09-01T08:00:00Z', updated_at: '2026-09-01T09:00:00Z' },
  ],
  meta: { has_more: false, limit: 100, cursor: 0, total: 42 },
};

const orderDetailResponse = {
  status: 'success',
  data: {
    id: 2,
    order_id: 'ORD-002',
    status: 'new',
    total_amount: '75000.00',
    items: [],
    created_at: '2026-09-10T10:00:00Z',
    updated_at: '2026-09-10T11:00:00Z',
    status_history: [{ status: 'new', created_at: '2026-09-10T10:00:00Z' }],
  },
};

describe('AdminOrdersPage — data-testid contract', () => {
  let originalFetch: typeof fetch | undefined;

  beforeEach(() => {
    useDummyStore.getState().reset();
    installDummy();
    originalFetch = (globalThis as unknown as { fetch?: typeof fetch }).fetch;
    (globalThis as unknown as { fetch: unknown }).fetch = jest.fn(async (url: unknown, opts?: { method?: string }) => {
      const s = String(url);
      if (s.includes('/admin/orders') && (!opts?.method || opts.method === 'GET')) {
        return { ok: true, status: 200, json: async () => ordersResponse } as Response;
      }
      if (/\/admin\/orders\//.test(s)) {
        return { ok: true, status: 200, json: async () => orderDetailResponse } as Response;
      }
      return { ok: false, status: 404, json: async () => ({ status: 'error', message: 'not found' }) } as Response;
    });
  });

  afterEach(() => {
    if (originalFetch) (globalThis as unknown as { fetch: unknown }).fetch = originalFetch;
    else delete (globalThis as unknown as { fetch?: unknown }).fetch;
    jest.clearAllMocks();
  });

  it('renders admin-orders-table, admin-order-detail, and admin-order-approve testids', async () => {
    render(<AdminOrdersPage />);
    await waitFor(() => expect(screen.getByTestId('admin-orders-table')).toBeInTheDocument());
    // Click on first order to trigger detail loading
    const orderButton = screen.getByRole('button', { name: /ORD-002/i });
    fireEvent.click(orderButton);
    await waitFor(() => expect(screen.getByTestId('admin-order-detail-2')).toBeInTheDocument());
    // Approve button exists for new order
    expect(screen.getByTestId('admin-order-approve-2')).toBeInTheDocument();
  });

  it('renders admin-orders-error when API error occurs', async () => {
    // Override fetch to simulate error
    (globalThis as unknown as { fetch: unknown }).fetch = jest.fn(async () => ({ ok: false, status: 500, json: async () => ({ status: 'error', message: 'Server error' }) } as Response));
    render(<AdminOrdersPage />);
    await waitFor(() => expect(screen.getByTestId('admin-orders-error')).toBeInTheDocument());
  });
});

/**
 * Invoice detail page — integration test.
 *
 * Given route /invoices/123 and API returns detail JSON with header,
 * frozen line items, payments ordered newest, outlet info, overdue badge
 * When page mounts
 * Then header, line items table, payment table, and OVERDUE badge render;
 * respects template colors/logo
 *
 * Exercises the REAL Next page component `apps/web/src/app/invoices/[id]/page.tsx`
 * rendering through `InvoiceDetail` component. Mock fetch for GET /invoices/{id};
 * do NOT mock InvoiceDetail component under test.
 *
 * Expected RED: page does not exist or renders empty.
 */
import React from 'react';
import '@testing-library/jest-dom';
import { render, screen, waitFor } from '@testing-library/react';

// Mock next/navigation for dynamic route params
jest.mock(
  'next/navigation',
  () => ({
    useParams: () => ({ id: '123' }),
    useRouter: () => ({ push: jest.fn(), replace: jest.fn() }),
  }),
  { virtual: true },
);

// Mock api module and dummy guards
jest.mock('@/lib/api', () => ({
  apiUrl: (p: string) => `http://localhost:8000/api${p}`,
  authHeaders: (token: string) => ({
    Accept: 'application/json',
    'Content-Type': 'application/json',
    Authorization: `Bearer ${token}`,
  }),
  getStoredToken: () => 'test-token',
}));

jest.mock('@/dummy/store', () => ({
  useDummyStore: Object.assign(jest.fn(() => false), {
    getState: () => ({ isDummy: false, dummyEntities: null }),
  }),
  selectIsDummy: (state: { isDummy: boolean }) => state.isDummy,
}));

const detailResponse = {
  status: 'success',
  data: {
    id: 123,
    order_id: 456,
    outlet_id: 789,
    invoice_number: 'INV-20260101-0001',
    issue_date: '2026-01-01',
    due_date: '2026-01-08',
    total_amount: '50000.00',
    paid_amount: '20000.00',
    balance_amount: '30000.00',
    status: 'unpaid',
    is_overdue: true,
    overdue: true,
    created_at: '2026-01-01T10:00:00+07:00',
    updated_at: '2026-01-01T10:00:00+07:00',
    outlet: {
      id: 789,
      name: 'Toko Sejahtera',
      phone: '081234567890',
      address: 'Jl. Merdeka No. 10',
      city: 'Jakarta',
    },
    line_items: [
      {
        id: 1,
        order_id: 456,
        product_id: 10,
        product_name: 'Sabun A',
        product_name_snapshot: 'Sabun A',
        quantity: 2,
        unit_price: '15000.00',
        subtotal: '30000.00',
      },
      {
        id: 2,
        order_id: 456,
        product_id: 11,
        product_name: 'Shampo B',
        product_name_snapshot: 'Shampo B',
        quantity: 1,
        unit_price: '20000.00',
        subtotal: '20000.00',
      },
    ],
    items: [
      {
        id: 1,
        order_id: 456,
        product_id: 10,
        product_name: 'Sabun A',
        product_name_snapshot: 'Sabun A',
        quantity: 2,
        unit_price: '15000.00',
        subtotal: '30000.00',
      },
      {
        id: 2,
        order_id: 456,
        product_id: 11,
        product_name: 'Shampo B',
        product_name_snapshot: 'Shampo B',
        quantity: 1,
        unit_price: '20000.00',
        subtotal: '20000.00',
      },
    ],
    payments: [
      {
        id: 100,
        order_id: 456,
        outlet_id: 789,
        amount: '10000.00',
        payment_method: 'transfer',
        status: 'completed',
        created_at: '2026-01-05T14:00:00+07:00',
        updated_at: '2026-01-05T14:00:00+07:00',
      },
      {
        id: 99,
        order_id: 456,
        outlet_id: 789,
        amount: '10000.00',
        payment_method: 'cash',
        status: 'pending',
        created_at: '2026-01-03T09:00:00+07:00',
        updated_at: '2026-01-03T09:00:00+07:00',
      },
    ],
    payment_history: [
      {
        id: 100,
        order_id: 456,
        outlet_id: 789,
        amount: '10000.00',
        payment_method: 'transfer',
        status: 'completed',
        created_at: '2026-01-05T14:00:00+07:00',
        updated_at: '2026-01-05T14:00:00+07:00',
      },
      {
        id: 99,
        order_id: 456,
        outlet_id: 789,
        amount: '10000.00',
        payment_method: 'cash',
        status: 'pending',
        created_at: '2026-01-03T09:00:00+07:00',
        updated_at: '2026-01-03T09:00:00+07:00',
      },
    ],
  },
};

let originalFetch: typeof fetch | undefined;
let fetchMock: jest.Mock;

beforeEach(() => {
  localStorage.clear();
  localStorage.setItem('ddp_token', 'test-token');

  fetchMock = jest.fn(async (url: unknown, options?: { method?: string }) => {
    const urlString = String(url);
    const method = (options?.method ?? 'GET').toUpperCase();
    if (urlString.includes('/invoices/123') && !urlString.includes('/pdf') && method === 'GET') {
      return { ok: true, status: 200, json: async () => detailResponse } as Response;
    }
    return { ok: true, status: 200, json: async () => ({}) } as Response;
  });
  originalFetch = (globalThis as unknown as { fetch?: typeof fetch }).fetch;
  (globalThis as unknown as { fetch: unknown }).fetch = fetchMock;
});

afterEach(() => {
  if (originalFetch) (globalThis as unknown as { fetch: unknown }).fetch = originalFetch;
  else delete (globalThis as unknown as { fetch?: unknown }).fetch;
  jest.clearAllMocks();
});

describe('invoice detail page', () => {
  it('renders header, line items table, payment table, and OVERDUE badge', async () => {
    const { default: Page } = await import('@/app/invoices/[id]/page');
    render(<Page />);

    await waitFor(() => {
      expect(screen.getByText('INV-20260101-0001')).toBeInTheDocument();
    });

    // Header fields
    expect(screen.getByText(/nomor invoice/i)).toBeInTheDocument();
    expect(screen.getByText('INV-20260101-0001')).toBeInTheDocument();
    expect(screen.getByText(/tanggal terbit/i)).toBeInTheDocument();
    expect(screen.getByText(/2026-01-01|1\/1\/2026|1 Januari 2026/i)).toBeInTheDocument();
    expect(screen.getByText(/jatuh tempo/i)).toBeInTheDocument();
    expect(screen.getByText(/2026-01-08|8\/1\/2026|8 Januari 2026|1\/8\/2026/i)).toBeInTheDocument();

    // Totals — money formatted as Rp 50.000
    expect(screen.getAllByText(/total/i).length).toBeGreaterThanOrEqual(1);
    expect(screen.getByText(/Rp\s*50\.000/i)).toBeInTheDocument();
    expect(screen.getByText(/dibayar/i)).toBeInTheDocument();
    expect(screen.getAllByText(/Rp\s*20\.000/i).length).toBeGreaterThanOrEqual(1);
    expect(screen.getByText(/sisa/i)).toBeInTheDocument();
    expect(screen.getAllByText(/Rp\s*30\.000/i).length).toBeGreaterThanOrEqual(1);

    // Status badge
    expect(screen.getAllByText(/Belum Bayar|Unpaid/i).length).toBeGreaterThanOrEqual(1);

    // OVERDUE badge
    expect(screen.getAllByText(/OVERDUE|JATUH TEMPO/i).length).toBeGreaterThanOrEqual(1);

    // Outlet info
    expect(screen.getByText(/Toko Sejahtera/i)).toBeInTheDocument();
    expect(screen.getByText(/081234567890/i)).toBeInTheDocument();
    expect(screen.getByText(/Jl. Merdeka/i)).toBeInTheDocument();

    // Line items table — frozen product names
    expect(screen.getByText(/Sabun A/i)).toBeInTheDocument();
    expect(screen.getByText(/Shampo B/i)).toBeInTheDocument();
    // Line items table shows separate quantity + unit price + subtotal
    expect(screen.getByText(/Rp\s*15\.000/i)).toBeInTheDocument();
    expect(screen.getAllByText(/Rp\s*20\.000/i).length).toBeGreaterThanOrEqual(2);

    // Payment table - newest first (id 100 first) — both 10.000 amounts present
    const paymentRows = screen.getAllByText(/Rp\s*10\.000/i);
    expect(paymentRows.length).toBeGreaterThanOrEqual(2);

    // fetch was called for /invoices/123
    expect(fetchMock).toHaveBeenCalledWith(
      expect.stringContaining('/invoices/123'),
      expect.anything(),
    );
  });
});
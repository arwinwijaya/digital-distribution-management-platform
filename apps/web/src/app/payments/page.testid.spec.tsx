import React from 'react';
import '@testing-library/jest-dom';
import { act, fireEvent, render, screen, waitFor } from '@testing-library/react';

jest.mock('@/lib/api', () => ({
  apiUrl: (path: string) => `http://localhost:8000/api${path}`,
  authHeaders: (token: string) => ({ Authorization: `Bearer ${token}` }),
  getStoredToken: () => 'test-token',
}));

jest.mock('@/app/payments/api', () => ({
  loadPaymentsList: jest.fn(),
}));

jest.mock('@/dummy/guards', () => ({
  useDummyRefresh: (fn: () => void) => { /* no-op */ },
}));

jest.mock('@/dummy/store', () => ({
  useDummyStore: {
    getState: () => ({ isDummy: false, reset: jest.fn() }),
  },
}));

import PaymentsPage from '@/app/payments/page';
import { loadPaymentsList } from '@/app/payments/api';

const mockPaymentsList = {
  payments: [
    { id: 1, order_id: 1001, amount: '50000.00', payment_method: 'cash', receipt_reference: 'RCPT-001', created_at: '2026-09-15T10:00:00Z' },
  ],
  paymentMeta: { page: 1, limit: 15, total: 1, has_more: false },
  invoiceMeta: { page: 1, limit: 15, total: 0, has_more: false },
  summary: { credit_limit: '1000000.00', outstanding_balance: '50000.00', available_credit: '950000.00' },
};

describe('PaymentsPage — data-testid contract', () => {
  let originalFetch: typeof fetch | undefined;

  beforeEach(() => {
    jest.clearAllMocks();
    originalFetch = (globalThis as unknown as { fetch?: typeof fetch }).fetch;
    (globalThis as unknown as { fetch: unknown }).fetch = jest.fn(async (url: unknown) => {
      const s = String(url);
      if (s.includes('/auth/me')) {
        return { ok: true, status: 200, json: async () => ({ status: 'success', data: { role: 'finance' } }) } as Response;
      }
      if (s.includes('/payments')) {
        return { ok: true, status: 200, json: async () => mockPaymentsList } as Response;
      }
      return { ok: false, status: 404, json: async () => ({ status: 'error', message: 'not found' }) } as Response;
    });
    (loadPaymentsList as jest.Mock).mockResolvedValue(mockPaymentsList);
  });

  afterEach(() => {
    if (originalFetch) (globalThis as unknown as { fetch: unknown }).fetch = originalFetch;
    else delete (globalThis as unknown as { fetch?: unknown }).fetch;
    jest.clearAllMocks();
  });

  it('exposes payment-order-id, payment-amount, payment-submit in PaymentEntry', async () => {
    render(<PaymentsPage />);
    // Wait for login form or data to load
    await waitFor(() => expect(screen.getByTestId('payment-order-id')).toBeInTheDocument());
    expect(screen.getByTestId('payment-amount')).toBeInTheDocument();
    expect(screen.getByTestId('payment-submit')).toBeInTheDocument();
  });

  it('exposes payment-success after successful payment submission', async () => {
    // Mock the payment API POST for the successful submission path.
    (globalThis as unknown as { fetch: unknown }).fetch = jest.fn(async (url: unknown, opts?: { method?: string }) => {
      const s = String(url);
      if (s.includes('/auth/me')) {
        return { ok: true, status: 200, json: async () => ({ status: 'success', data: { role: 'finance' } }) } as Response;
      }
      if (s.includes('/payments') && opts?.method === 'POST') {
        return { ok: true, status: 201, json: async () => ({ status: 'success', data: { receipt_reference: 'RCPT-TEST' } }) } as Response;
      }
      if (s.includes('/payments')) {
        return { ok: true, status: 200, json: async () => mockPaymentsList } as Response;
      }
      return { ok: false, status: 404, json: async () => ({ status: 'error', message: 'not found' }) } as Response;
    });

    render(<PaymentsPage />);
    await waitFor(() => expect(screen.getByTestId('payment-order-id')).toBeInTheDocument());

    fireEvent.change(screen.getByTestId('payment-order-id'), { target: { value: '1234' } });
    fireEvent.change(screen.getByTestId('payment-amount'), { target: { value: '25000' } });
    await act(async () => {
      fireEvent.click(screen.getByTestId('payment-submit'));
    });

    await waitFor(() => expect(screen.getByTestId('payment-success')).toHaveTextContent(/Pembayaran berhasil/));
  });
});
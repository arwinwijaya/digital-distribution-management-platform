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
import DeliveryPage from '@/app/delivery/page';

const deliveriesResponse = {
  status: 'success',
  data: [
    { id: 101, order_id: 101, status: 'assigned', driver_id: 5, recipient_name: null, proof_of_delivery_url: null, assigned_at: '2026-09-15T09:00:00Z', assigned_date: '2026-09-15', started_at: null, failure_reason: null, notes: null, order: { order_id: 'ORD-101', total_amount: '50000.00', outlet: { id: 1, name: 'Outlet A', city: 'Jakarta', address: null }, sales: { name: 'Sales 1' }, items: [] }, driver: { name: 'Driver 1' }, assigned_by: null },
    { id: 102, order_id: 102, status: 'in_progress', driver_id: 5, recipient_name: 'Budi', proof_of_delivery_url: 'https://example.com/proof.jpg', assigned_at: '2026-09-15T10:00:00Z', assigned_date: '2026-09-15', started_at: '2026-09-15T10:05:00Z', failure_reason: null, notes: null, order: { order_id: 'ORD-102', total_amount: '75000.00', outlet: { id: 2, name: 'Outlet B', city: 'Bandung', address: null }, sales: { name: 'Sales 2' }, items: [] }, driver: { name: 'Driver 2' }, assigned_by: null },
  ],
};

describe('DeliveryPage — data-testid contract', () => {
  let originalFetch: typeof fetch | undefined;

  beforeEach(() => {
    useDummyStore.getState().reset();
    installDummy();
    originalFetch = (globalThis as unknown as { fetch?: typeof fetch }).fetch;
    (globalThis as unknown as { fetch: unknown }).fetch = jest.fn(async (url: unknown) => {
      const s = String(url);
      if (s.includes('/deliveries')) {
        return { ok: true, status: 200, json: async () => deliveriesResponse } as Response;
      }
      return { ok: false, status: 404, json: async () => ({ status: 'error', message: 'not found' }) } as Response;
    });
  });

  afterEach(() => {
    if (originalFetch) (globalThis as unknown as { fetch: unknown }).fetch = originalFetch;
    else delete (globalThis as unknown as { fetch?: unknown }).fetch;
    jest.clearAllMocks();
  });

  it('exposes delivery-start for assigned deliveries and recipient/proof/complete for in_progress deliveries', async () => {
    render(<DeliveryPage />);
    await waitFor(() => expect(screen.getByTestId('delivery-start-101')).toBeInTheDocument());

    // First delivery is assigned -> start button present
    expect(screen.getByTestId('delivery-start-101')).toBeInTheDocument();
    // For in_progress delivery, recipient, proof, complete should be present
    expect(screen.getByTestId('delivery-recipient-102')).toBeInTheDocument();
    expect(screen.getByTestId('delivery-proof-url-102')).toBeInTheDocument();
    expect(screen.getByTestId('delivery-complete-102')).toBeInTheDocument();

    // For assigned delivery, recipient/proof/complete should NOT be present
    expect(screen.queryByTestId('delivery-recipient-101')).not.toBeInTheDocument();
    expect(screen.queryByTestId('delivery-proof-url-101')).not.toBeInTheDocument();
    expect(screen.queryByTestId('delivery-complete-101')).not.toBeInTheDocument();
  });
});
import React from 'react';
import '@testing-library/jest-dom';
import { render, screen, waitFor } from '@testing-library/react';

const mockGetStoredToken = jest.fn(() => 'test-token');
jest.mock('@/lib/api', () => ({
  apiUrl: (p: string) => `http://localhost:8000/api${p}`,
  authHeaders: (token: string) => ({ Authorization: `Bearer ${token}` }),
  getStoredToken: (...args: unknown[]) => (mockGetStoredToken as (...a: unknown[]) => string | null)(...args),
}));

const performanceResponse = {
  status: 'success',
  data: {
    user_id: 3,
    name: 'Sales Satu',
    period: '2026-09',
    target: '20000000.00',
    achievement: '8000000.00',
    percentage: '40.00',
    order_count: 1,
  },
};

describe('sales performance page', () => {
  let originalFetch: typeof fetch | undefined;

  beforeEach(() => {
    mockGetStoredToken.mockReturnValue('test-token');
    originalFetch = (globalThis as unknown as { fetch?: typeof fetch }).fetch;
    (globalThis as unknown as { fetch: unknown }).fetch = jest.fn(async (url: unknown) => {
      const urlString = String(url);
      if (urlString.includes('/sales/my-performance')) {
        return { ok: true, status: 200, json: async () => performanceResponse } as Response;
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
  });

  it('renders own performance dashboard with string monetary values formatted', async () => {
    const { default: Page } = await import('@/app/sales/performance/page');
    render(<Page />);

    await waitFor(() => {
      expect(screen.getAllByText('Rp 20.000.000').length).toBeGreaterThan(0);
    }, { timeout: 5000 });
    expect(screen.getAllByText('Rp 8.000.000').length).toBeGreaterThan(0);
    expect(screen.getAllByText('40.00%').length).toBeGreaterThan(0);

    const fetchMock = (globalThis as unknown as { fetch: jest.Mock }).fetch;
    const urls = fetchMock.mock.calls.map((c) => String(c[0]));
    expect(urls.some((u) => u.includes('/sales/my-performance'))).toBe(true);
    expect(urls.some((u) => u.includes('/admin/sales/performance'))).toBe(false);
  });
});

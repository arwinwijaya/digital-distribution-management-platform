import React from 'react';
import '@testing-library/jest-dom';
import { render, screen, waitFor, fireEvent, within } from '@testing-library/react';

const mockGetStoredToken = jest.fn(() => 'test-token');
jest.mock('@/lib/api', () => ({
  apiUrl: (p: string) => `http://localhost:8000/api${p}`,
  authHeaders: (token: string) => ({ Authorization: `Bearer ${token}` }),
  getStoredToken: (...args: unknown[]) => (mockGetStoredToken as (...a: unknown[]) => string | null)(...args),
}));
import { useDummyStore } from '@/dummy/store';
import { installDummy } from '@/dummy/install';

const ordersListResponse = {
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

const approveResponse = {
  status: 'success',
  data: { id: 2, order_id: 'ORD-002', status: 'approved', total_amount: '75000.00', items: [], status_history: [] },
};

describe('default sort + summary + timestamp columns', () => {
  let originalFetch: typeof fetch | undefined;
  let fetchMock: jest.Mock;

  const installFetch = (impl: (url: string, options?: { method?: string }) => Response) => {
    fetchMock.mockImplementation(async (url: unknown, options?: { method?: string }) => impl(String(url), options));
    (globalThis as unknown as { fetch: unknown }).fetch = fetchMock;
  };

  beforeEach(() => {
    useDummyStore.getState().reset();
    installDummy();
    mockGetStoredToken.mockReturnValue('test-token');
    originalFetch = (globalThis as unknown as { fetch?: typeof fetch }).fetch;
    fetchMock = jest.fn(async (url: unknown, options?: { method?: string }) => {
      const urlString = String(url);
      if (urlString.includes('/approve')) return { ok: true, status: 200, json: async () => approveResponse } as Response;
      if (/\/admin\/orders\/\d+$/.test(urlString)) return { ok: true, status: 200, json: async () => orderDetailResponse } as Response;
      if (urlString.includes('/admin/orders')) return { ok: true, status: 200, json: async () => ordersListResponse } as Response;
      return { ok: true, status: 200, json: async () => ({}) } as Response;
    });
    (globalThis as unknown as { fetch: unknown }).fetch = fetchMock;
  });

  afterEach(() => {
    if (originalFetch) (globalThis as unknown as { fetch: unknown }).fetch = originalFetch;
    else delete (globalThis as unknown as { fetch?: unknown }).fetch;
    jest.restoreAllMocks();
  });

  it('renders the newest-first default request and the "N pesanan" summary', async () => {
    const { default: Page } = await import('@/app/admin/orders/page');
    render(<Page />);

    await waitFor(() => expect(screen.getByText('ORD-002')).toBeInTheDocument());

    const listCall = fetchMock.mock.calls
      .map((c) => String(c[0]))
      .find((u) => u.includes('/admin/orders?')) ?? '';
    expect(listCall).toContain('sort=created_at');
    expect(listCall).toContain('order=desc');
    expect(listCall).toContain('cursor=0');
    expect(listCall).toContain('limit=100');

    expect(screen.getByTestId('table-summary').textContent).toBe('42 pesanan');
  });

  it('renders the Dibuat/Diperbarui columns formatted (not raw ISO)', async () => {
    const { default: Page } = await import('@/app/admin/orders/page');
    render(<Page />);

    await waitFor(() => expect(screen.getByText('ORD-002')).toBeInTheDocument());

    expect(screen.getByText('Dibuat')).toBeInTheDocument();
    expect(screen.getByText('Diperbarui')).toBeInTheDocument();

    // Raw ISO must never be rendered; the formatted local date (containing the
    // year token) must be.
    expect(screen.queryByText('2026-09-10T10:00:00Z')).not.toBeInTheDocument();
    expect(screen.queryByText('2026-09-01T08:00:00Z')).not.toBeInTheDocument();
    expect((document.body.textContent ?? '')).toMatch(/2026/);
  });

  it('renders "—" when a timestamp is null', async () => {
    installFetch((url) => {
      if (/\/admin\/orders\/\d+$/.test(url)) return { ok: true, status: 200, json: async () => orderDetailResponse } as Response;
      return {
        ok: true,
        status: 200,
        json: async () => ({
          status: 'success',
          data: [{ id: 9, order_id: 'ORD-009', status: 'new', total_amount: '1000.00', items: [], created_at: null, updated_at: null }],
          meta: { has_more: false, limit: 100, cursor: 0, total: 1 },
        }),
      } as Response;
    });

    const { default: Page } = await import('@/app/admin/orders/page');
    render(<Page />);

    await waitFor(() => expect(screen.getByText('ORD-009')).toBeInTheDocument());
    expect(screen.getAllByText('\u2014').length).toBeGreaterThanOrEqual(1);
  });

  it('keeps the approve flow working: approving calls the endpoint and reloads the list', async () => {
    const { default: Page } = await import('@/app/admin/orders/page');
    render(<Page />);

    await waitFor(() => expect(screen.getByText('ORD-002')).toBeInTheDocument());

    const listCallsBefore = fetchMock.mock.calls.filter((c) => String(c[0]).includes('/admin/orders')).length;

    const row = screen.getByText('ORD-002').closest('tr') as HTMLTableRowElement;
    fireEvent.click(within(row).getByRole('button', { name: 'Setujui' }));

    await waitFor(() => {
      const approveCall = fetchMock.mock.calls.find((c) => String(c[0]).includes('/approve'));
      expect(approveCall).toBeTruthy();
      expect(String(approveCall?.[0])).toContain('/orders/2/approve');
      expect((approveCall?.[1] as { method?: string } | undefined)?.method).toBe('PUT');
    });

    await waitFor(() => {
      const listCallsAfter = fetchMock.mock.calls.filter((c) => String(c[0]).includes('/admin/orders')).length;
      expect(listCallsAfter).toBe(listCallsBefore + 1);
    });
  });

  it('loads the order detail on row click (show logic unchanged)', async () => {
    const { default: Page } = await import('@/app/admin/orders/page');
    render(<Page />);

    await waitFor(() => expect(screen.getByText('ORD-002')).toBeInTheDocument());

    fireEvent.click(screen.getByText('ORD-002'));

    await waitFor(() => {
      const detailCall = fetchMock.mock.calls.find((c) => /\/admin\/orders\/2$/.test(String(c[0])));
      expect(detailCall).toBeTruthy();
    });
    await waitFor(() => expect(screen.getByText('Riwayat status')).toBeInTheDocument());
  });
});

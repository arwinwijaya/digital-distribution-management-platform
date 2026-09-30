import React from 'react';
import '@testing-library/jest-dom';
import { render, screen, waitFor, fireEvent, within } from '@testing-library/react';

const mockGetStoredToken = jest.fn(() => 'admin-token');
jest.mock('@/lib/api', () => ({
  apiUrl: (p: string) => `http://localhost:8000/api${p}`,
  authHeaders: (token: string) => ({ Authorization: `Bearer ${token}`, Accept: 'application/json', 'Content-Type': 'application/json' }),
  getStoredToken: (...args: unknown[]) => (mockGetStoredToken as (...a: unknown[]) => string | null)(...args),
  clearStoredToken: jest.fn(),
}));

function jsonResponse(body: unknown, ok = true, status = ok ? 200 : 500): Response {
  return { ok, status, json: async () => body } as Response;
}

const listEnvelope = {
  status: 'success',
  data: [
    {
      id: 201,
      supplier_id: 11,
      supplier: { id: 11, name: 'Supplier Beras Nusantara' },
      created_by: 1,
      approved_by: null,
      executed_by: null,
      status: 'draft',
      window_start: '2026-09-01',
      window_end: '2026-09-30',
      approved_at: null,
      executed_at: null,
      execution_result: null,
      metadata: {},
      items: [
        { product_id: 501, product: { id: 501, name: 'Beras Premium', price: 74000 }, reorder_quantity: 25, data_sufficiency: 'sufficient', metadata: {} },
      ],
      idempotent_replay: false,
      created_at: '2026-09-24T08:00:00Z',
      updated_at: '2026-09-24T08:10:00Z',
    },
    {
      id: 202,
      supplier_id: 12,
      supplier: { id: 12, name: 'Supplier Minyak Jaya' },
      created_by: 1,
      approved_by: 2,
      executed_by: null,
      status: 'approved',
      window_start: '2026-09-01',
      window_end: '2026-09-30',
      approved_at: '2026-09-24T09:00:00Z',
      executed_at: null,
      execution_result: null,
      metadata: {},
      items: [
        { product_id: 502, product: { id: 502, name: 'Minyak Goreng', price: 18000 }, reorder_quantity: 40, data_sufficiency: 'limited', metadata: {} },
      ],
      idempotent_replay: false,
      created_at: '2026-09-23T08:00:00Z',
      updated_at: '2026-09-23T09:00:00Z',
    },
    {
      id: 203,
      supplier_id: 13,
      supplier: { id: 13, name: 'Supplier Gula Sentosa' },
      created_by: 1,
      approved_by: 2,
      executed_by: 3,
      status: 'executed',
      window_start: '2026-09-01',
      window_end: '2026-09-30',
      approved_at: '2026-09-22T09:00:00Z',
      executed_at: '2026-09-22T10:00:00Z',
      execution_result: { purchase_orders: [{ reference: 'PO-2026-0007', items: [] }] },
      metadata: {},
      items: [
        { product_id: 503, product: { id: 503, name: 'Gula Pasir', price: 16500 }, reorder_quantity: 18, data_sufficiency: 'sufficient', metadata: {} },
      ],
      idempotent_replay: false,
      created_at: '2026-09-22T08:00:00Z',
      updated_at: '2026-09-22T10:00:00Z',
    },
  ],
  meta: {
    has_more: false,
    limit: 15,
    cursor: 0,
    total: 3,
    summary: { total: 3, draft: 1, approved: 1, executed: 1, rejected: 0, failed: 0, cancelled: 0 },
  },
};

describe('admin supply-chain replenishment — cycle 1: render, status actions, PO refs, envelope error', () => {
  let fetchMock: jest.Mock;
  let originalFetch: typeof fetch | undefined;

  beforeEach(() => {
    mockGetStoredToken.mockReturnValue('admin-token');
    originalFetch = (globalThis as unknown as { fetch?: typeof fetch }).fetch;
    fetchMock = jest.fn(async (url: unknown) => {
      const target = String(url);
      if (target.includes('/auth/me')) return jsonResponse({ status: 'success', data: { role: 'platform_owner', rbac: {} } });
      if (target.includes('/admin/replenishment-plans/201/approve')) return jsonResponse({ status: 'success', data: { ...listEnvelope.data[0], status: 'approved' } });
      if (target.includes('/admin/replenishment-plans/202/execute')) return jsonResponse({ status: 'success', data: { ...listEnvelope.data[1], status: 'executed', execution_result: { purchase_orders: [{ reference: 'PO-2026-0010', items: [] }] } } });
      if (target.includes('/admin/replenishment-plans/generate')) return jsonResponse({ status: 'success', data: { ...listEnvelope.data[0], id: 204 } });
      if (target.includes('/admin/replenishment-plans')) return jsonResponse(listEnvelope);
      return jsonResponse({ status: 'success', data: {} });
    });
    (globalThis as unknown as { fetch: unknown }).fetch = fetchMock;
  });

  afterEach(() => {
    if (originalFetch) (globalThis as unknown as { fetch: unknown }).fetch = originalFetch;
    else delete (globalThis as unknown as { fetch?: unknown }).fetch;
    jest.clearAllMocks();
    mockGetStoredToken.mockReturnValue('admin-token');
  });

  function lastListUrl(): string {
    const calls = fetchMock.mock.calls.filter((c) => String(c[0]).includes('/admin/replenishment-plans?'));
    return String(calls[calls.length - 1]?.[0] ?? '');
  }

  it('renders generate button, admin-table summary, paging, status-aware actions, and executed PO references', async () => {
    const { default: Page } = await import('@/app/admin/supply-chain/page');
    render(<Page />);

    await waitFor(() => expect(screen.getByText('Supplier Beras Nusantara')).toBeInTheDocument());
    expect(screen.getByRole('button', { name: 'Generate rencana' })).toBeEnabled();
    expect(screen.getByText('Supplier Minyak Jaya')).toBeInTheDocument();
    expect(screen.getByText('Supplier Gula Sentosa')).toBeInTheDocument();
    expect(screen.getByText('PO-2026-0007')).toBeInTheDocument();
    expect(screen.getByTestId('table-summary')).toHaveTextContent('3 rencana');
    expect(screen.getByTestId('table-summary')).toHaveTextContent('1 draft');
    expect(screen.getByTestId('table-pagination')).toHaveTextContent('Halaman 1 dari 1');

    const firstListUrl = lastListUrl();
    expect(firstListUrl).toContain('sort=created_at');
    expect(firstListUrl).toContain('order=desc');
    expect(firstListUrl).toContain('cursor=0');
    expect(firstListUrl).toContain('limit=15');

    const draftRow = screen.getByText('Supplier Beras Nusantara').closest('tr') as HTMLTableRowElement;
    expect(within(draftRow).getByRole('button', { name: 'Approve' })).toBeEnabled();
    expect(within(draftRow).queryByRole('button', { name: 'Execute' })).not.toBeInTheDocument();

    const approvedRow = screen.getByText('Supplier Minyak Jaya').closest('tr') as HTMLTableRowElement;
    expect(within(approvedRow).getByRole('button', { name: 'Execute' })).toBeEnabled();
    expect(within(approvedRow).queryByRole('button', { name: 'Approve' })).not.toBeInTheDocument();

    const executedRow = screen.getByText('Supplier Gula Sentosa').closest('tr') as HTMLTableRowElement;
    expect(within(executedRow).queryByRole('button', { name: 'Approve' })).not.toBeInTheDocument();
    expect(within(executedRow).queryByRole('button', { name: 'Execute' })).not.toBeInTheDocument();
  });

  it('delegates generate, approve, and execute actions through the replenishment API client and reloads', async () => {
    const { default: Page } = await import('@/app/admin/supply-chain/page');
    render(<Page />);
    await waitFor(() => expect(screen.getByText('Supplier Beras Nusantara')).toBeInTheDocument());

    fireEvent.click(screen.getByRole('button', { name: 'Generate rencana' }));
    await waitFor(() => {
      const call = fetchMock.mock.calls.find((c) => String(c[0]).includes('/admin/replenishment-plans/generate'));
      expect(call).toBeTruthy();
      expect((call?.[1] as { method?: string } | undefined)?.method).toBe('POST');
    });

    fireEvent.click(within(screen.getByText('Supplier Beras Nusantara').closest('tr') as HTMLTableRowElement).getByRole('button', { name: 'Approve' }));
    await waitFor(() => expect(fetchMock.mock.calls.some((c) => String(c[0]).includes('/admin/replenishment-plans/201/approve'))).toBe(true));

    fireEvent.click(within(screen.getByText('Supplier Minyak Jaya').closest('tr') as HTMLTableRowElement).getByRole('button', { name: 'Execute' }));
    await waitFor(() => {
      const call = fetchMock.mock.calls.find((c) => String(c[0]).includes('/admin/replenishment-plans/202/execute'));
      expect(call).toBeTruthy();
      expect((call?.[1] as { body?: string } | undefined)?.body).toContain('logical_key');
    });

    expect(fetchMock.mock.calls.filter((c) => String(c[0]).includes('/admin/replenishment-plans?')).length).toBeGreaterThanOrEqual(4);
  });

  it('shows an alert when the API returns an envelope error', async () => {
    fetchMock.mockImplementation(async (url: unknown) => {
      const target = String(url);
      if (target.includes('/auth/me')) return jsonResponse({ status: 'success', data: { role: 'admin', rbac: {} } });
      if (target.includes('/admin/replenishment-plans')) return jsonResponse({ status: 'error', message: 'Rencana replenishment gagal dimuat', data: [] }, false, 500);
      return jsonResponse({ status: 'success', data: {} });
    });

    const { default: Page } = await import('@/app/admin/supply-chain/page');
    render(<Page />);

    await waitFor(() => expect(screen.getByRole('alert')).toHaveTextContent('Rencana replenishment gagal dimuat'));
  });
});

describe('admin supply-chain replenishment — cycle 2: empty state', () => {
  let fetchMock: jest.Mock;
  let originalFetch: typeof fetch | undefined;

  beforeEach(() => {
    mockGetStoredToken.mockReturnValue('admin-token');
    originalFetch = (globalThis as unknown as { fetch?: typeof fetch }).fetch;
    fetchMock = jest.fn(async (url: unknown) => {
      const target = String(url);
      if (target.includes('/auth/me')) return jsonResponse({ status: 'success', data: { role: 'admin', rbac: {} } });
      if (target.includes('/admin/replenishment-plans')) return jsonResponse({ status: 'success', data: [], meta: { has_more: false, limit: 15, cursor: 0, total: 0, summary: { total: 0, draft: 0, approved: 0, executed: 0, rejected: 0, failed: 0, cancelled: 0 } } });
      return jsonResponse({ status: 'success', data: {} });
    });
    (globalThis as unknown as { fetch: unknown }).fetch = fetchMock;
  });

  afterEach(() => {
    if (originalFetch) (globalThis as unknown as { fetch: unknown }).fetch = originalFetch;
    else delete (globalThis as unknown as { fetch?: unknown }).fetch;
    jest.clearAllMocks();
    mockGetStoredToken.mockReturnValue('admin-token');
  });

  it('shows an informative empty state when no plans exist', async () => {
    const { default: Page } = await import('@/app/admin/supply-chain/page');
    render(<Page />);

    await waitFor(() => expect(screen.getByText('Belum ada rencana replenishment')).toBeInTheDocument());
    expect(screen.getByText('Generate rencana untuk memulai proses pengadaan stok dari supplier.')).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Generate rencana' })).toBeEnabled();
  });
});

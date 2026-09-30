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
      id: 101,
      type: 'draft_order',
      status: 'draft',
      outlet_id: 7,
      payload: { outlet_name: 'Outlet Mawar', total_amount: 125000 },
      method: 'stock_recommendation',
      method_version: 'v1',
      fallback: false,
      data_sufficiency: 'sufficient',
      created_at: '2026-09-24T08:00:00Z',
      updated_at: '2026-09-24T08:10:00Z',
    },
    {
      id: 102,
      type: 'draft_campaign',
      status: 'approved',
      outlet_id: null,
      payload: { name: 'Promo Akhir Pekan', discount_value: 10 },
      method: 'campaign_recommendation',
      method_version: 'v1',
      fallback: true,
      data_sufficiency: 'limited',
      created_at: '2026-09-23T08:00:00Z',
      updated_at: '2026-09-23T09:00:00Z',
    },
    {
      id: 103,
      type: 'draft_order',
      status: 'executed',
      outlet_id: 8,
      payload: { outlet_name: 'Outlet Melati' },
      method: 'stock_recommendation',
      method_version: 'v1',
      fallback: false,
      data_sufficiency: 'sufficient',
      created_at: '2026-09-22T08:00:00Z',
      updated_at: '2026-09-22T09:00:00Z',
    },
  ],
  meta: { has_more: false, limit: 15, cursor: 0, total: 3 },
};

describe('admin ai actions inbox — cycle 1: render, actions, envelope error', () => {
  let fetchMock: jest.Mock;
  let originalFetch: typeof fetch | undefined;

  beforeEach(() => {
    mockGetStoredToken.mockReturnValue('admin-token');
    originalFetch = (globalThis as unknown as { fetch?: typeof fetch }).fetch;
    fetchMock = jest.fn(async (url: unknown) => {
      const target = String(url);
      if (target.includes('/auth/me')) return jsonResponse({ status: 'success', data: { role: 'admin', rbac: {} } });
      if (target.includes('/admin/recommendation-actions/101/approve')) return jsonResponse({ status: 'success', data: { ...listEnvelope.data[0], status: 'approved' } });
      if (target.includes('/admin/recommendation-actions/101/reject')) return jsonResponse({ status: 'success', data: { ...listEnvelope.data[0], status: 'rejected' } });
      if (target.includes('/admin/recommendation-actions/102/execute')) return jsonResponse({ status: 'success', data: { ...listEnvelope.data[1], status: 'executed' } });
      if (target.includes('/admin/recommendation-actions')) return jsonResponse(listEnvelope);
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
    const calls = fetchMock.mock.calls.filter((c) => String(c[0]).includes('/admin/recommendation-actions?'));
    return String(calls[calls.length - 1]?.[0] ?? '');
  }

  it('renders endpoint actions with admin-table summary, sort, paging, and row-specific actions', async () => {
    const { default: Page } = await import('@/app/admin/ai-actions/page');
    render(<Page />);

    await waitFor(() => expect(screen.getByText('Outlet Mawar')).toBeInTheDocument());
    expect(screen.getByText('Promo Akhir Pekan')).toBeInTheDocument();
    expect(screen.getByText('Outlet Melati')).toBeInTheDocument();
    expect(screen.getByTestId('table-summary')).toHaveTextContent('3 aksi');
    expect(screen.getByTestId('table-summary')).toHaveTextContent('1 draft');
    expect(screen.getByTestId('table-pagination')).toHaveTextContent('Halaman 1 dari 1');

    const firstListUrl = lastListUrl();
    expect(firstListUrl).toContain('sort=created_at');
    expect(firstListUrl).toContain('order=desc');
    expect(firstListUrl).toContain('cursor=0');
    expect(firstListUrl).toContain('limit=15');

    const draftRow = screen.getByText('Outlet Mawar').closest('tr') as HTMLTableRowElement;
    expect(within(draftRow).getByRole('button', { name: 'Setujui' })).toBeEnabled();
    expect(within(draftRow).getByRole('button', { name: 'Tolak' })).toBeEnabled();
    expect(within(draftRow).queryByRole('button', { name: 'Eksekusi' })).not.toBeInTheDocument();

    const approvedRow = screen.getByText('Promo Akhir Pekan').closest('tr') as HTMLTableRowElement;
    expect(within(approvedRow).getByRole('button', { name: 'Eksekusi' })).toBeEnabled();
    expect(within(approvedRow).queryByRole('button', { name: 'Setujui' })).not.toBeInTheDocument();

    const executedRow = screen.getByText('Outlet Melati').closest('tr') as HTMLTableRowElement;
    expect(within(executedRow).queryByRole('button', { name: 'Setujui' })).not.toBeInTheDocument();
    expect(within(executedRow).queryByRole('button', { name: 'Eksekusi' })).not.toBeInTheDocument();
  });

  it('delegates approve, reject, and execute actions to the ai-actions API client and reloads', async () => {
    const { default: Page } = await import('@/app/admin/ai-actions/page');
    render(<Page />);
    await waitFor(() => expect(screen.getByText('Outlet Mawar')).toBeInTheDocument());

    fireEvent.click(within(screen.getByText('Outlet Mawar').closest('tr') as HTMLTableRowElement).getByRole('button', { name: 'Setujui' }));
    await waitFor(() => {
      const call = fetchMock.mock.calls.find((c) => String(c[0]).includes('/admin/recommendation-actions/101/approve'));
      expect(call).toBeTruthy();
      expect((call?.[1] as { method?: string } | undefined)?.method).toBe('POST');
    });

    fireEvent.click(within(screen.getByText('Outlet Mawar').closest('tr') as HTMLTableRowElement).getByRole('button', { name: 'Tolak' }));
    await waitFor(() => expect(fetchMock.mock.calls.some((c) => String(c[0]).includes('/admin/recommendation-actions/101/reject'))).toBe(true));

    fireEvent.click(within(screen.getByText('Promo Akhir Pekan').closest('tr') as HTMLTableRowElement).getByRole('button', { name: 'Eksekusi' }));
    await waitFor(() => expect(fetchMock.mock.calls.some((c) => String(c[0]).includes('/admin/recommendation-actions/102/execute'))).toBe(true));

    expect(fetchMock.mock.calls.filter((c) => String(c[0]).includes('/admin/recommendation-actions?')).length).toBeGreaterThanOrEqual(4);
  });

  it('shows an alert when the API returns an envelope error', async () => {
    // Override the default fetch mock for this test BEFORE page mount
    fetchMock.mockImplementation(async (url: unknown) => {
      const target = String(url);
      if (target.includes('/auth/me')) return jsonResponse({ status: 'success', data: { role: 'admin', rbac: {} } });
      if (target.includes('/admin/recommendation-actions')) return jsonResponse({ status: 'error', message: 'Inbox gagal dimuat', data: [] }, false, 500);
      return jsonResponse({ status: 'success', data: {} });
    });

    const { default: Page } = await import('@/app/admin/ai-actions/page');
    render(<Page />);

    await waitFor(() => expect(screen.getByRole('alert')).toHaveTextContent('Inbox gagal dimuat'));
  });
});

describe('admin ai actions inbox — cycle 2: non-admin access guard', () => {
  let fetchMock: jest.Mock;
  let originalFetch: typeof fetch | undefined;

  beforeEach(() => {
    mockGetStoredToken.mockReturnValue('non-admin-token');
    originalFetch = (globalThis as unknown as { fetch?: typeof fetch }).fetch;
    fetchMock = jest.fn(async (url: unknown) => {
      const target = String(url);
      if (target.includes('/auth/me')) return jsonResponse({ status: 'success', data: { role: 'finance', rbac: {} } });
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

  it('blocks non-admin roles (e.g., finance) without fetching the inbox and shows access denied', async () => {
    const { default: Page } = await import('@/app/admin/ai-actions/page');
    render(<Page />);

    await waitFor(() => expect(screen.getByRole('alert')).toBeInTheDocument());
    expect(screen.getByRole('alert').textContent).toMatch(/akses ditolak/i);
    expect(screen.queryByTestId('table-summary')).not.toBeInTheDocument();
    expect(screen.queryByTestId('table-pagination')).not.toBeInTheDocument();
    expect(fetchMock.mock.calls.some((c) => String(c[0]).includes('/admin/recommendation-actions?'))).toBe(false);
  });
});

import React from 'react';
import '@testing-library/jest-dom';
import { render, screen, waitFor, within } from '@testing-library/react';

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

const experiments = [
  {
    id: 41,
    experiment_key: 'checkout-banner-v1',
    name: 'Checkout banner',
    status: 'running',
    minimum_sample_size: 30,
    assignment_summary: { total: 84, control: 42, treatment: 42 },
    starts_at: '2026-09-01T00:00:00Z',
    ends_at: null,
    created_at: '2026-09-01T00:00:00Z',
  },
  {
    id: 42,
    experiment_key: 'free-shipping-v1',
    name: 'Free shipping',
    status: 'running',
    minimum_sample_size: 30,
    assignment_summary: { total: 4, control: 2, treatment: 2 },
    starts_at: '2026-09-02T00:00:00Z',
    ends_at: null,
    created_at: '2026-09-02T00:00:00Z',
  },
];

const liftByExperiment: Record<number, unknown> = {
  41: {
    id: 501,
    experiment_id: 41,
    uplift: '0.125000',
    status: 'computed',
    method_version: 'lift-mean-v1',
    control_sample_size: 42,
    treatment_sample_size: 42,
    control_revenue: '1000000.00',
    treatment_revenue: '1125000.00',
    metadata: { sufficient: true },
    created_at: '2026-09-30T00:00:00Z',
  },
  42: {
    id: 502,
    experiment_id: 42,
    uplift: null,
    status: 'insufficient-data',
    method_version: 'lift-mean-v1',
    control_sample_size: 2,
    treatment_sample_size: 2,
    control_revenue: '100.00',
    treatment_revenue: '130.00',
    metadata: { sufficient: false, minimum_sample_size: 30 },
    created_at: '2026-09-30T00:00:00Z',
  },
};

describe('admin AI experiments dashboard — cycle 1: list, lift cards, insufficient data', () => {
  let fetchMock: jest.Mock;
  let originalFetch: typeof fetch | undefined;

  beforeEach(() => {
    mockGetStoredToken.mockReturnValue('admin-token');
    originalFetch = (globalThis as unknown as { fetch?: typeof fetch }).fetch;
    fetchMock = jest.fn(async (url: unknown) => {
      const target = String(url);
      if (target.includes('/auth/me')) return jsonResponse({ status: 'success', data: { role: 'admin', rbac: {} } });
      if (target.includes('/admin/ai-experiments/41/revenue-lift')) return jsonResponse({ status: 'success', data: liftByExperiment[41] });
      if (target.includes('/admin/ai-experiments/42/revenue-lift')) return jsonResponse({ status: 'success', data: liftByExperiment[42] });
      if (target.includes('/admin/ai-experiments')) return jsonResponse({ status: 'success', data: experiments, meta: { has_more: false, limit: 15, cursor: 0, total: 2 } });
      return jsonResponse({ status: 'success', data: {} });
    });
    (globalThis as unknown as { fetch: unknown }).fetch = fetchMock;
  });

  afterEach(() => {
    if (originalFetch) (globalThis as unknown as { fetch: unknown }).fetch = originalFetch;
    else delete (globalThis as unknown as { fetch?: unknown }).fetch;
    jest.clearAllMocks();
  });

  it('renders the experiment list, an uplift card, and an honest insufficient-data notice', async () => {
    const { default: Page } = await import('@/app/admin/ai-experiments/page');
    render(<Page />);

    await waitFor(() => expect(screen.getAllByText('Checkout banner').length).toBeGreaterThan(0));

    // Experiment list is an accessible, sorted, paginated table.
    expect(screen.getByRole('table')).toBeInTheDocument();
    expect(screen.getByTestId('table-summary')).toHaveTextContent('2 eksperimen');
    expect(screen.getByTestId('table-pagination')).toHaveTextContent('Halaman 1 dari 1');
    const listCall = fetchMock.mock.calls.find((call) => String(call[0]).includes('/admin/ai-experiments?'));
    expect(listCall).toBeTruthy();
    expect(String(listCall?.[0])).toContain('sort=created_at');

    // Computed lift card: uplift percent + sample status + method version.
    const computedCard = screen.getByTestId('lift-card-41');
    expect(within(computedCard).getByText('12,50%')).toBeInTheDocument();
    expect(within(computedCard).getByText(/42 control · 42 treatment/)).toBeInTheDocument();
    expect(within(computedCard).getByText(/lift-mean-v1/)).toBeInTheDocument();

    // Insufficient card: pending notice, sample/method stated, never 0/perfect.
    const insufficientCard = screen.getByTestId('lift-card-42');
    expect(within(insufficientCard).getByText(/Data belum cukup/i)).toBeInTheDocument();
    expect(within(insufficientCard).getByText(/sampel/i)).toBeInTheDocument();
    expect(within(insufficientCard).queryByText(/0,00%/)).not.toBeInTheDocument();
    expect(within(insufficientCard).queryByText(/100,00%/)).not.toBeInTheDocument();
    expect(screen.queryByText(/perfect/i)).not.toBeInTheDocument();
  });

  it('shows an alert when the experiment list envelope fails', async () => {
    fetchMock.mockImplementation(async (url: unknown) => {
      const target = String(url);
      if (target.includes('/auth/me')) return jsonResponse({ status: 'success', data: { role: 'admin' } });
      if (target.includes('/admin/ai-experiments')) return jsonResponse({ status: 'error', message: 'Eksperimen gagal dimuat', data: [] }, false, 500);
      return jsonResponse({ status: 'success', data: {} });
    });

    const { default: Page } = await import('@/app/admin/ai-experiments/page');
    render(<Page />);
    await waitFor(() => expect(screen.getByRole('alert')).toHaveTextContent('Eksperimen gagal dimuat'));
  });
});

/**
 * Test: operations page consumes shared API contract.
 * Tests readiness display, disabled state, loading, errors, filters, and issue detail.
 */
import React from 'react';
import '@testing-library/jest-dom';
import { render, screen, waitFor, fireEvent } from '@testing-library/react';

const mockGetStoredToken = jest.fn(() => 'test-token');
jest.mock('@/lib/api', () => ({
  apiUrl: (p: string) => `http://localhost:8000/api${p}`,
  authHeaders: (token: string) => ({ Authorization: `Bearer ${token}` }),
  getStoredToken: (...args: unknown[]) => (mockGetStoredToken as (...a: unknown[]) => string | null)(...args),
}));

const readinessResponse = {
  status: 'success',
  data: {
    status: 'ready',
    checks: [
      { name: 'database', status: 'ok', evidence: 'tables exist', remediation: 'none' },
      { name: 'redis', status: 'warn', evidence: 'high memory', remediation: 'scale up' },
    ],
    evaluated_at: '2026-09-15T10:00:00Z',
    correlation_id: 'abc-123',
  },
};

const issuesResponse = {
  status: 'success',
  data: [
    {
      id: 1,
      source: 'order_validation',
      reference: 'ORD-100',
      status: 'open',
      severity: 'high',
      attempts: 3,
      occurred_at: '2026-09-15T09:00:00Z',
      error_class: 'ValidationError',
      correlation_id: 'corr-456',
      next_action: 'retry',
    },
    {
      id: 2,
      source: 'product_sync',
      reference: 'SKU-200',
      status: 'resolved',
      severity: 'low',
      attempts: 1,
      occurred_at: '2026-09-14T08:00:00Z',
      error_class: null,
      correlation_id: null,
      next_action: null,
    },
  ],
  meta: { page: 1, limit: 20, total: 2, has_more: false },
};

const issueDetailResponse = {
  status: 'success',
  data: {
    id: 1,
    source: 'order_validation',
    reference: 'ORD-100',
    status: 'open',
    severity: 'high',
    attempts: 3,
    occurred_at: '2026-09-15T09:00:00Z',
    error_class: 'ValidationError',
    correlation_id: 'corr-456',
    next_action: 'retry',
    detail: { raw: 'field missing', field: 'outlet_id' },
  },
};

const disabledResponse = {
  status: 'error',
  code: 'pre_pilot_disabled',
  message: 'Pre-pilot not enabled',
};

function buildFetch(bodies: Record<string, { body: unknown; status?: number; ok?: boolean }>) {
  return jest.fn(async (url: RequestInfo) => {
    const urlString = String(url);
    if (urlString.includes('/auth/me')) {
      return { ok: true, status: 200, json: async () => ({ status: 'success', data: { role: 'admin' } }) } as Response;
    }
    if (urlString.includes('/admin/operations/readiness')) {
      const entry = bodies['readiness'];
      if (entry) {
        return { ok: entry.ok ?? false, status: entry.status ?? 503, json: async () => entry.body } as Response;
      }
      return { ok: true, status: 200, json: async () => readinessResponse } as Response;
    }
    if (urlString.includes('/admin/operations/issues/')) {
      const entry = bodies['issueDetail'];
      if (entry) {
        return { ok: entry.ok ?? false, status: entry.status ?? 503, json: async () => entry.body } as Response;
      }
      return { ok: true, status: 200, json: async () => issueDetailResponse } as Response;
    }
    if (urlString.includes('/admin/operations/issues')) {
      const entry = bodies['issues'];
      if (entry) {
        return { ok: entry.ok ?? false, status: entry.status ?? 503, json: async () => entry.body } as Response;
      }
      return { ok: true, status: 200, json: async () => issuesResponse } as Response;
    }
    return { ok: true, status: 200, json: async () => ({ status: 'success', data: {} }) } as Response;
  });
}

describe('operations page', () => {
  let originalFetch: typeof fetch | undefined;

  beforeEach(() => {
    mockGetStoredToken.mockReturnValue('test-token');
    originalFetch = (globalThis as unknown as { fetch?: typeof fetch }).fetch;
  });

  afterEach(() => {
    if (originalFetch) {
      (globalThis as unknown as { fetch: unknown }).fetch = originalFetch;
    } else {
      delete (globalThis as unknown as { fetch?: unknown }).fetch;
    }
  });

  it('renders readiness data and issues table when API returns success', async () => {
    (globalThis as unknown as { fetch: unknown }).fetch = buildFetch({});
    const { default: OperationsPage } = await import('@/app/operations/page');
    const { container } = render(<OperationsPage />);

    await waitFor(() => {
      expect(screen.getByText(/ready/)).toBeInTheDocument();
    });

    // Readiness checks rendered
    expect(screen.getByText('database')).toBeInTheDocument();
    expect(screen.getByText('redis')).toBeInTheDocument();
    expect(screen.getByText('tables exist')).toBeInTheDocument();
    expect(screen.getByText('scale up')).toBeInTheDocument();
    expect(screen.getByText(/abc-123/)).toBeInTheDocument();

    // Issues table scoped to avoid matching <option> labels
    const issuesTable = container.querySelector('[data-testid="operations-issues-table"]');
    expect(issuesTable).toBeInTheDocument();
    const scoped = (q: (e: HTMLElement) => HTMLElement | null) => q(issuesTable as unknown as HTMLElement);
    expect(scoped((e) => e.querySelector('td'))).toBeInTheDocument();
    const rows = (issuesTable as unknown as HTMLElement).querySelectorAll('tbody tr');
    expect(rows.length).toBe(2);
    expect((issuesTable as unknown as HTMLElement).textContent).toContain('order_validation');
    expect((issuesTable as unknown as HTMLElement).textContent).toContain('ORD-100');
    expect((issuesTable as unknown as HTMLElement).textContent).toContain('corr-456');
    expect((issuesTable as unknown as HTMLElement).textContent).toContain('retry');
  });

  it('shows disabled state when readiness returns 503 pre_pilot_disabled', async () => {
    (globalThis as unknown as { fetch: unknown }).fetch = buildFetch({
      readiness: { body: disabledResponse, status: 503, ok: false },
      issues: { body: disabledResponse, status: 503, ok: false },
    });
    const { default: OperationsPage } = await import('@/app/operations/page');
    render(<OperationsPage />);

    await waitFor(() => {
      expect(screen.getByText(/Aktivasi diperlukan/)).toBeInTheDocument();
    });

    expect(screen.getByText(/pre-pilot sedang dinonaktifkan/)).toBeInTheDocument();
    // Readiness checks and issues should not render
    expect(screen.queryByText('database')).not.toBeInTheDocument();
    expect(screen.queryByText('order_validation')).not.toBeInTheDocument();
  });

  it('shows loading state while fetching', async () => {
    let resolveReadiness!: (v: unknown) => void;
    const pending = new Promise((resolve) => {
      resolveReadiness = resolve;
    });
    (globalThis as unknown as { fetch: unknown }).fetch = jest.fn(async (url: RequestInfo) => {
      const urlString = String(url);
      if (urlString.includes('/auth/me')) {
        return { ok: true, status: 200, json: async () => ({ status: 'success', data: { role: 'admin' } }) } as Response;
      }
      if (urlString.includes('/admin/operations/readiness') || urlString.includes('/admin/operations/issues')) {
        return pending as unknown as Response;
      }
      return { ok: true, status: 200, json: async () => ({ status: 'success', data: {} }) } as Response;
    });

    const { default: OperationsPage } = await import('@/app/operations/page');
    render(<OperationsPage />);

    // Initial loading for auth
    await waitFor(() => {
      expect(screen.getByText('Memuat...')).toBeInTheDocument();
    });

    resolveReadiness({
      ok: true, status: 200,
      json: async () => readinessResponse,
    });
  });

  it('shows error state when API fails', async () => {
    (globalThis as unknown as { fetch: unknown }).fetch = buildFetch({
      readiness: { body: { status: 'error', message: 'Server error' }, status: 500, ok: false },
      issues: { body: { status: 'error', message: 'Server error' }, status: 500, ok: false },
    });
    const { default: OperationsPage } = await import('@/app/operations/page');
    render(<OperationsPage />);

    await waitFor(() => {
      expect(screen.getByRole('alert')).toBeInTheDocument();
    });

    expect(screen.getByText(/Server error/)).toBeInTheDocument();
  });

  it('sends correct filter params when filters are applied', async () => {
    const fetchMock = buildFetch({});
    (globalThis as unknown as { fetch: unknown }).fetch = fetchMock;
    const { default: OperationsPage } = await import('@/app/operations/page');
    render(<OperationsPage />);

    await waitFor(() => {
      expect(screen.getByText(/ready/)).toBeInTheDocument();
    });

    const sourceSelect = screen.getByLabelText('Source');
    fireEvent.change(sourceSelect, { target: { value: 'order_validation' } });

    const severitySelect = screen.getByLabelText('Severity');
    fireEvent.change(severitySelect, { target: { value: 'high' } });

    const filterButton = screen.getByRole('button', { name: /Terapkan filter/ });
    fireEvent.click(filterButton);

    await waitFor(() => {
      const issuesCalls = fetchMock.mock.calls.filter(
        ([url]: [RequestInfo]) => String(url).includes('/admin/operations/issues'),
      );
      expect(issuesCalls.length).toBeGreaterThanOrEqual(2);
      const filteredCall = issuesCalls[issuesCalls.length - 1];
      const calledUrl = String(filteredCall![0]);
      expect(calledUrl).toContain('source=order_validation');
      expect(calledUrl).toContain('severity=high');
    });
  });

  it('opens detail modal when clicking an issue row', async () => {
    (globalThis as unknown as { fetch: unknown }).fetch = buildFetch({});
    const { default: OperationsPage } = await import('@/app/operations/page');
    render(<OperationsPage />);

    await waitFor(() => {
      expect(screen.getByText('ORD-100')).toBeInTheDocument();
    });

    const cell = screen.getByText('ORD-100');
    fireEvent.click(cell.closest('tr')!);

    await waitFor(() => {
      expect(screen.getByText('Detail isu')).toBeInTheDocument();
    });

    // Detail data rendered inside the modal
    await waitFor(() => {
      expect(screen.getByText('ValidationError')).toBeInTheDocument();
    });
  });

  it('shows empty state when no issues found', async () => {
    (globalThis as unknown as { fetch: unknown }).fetch = buildFetch({
      issues: { body: { status: 'success', data: [], meta: { page: 1, limit: 20, total: 0, has_more: false } }, status: 200, ok: true },
    });
    const { default: OperationsPage } = await import('@/app/operations/page');
    render(<OperationsPage />);

    await waitFor(() => {
      expect(screen.getByText(/Tidak ada isu/)).toBeInTheDocument();
    });
  });

  it('shows login form when not authenticated', async () => {
    mockGetStoredToken.mockReturnValue(undefined as unknown as string);
    (globalThis as unknown as { fetch: unknown }).fetch = buildFetch({});
    const { default: OperationsPage } = await import('@/app/operations/page');
    render(<OperationsPage />);

    await waitFor(() => {
      expect(screen.getByText(/Masuk ke akun/)).toBeInTheDocument();
    });
  });
});

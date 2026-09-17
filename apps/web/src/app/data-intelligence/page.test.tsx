/**
 * Test: admin page consumes shared API contract and retains table fallback.
 * Expected RED: page/API client/components do not exist yet.
 */
import React from 'react';
import '@testing-library/jest-dom';
import { render, screen, waitFor, act, within } from '@testing-library/react';

import { useDummyStore } from '@/dummy/store';
import { installDummy } from '@/dummy/install';

const mockGetStoredToken = jest.fn(() => 'test-token');
jest.mock('@/lib/api', () => ({
  apiUrl: (p: string) => `http://localhost:8000/api${p}`,
  authHeaders: (token: string) => ({ Authorization: `Bearer ${token}` }),
  getStoredToken: (...args: unknown[]) => (mockGetStoredToken as (...a: unknown[]) => string | null)(...args),
}));

const geographicResponse = {
  status: 'success',
  data: {
    table: [
      { territory: 'Jakarta Selatan', sales: '150000.00', orders: 12, outlets: 3 },
      { territory: 'Unassigned', sales: '25000.00', orders: 2, outlets: 1 },
    ],
    map_points: [
      { outlet_id: 1, outlet_name: 'Outlet A', territory: 'Jakarta Selatan', latitude: -6.2, longitude: 106.8, orders: 5, sales: '50000.00' },
    ],
    snapshot_version: 7,
    window: { start: '2026-08-16', end: '2026-09-14', timezone: 'Asia/Jakarta' },
  },
};

const supplierResponse = {
  status: 'success',
  data: {
    suppliers: [
      {
        supplier_id: 1,
        supplier_name: 'Supplier A',
        status: 'ok',
        fulfillment: { numerator: 10, denominator: 12, ratio: 0.8333 },
        on_time: { numerator: 9, denominator: 10, ratio: 0.9, eligible: 10, excluded: 2 },
        catalog: { numerator: 15, denominator: 18, ratio: 0.8333 },
        weighted_score: 0.85,
      },
    ],
    snapshot_version: 7,
    window: { start: '2026-08-16', end: '2026-09-14', timezone: 'Asia/Jakarta' },
  },
};

const stockResponse = {
  status: 'success',
  data: {
    items: [
      {
        product_id: 1,
        product_name: 'Product Alpha',
        sku: 'SKU-001',
        supplier_id: 1,
        lead_time_days: 7,
        available_stock: 20,
        total_demand: 60,
        average_daily_demand: 2,
        lead_time_demand: 14,
        reorder_quantity: 0,
        has_warning: false,
        status: 'ok',
      },
      {
        product_id: 2,
        product_name: 'Product Beta',
        sku: 'SKU-002',
        supplier_id: 1,
        lead_time_days: 5,
        available_stock: 3,
        total_demand: 50,
        average_daily_demand: 1.6667,
        lead_time_demand: 8.3333,
        reorder_quantity: 6,
        has_warning: true,
        status: 'reorder',
      },
    ],
    snapshot_version: 7,
    window: { start: '2026-08-16', end: '2026-09-14', timezone: 'Asia/Jakarta' },
  },
};

const recommendationMeasurementResponse = {
  status: 'success',
  data: {
    funnel: [
      { step: 'displayed', count: 120 },
      { step: 'clicked', count: 30 },
      { step: 'cart', count: 12 },
      { step: 'purchased', count: 6 },
    ],
    rates: {
      clicked_rate: 0.25,
      cart_rate: 0.4,
      purchased_rate: 0.5,
      overall_conversion_rate: 0.05,
    },
    attribution: { method: 'order_match' },
    snapshot_version: 7,
    window: { start: '2026-08-16', end: '2026-09-14', timezone: 'Asia/Jakarta' },
  },
};

const forecastMeasurementResponse = {
  status: 'success',
  data: {
    status: 'target_achieved',
    wape: '0.2000',
    accuracy: 0.8,
    accuracy_percent: 80.0,
    actual_days: 30,
    minimum_required_days: 30,
    target_achieved: true,
    target: 'accuracy_strictly_above_70_percent',
    snapshot_version: 7,
    window: { start: '2026-08-16', end: '2026-09-14', timezone: 'Asia/Jakarta' },
    note: 'OK',
  },
};

function mockFetchForSuccess() {
  const fetchSpy = jest.fn(async (url: RequestInfo) => {
    const urlString = String(url);
    let body: unknown;
    if (urlString.includes('/admin/analytics/geographic')) body = geographicResponse;
    else if (urlString.includes('/admin/analytics/measurement/forecasts')) body = forecastMeasurementResponse;
    else if (urlString.includes('/admin/analytics/measurement/recommendations')) body = recommendationMeasurementResponse;
    else if (urlString.includes('/admin/analytics/stock-planning')) body = stockResponse;
    else if (urlString.includes('/admin/analytics/suppliers')) body = supplierResponse;
    else body = { status: 'success', data: {} };

    return { ok: true, status: 200, json: async () => body } as Response;
  });
  return fetchSpy;
}

describe('admin page consumes shared API contract', () => {
  let originalFetch: typeof fetch | undefined;

  beforeEach(() => {
    mockGetStoredToken.mockReturnValue('test-token');
    useDummyStore.getState().reset();
    installDummy();
    originalFetch = (globalThis as unknown as { fetch?: typeof fetch }).fetch;
    (globalThis as unknown as { fetch: unknown }).fetch = jest.fn(async (...args: unknown[]) => {
      const url = String(args[0]);
      let body: unknown;
      if (url.includes('/admin/analytics/geographic')) body = geographicResponse;
      else if (url.includes('/admin/analytics/measurement/forecasts')) body = forecastMeasurementResponse;
      else if (url.includes('/admin/analytics/measurement/recommendations')) body = recommendationMeasurementResponse;
      else if (url.includes('/admin/analytics/stock-planning')) body = stockResponse;
      else if (url.includes('/admin/analytics/suppliers')) body = supplierResponse;
      else body = { status: 'success', data: {} };
      return { ok: true, status: 200, json: async () => body } as Response;
    });
  });

  afterEach(() => {
    if (originalFetch) {
      (globalThis as unknown as { fetch: unknown }).fetch = originalFetch;
    } else {
      delete (globalThis as unknown as { fetch?: unknown }).fetch;
    }
  });

  it('renders territory table data, supplier coverage/status, stock actions, WAPE/pending status', async () => {
    const { default: DataIntelligencePage } = await import('@/app/data-intelligence/page');
    render(<DataIntelligencePage />);

    await waitFor(() => {
      expect(screen.getByText(/Jakarta Selatan/)).toBeInTheDocument();
    });

    // Territory table data from shared contract
    expect(screen.getByText(/Jakarta Selatan/)).toBeInTheDocument();
    expect(screen.getByText(/Unassigned/)).toBeInTheDocument();

    // Money is rendered as id-ID Rupiah, not the raw fixed-2-decimal string
    expect(screen.getByText('Rp 150.000')).toBeInTheDocument();
    expect(screen.queryByText('150000.00')).not.toBeInTheDocument();

    // The shared Table renders real <th> headers and right-aligns numbers
    const territoryTable = screen.getByTestId('territory-table');
    expect(within(territoryTable).getByRole('columnheader', { name: 'Wilayah' })).toBeInTheDocument();
    expect(within(territoryTable).getByRole('columnheader', { name: 'Penjualan' })).toHaveClass('text-right');
    expect(within(territoryTable).getByRole('columnheader', { name: 'Outlet' })).toHaveClass('text-right');

    // Supplier coverage/status from shared contract
    expect(screen.getByText(/Supplier A/)).toBeInTheDocument();
    expect(screen.getByText(/0\.85/)).toBeInTheDocument();

    // Stock actions from shared contract
    expect(screen.getByText(/Product Alpha/)).toBeInTheDocument();
    expect(screen.getByText(/SKU-001/)).toBeInTheDocument();
    expect(screen.getAllByText(/^ok$/).length).toBeGreaterThan(0);

    // WAPE/status from shared contract
    expect(screen.getByText(/0\.2000/)).toBeInTheDocument();

    // Snapshot metadata shown
    expect(screen.getByText(/snapshot/i)).toBeInTheDocument();
  });

  it('handles an API error without hiding the table fallback', async () => {
    ((globalThis as unknown as { fetch: jest.Mock }).fetch as jest.Mock).mockImplementation(async () => ({
      ok: false,
      status: 500,
      json: async () => ({ status: 'error', message: 'Internal error' }),
    } as Response));

    const { default: DataIntelligencePage } = await import('@/app/data-intelligence/page');
    render(<DataIntelligencePage />);

    await waitFor(() => {
      expect(screen.getByRole('alert')).toBeInTheDocument();
    });

    // Table fallback headings are still rendered
    expect(screen.getAllByText(/Wilayah/i).length).toBeGreaterThan(0);
    expect(screen.getAllByText(/Supplier/i).length).toBeGreaterThan(0);
    expect(screen.getAllByText(/Stok/i).length).toBeGreaterThan(0);
  });

  it('frontend funnel event carries a UUID and replay-safe request', async () => {
    const fetchMock = (globalThis as unknown as { fetch: jest.Mock }).fetch as jest.Mock;
    const sentBodies: Array<{ event_uuid: string; event_type: string; outlet_id?: number | null; product_id?: number | null }> = [];
    fetchMock.mockImplementation(async (_url: unknown, options?: { body?: string }) => {
      const body = options?.body ? JSON.parse(options.body) : {};
      sentBodies.push(body);
      return {
        ok: true,
        status: 201,
        json: async () => ({ status: 'success', data: { event_uuid: body.event_uuid } }),
      } as Response;
    });

    const { buildFunnelEventPayload, sendFunnelEvent } = await import('@/lib/data-intelligence-api');

    const stableKey = buildFunnelEventPayload('clicked', { outlet_id: 1, product_id: 2 }).event_uuid;
    expect(stableKey).toMatch(/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i);

    const first = await sendFunnelEvent('clicked', { outlet_id: 1, product_id: 2 }, stableKey);
    expect(first.event_uuid).toBe(stableKey);
    expect(first.event_type).toBe('clicked');

    // Retry with the same idempotency key must not generate a new key.
    const retry = await sendFunnelEvent('clicked', { outlet_id: 1, product_id: 2 }, stableKey);
    expect(retry.event_uuid).toBe(stableKey);
    expect(sentBodies).toHaveLength(2);
    expect(sentBodies[0].event_uuid).toBe(stableKey);
    expect(sentBodies[1].event_uuid).toBe(stableKey);
    expect(sentBodies[0].event_type).toBe('clicked');
  });

  it('re-fetches and shows dummy data when Mode Dummy is toggled on', async () => {
    const { default: DataIntelligencePage } = await import('@/app/data-intelligence/page');
    render(<DataIntelligencePage />);

    // Dummy is OFF: real (mocked) territories render first.
    await waitFor(() => {
      expect(screen.getByText(/Jakarta Selatan/)).toBeInTheDocument();
    });
    const fetchMock = (globalThis as unknown as { fetch: jest.Mock }).fetch as jest.Mock;
    const geographicCalls = () =>
      fetchMock.mock.calls.filter((call) => String(call[0]).includes('/admin/analytics/geographic')).length;
    const callsBefore = geographicCalls();

    // Flip Mode Dummy ON — the page must reload from the dummy store.
    act(() => {
      useDummyStore.getState().toggle();
    });

    // Dummy-only territory (from JABODETABEK_TERRITORIES) renders, and no
    // extra network round-trip happens (dummy short-circuit in the API layer).
    await waitFor(() => {
      const table = screen.getByTestId('territory-table');
      expect(within(table).getByText('Bogor')).toBeInTheDocument();
    });
    expect(geographicCalls()).toBe(callsBefore);
  });
});

/**
 * T8 — dummy guards for data-intelligence-api + operations-api.
 *
 * Integration level: exercises the exported fetch functions against the REAL
 * dummy store + REAL guards. Only the network (global fetch) and the
 * generator payload (stub T6-shaped aggregates) are doubled.
 *
 * Cycle 1 (this file, part 1): DI reads return dummy aggregates with zero
 * network while ON; OFF path still hits the backend URL (no regression).
 */
import {
  fetchForecastMeasurementData,
  fetchGeographicData,
  fetchRecommendationMeasurementData,
  fetchStockPlanningData,
  fetchSupplierPerformanceData,
} from '@/lib/data-intelligence-api';
import {
  fetchReadiness,
  fetchIssues,
  fetchIssueDetail,
} from '@/lib/operations-api';
import { setDummyGenerator, useDummyStore } from '@/dummy/store';

const SNAPSHOT_WINDOW = {
  start: '2025-12-16',
  end: '2026-02-14',
  timezone: 'Asia/Jakarta',
};

function makeMapPoints(count: number): Array<Record<string, unknown>> {
  return Array.from({ length: count }, (_, i) => ({
    outlet_id: i + 1,
    outlet_name: `Toko Dummy ${i + 1}`,
    territory: 'Bogor',
    latitude: -6.5971,
    longitude: 106.806,
    orders: 10,
    sales: '100000.00',
  }));
}

/** Stub generator feeding T6-shaped aggregates (no backend, no Date.now). */
function stubAggregates(): Record<string, unknown> {
  return {
    geographic: {
      table: [
        { territory: 'Bogor', sales: '100000.00', orders: 10, outlets: 5 },
      ],
      map_points: makeMapPoints(50),
      snapshot_version: 1,
      window: { ...SNAPSHOT_WINDOW },
    },
    suppliers: {
      suppliers: [
        {
          supplier_id: 1,
          supplier_name: 'PT Dummy Pangan',
          status: 'ok',
          fulfillment: { numerator: 90, denominator: 100, ratio: 0.9 },
          on_time: {
            numerator: 80,
            denominator: 100,
            ratio: 0.8,
            eligible: 90,
            excluded: 10,
          },
          catalog: { numerator: 5, denominator: 30, ratio: 0.16 },
          weighted_score: 0.85,
        },
      ],
      snapshot_version: 1,
      window: { ...SNAPSHOT_WINDOW },
    },
    stock: {
      items: [
        {
          product_id: 1,
          product_name: 'Beras Premium Kemasan Kecil',
          sku: 'SKU-1001',
          supplier_id: 1,
          lead_time_days: 3,
          available_stock: 100,
          total_demand: 50,
          average_daily_demand: 0.83,
          lead_time_demand: 3,
          reorder_quantity: 2,
          has_warning: false,
          status: 'ok',
        },
      ],
      snapshot_version: 1,
      window: { ...SNAPSHOT_WINDOW },
    },
    measurement: {
      recommendations: {
        funnel: [
          { step: 'displayed', count: 120 },
          { step: 'clicked', count: 60 },
          { step: 'cart', count: 30 },
          { step: 'purchased', count: 12 },
        ],
        rates: {
          clicked_rate: 0.5,
          cart_rate: 0.5,
          purchased_rate: 0.4,
          overall_conversion_rate: 0.1,
        },
        attribution: {},
        snapshot_version: 1,
        window: { ...SNAPSHOT_WINDOW },
      },
      forecasts: {
        status: 'achieved',
        wape: '0.12',
        accuracy: 0.88,
        accuracy_percent: 88,
        actual_days: 60,
        minimum_required_days: 14,
        target_achieved: true,
        target: '80% accuracy on 14-day forecast',
        snapshot_version: 1,
        window: { ...SNAPSHOT_WINDOW },
        note: '60 hari data tersedia, target tercapai.',
      },
    },
    operations: {
      readiness: {
        status: 'ready',
        checks: [
          {
            name: 'Order completion rate',
            status: 'ok',
            evidence: '90% delivered',
            remediation: 'None',
          },
        ],
        evaluated_at: '2026-02-14T12:00:00+07:00',
        correlation_id: 'dummy-readiness-2026-02-14',
      },
      issues: [
        {
          id: 1,
          source: 'order_validation',
          reference: 'cancelled orders',
          status: 'open',
          severity: 'medium',
          attempts: 1,
          occurred_at: '2026-02-14T08:00:00+07:00',
          error_class: 'OrderValidationException',
          correlation_id: 'corr-cancel',
          next_action: 'Review cancellations',
        },
        {
          id: 2,
          source: 'inventory',
          reference: 'stock review',
          status: 'open',
          severity: 'info',
          attempts: 0,
          occurred_at: '2026-02-14T07:00:00+07:00',
          error_class: null,
          correlation_id: 'corr-inventory',
          next_action: 'Run stock planning',
        },
      ],
    },
  };
}

const realFetch = global.fetch;

beforeEach(() => {
  localStorage.clear();
  useDummyStore.getState().reset();
  localStorage.setItem('ddp_token', 't-token');
  setDummyGenerator(
    () => stubAggregates() as unknown as Record<string, unknown>,
  );
  global.fetch = jest.fn() as unknown as typeof fetch;
  // Default network stub so unguarded code fails on the zero-fetch
  // assertion (not on a TypeError) — the packet's expected RED message.
  (global.fetch as unknown as jest.Mock).mockResolvedValue({
    ok: true,
    json: async () => ({ status: 'success', data: {} }),
  });
});

afterEach(() => {
  global.fetch = realFetch;
  jest.restoreAllMocks();
});

describe('data-intelligence reads while dummy ON', () => {
  it('returns dummy aggregates with zero network', async () => {
    useDummyStore.getState().toggle();
    expect(useDummyStore.getState().isDummy).toBe(true);

    const geo = await fetchGeographicData();
    const suppliers = await fetchSupplierPerformanceData();
    const stock = await fetchStockPlanningData();
    const rec = await fetchRecommendationMeasurementData();
    const forecast = await fetchForecastMeasurementData();

    expect(geo.map_points.length).toBeGreaterThanOrEqual(40);
    expect(geo.map_points.length).toBeLessThanOrEqual(60);
    expect(geo.table.length).toBeGreaterThan(0);
    expect(suppliers.suppliers.length).toBeGreaterThan(0);
    expect(stock.items.length).toBeGreaterThan(0);
    expect(rec.funnel.length).toBeGreaterThan(0);
    expect(forecast.status).toBeDefined();

    expect(global.fetch).not.toHaveBeenCalled();
  });
});

describe('data-intelligence reads while dummy OFF', () => {
  it('still hits the backend geographic URL (no regression)', async () => {
    expect(useDummyStore.getState().isDummy).toBe(false);
    const payload = {
      table: [],
      map_points: [],
      snapshot_version: 1,
      window: { ...SNAPSHOT_WINDOW },
    };
    (global.fetch as unknown as jest.Mock).mockResolvedValue({
      ok: true,
      json: async () => ({ status: 'success', data: payload }),
    });

    const geo = await fetchGeographicData();

    expect(geo).toEqual(payload);
    expect(global.fetch).toHaveBeenCalledTimes(1);
    expect(
      String((global.fetch as unknown as jest.Mock).mock.calls[0][0]),
    ).toContain('/admin/analytics/geographic');
  });
});

/**
 * Cycle 2 — operations reads (readiness, issues, issue detail) return dummy
 * data with zero network while ON, through the exported functions.
 */

describe('operations reads while dummy ON', () => {
  it('returns ok + data for readiness, issues and issue detail with zero network', async () => {
    useDummyStore.getState().toggle();
    expect(useDummyStore.getState().isDummy).toBe(true);

    const readiness = await fetchReadiness();
    const issues = await fetchIssues({});
    const issueDetail = await fetchIssueDetail('dummy-1');

    expect(readiness.ok).toBe(true);
    expect(readiness.data).not.toBeNull();
    expect(issues.ok).toBe(true);
    expect(issues.data).not.toBeNull();
    expect(issueDetail.ok).toBe(true);
    expect(issueDetail.data).not.toBeNull();

    expect(global.fetch).not.toHaveBeenCalled();
  });
});

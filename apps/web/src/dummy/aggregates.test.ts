/**
 * RED cycle: aggregates populated + never-empty + typed to existing interfaces.
 *
 * Given master + transactions → buildAggregates(master, tx):
 * analytics recommendations non-empty, forecast 4 periods, segmentation non-empty;
 * geographic.map_points 40..60 inside bbox + table 5 rows; suppliers/stock/
 * measurement present with rates 0..1; both dashboard shapes (incl. FinanceMetrics
 * keys); operations present with issues non-empty.
 */
import { createSeededRng } from '@/dummy/rng';
import { dummyWindow } from '@/dummy/dates';
import { DUMMY_SEED } from '@/dummy/seed';
import { buildMasterData } from '@/dummy/factory';
import { buildTransactions } from '@/dummy/factory-transactions';
import { buildAggregates, buildGeographic } from '@/dummy/aggregates';
import type { MasterData } from '@/dummy/factory';
import type { Transactions } from '@/dummy/factory-transactions';

const TODAY = new Date('2026-02-14T10:00:00+07:00');

describe('buildAggregates', () => {
  const window = dummyWindow(TODAY);
  const rng = createSeededRng(DUMMY_SEED);
  const master = buildMasterData(rng, window);
  const tx = buildTransactions(master, window);
  const agg = buildAggregates(master, tx);

  describe('analytics (AIData)', () => {
    it('has at least one recommendation', () => {
      expect(agg.analytics).toBeDefined();
      expect(agg.analytics.recommendations.length).toBeGreaterThan(0);
    });

    it('forecast has exactly 4 predictions', () => {
      expect(agg.analytics.forecast.predictions).toHaveLength(4);
    });

    it('segmentation has at least one segment', () => {
      expect(agg.analytics.segmentation).toBeDefined();
      expect(agg.analytics.segmentation.segments.length).toBeGreaterThan(0);
    });

    it('forecast method is dummy-heuristic', () => {
      expect(agg.analytics.forecast.method).toBe('dummy-heuristic');
    });
  });

  describe('geographic (GeographicData)', () => {
    it('has 40..60 map_points', () => {
      expect(agg.geographic.map_points.length).toBeGreaterThanOrEqual(40);
      expect(agg.geographic.map_points.length).toBeLessThanOrEqual(60);
    });

    it('all map_points inside JABODETABEK bbox', () => {
      for (const point of agg.geographic.map_points) {
        expect(point.latitude).toBeGreaterThanOrEqual(-6.9);
        expect(point.latitude).toBeLessThanOrEqual(-5.9);
        expect(point.longitude).toBeGreaterThanOrEqual(105.9);
        expect(point.longitude).toBeLessThanOrEqual(107.3);
      }
    });

    it('table has exactly 5 rows (one per territory)', () => {
      expect(agg.geographic.table).toHaveLength(5);
    });
  });

  describe('suppliers/stock/measurement', () => {
    it('suppliers list is non-empty', () => {
      expect(agg.suppliers.suppliers.length).toBeGreaterThan(0);
    });

    it('stock items list is non-empty', () => {
      expect(agg.stock.items.length).toBeGreaterThan(0);
    });

    it('recommendation funnel is non-empty with rates 0..1', () => {
      expect(agg.measurement.recommendations.funnel.length).toBeGreaterThan(0);
      const rates = agg.measurement.recommendations.rates;
      for (const key of [
        'clicked_rate',
        'cart_rate',
        'purchased_rate',
        'overall_conversion_rate',
      ] as const) {
        expect(rates[key]).toBeGreaterThanOrEqual(0);
        expect(rates[key]).toBeLessThanOrEqual(1);
      }
    });

    it('forecast measurement is present', () => {
      expect(agg.measurement.forecasts).toBeDefined();
      expect(agg.measurement.forecasts.status).toBeDefined();
    });
  });

  describe('dashboard', () => {
    it('admin: orders_total equals transaction order count (derived)', () => {
      expect(agg.dashboardAdmin.metrics.orders_total).toBe(tx.orders.length);
    });

    it('finance: has ALL FinanceMetrics keys', () => {
      const fm = agg.dashboardFinance;
      expect(fm).toBeDefined();
      expect(fm).toHaveProperty('issued_invoices');
      expect(fm).toHaveProperty('outstanding_balance');
      expect(fm).toHaveProperty('overdue_rate');
      expect(fm).toHaveProperty('collection_time');
      expect(fm).toHaveProperty('payment_status_breakdown');
      expect(fm).toHaveProperty('reminders');
    });
  });

  describe('operations', () => {
    it('readiness is non-null', () => {
      expect(agg.operations.readiness).not.toBeNull();
      expect(agg.operations.readiness).toBeDefined();
    });

    it('issues list is non-empty', () => {
      expect(agg.operations.issues.length).toBeGreaterThan(0);
    });
  });

  describe('analyticsInsight (fixed split comparison window)', () => {
    const insight = agg.analyticsInsight;

    it('current period is the last 30 days ending on the dummy window end', () => {
      expect(insight.comparison.period).toEqual({
        start_date: '2026-01-16',
        end_date: '2026-02-14',
      });
    });

    it('previous period is the equal-length block immediately before', () => {
      expect(insight.comparison.previous_period).toEqual({
        start_date: '2025-12-17',
        end_date: '2026-01-15',
      });
    });

    it('sales_trends is zero-filled to exactly 30 buckets', () => {
      expect(insight.sales_trends).toHaveLength(30);
      expect(insight.sales_trends[0].period).toBe('2026-01-16');
      expect(insight.sales_trends[29].period).toBe('2026-02-14');
    });

    it('metrics_delta covers the four deltable metrics and no point-in-time counts', () => {
      expect(Object.keys(insight.metrics_delta).sort()).toEqual([
        'orders_total',
        'outstanding_total',
        'payments_total',
        'sales_total',
      ]);
    });

    it('exposes an outlet performance total covering at least the ranked rows', () => {
      expect(typeof insight.outlet_performance_total).toBe('number');
      expect(insight.outlet_performance_total).toBeGreaterThanOrEqual(
        insight.outlet_performance.length,
      );
    });

    it('needs_attention has valid reasons and respects the cap', () => {
      expect(insight.needs_attention.length).toBeLessThanOrEqual(5);
      for (const item of insight.needs_attention) {
        expect(['sales_decline', 'outstanding_risk']).toContain(item.reason);
      }
    });

    it('deterministically surfaces at least one needs_attention row for the pinned today', () => {
      expect(insight.needs_attention.length).toBeGreaterThanOrEqual(1);
    });
  });
});

// Deterministic v2 fixture: IDs and expected values are fixed literals (no RNG).
function geographicFixture(): { master: MasterData; tx: Transactions; window: { start: string; end: string } } {
  const outlet = (id: number, name: string, lat: number | null, lon: number | null, city = 'Jakarta', legacy_only = false) => ({
    id: String(id), name, territoryId: city.toLowerCase(), city, lat, lon, legacy_only,
  });
  const master = {
    territories: [],
    outlets: [
      outlet(101, 'Outlet H', -6.2, 106.8), outlet(102, 'Outlet E', null, 106.8),
      outlet(103, 'Outlet F', 0, 0), outlet(104, 'Outlet C', -6.3, 106.9), outlet(105, 'Outlet V1', -6.4, 107.0, 'Jakarta', true),
    ], products: [
      { sku: 'P-1', name: 'Produk A', category: 'A', price: 100 },
      { sku: 'P-2', name: 'Produk B', category: 'B', price: 200 },
      { sku: 'P-3', name: 'Produk C', category: 'C', price: 300 },
    ], suppliers: [], driverProfiles: [],
  } as unknown as MasterData;
  let orderId = 1001;
  const orders: Transactions['orders'] = [];
  const add = (outlet_id: number, status: string, date: string, amount: number, product_id = 1) => {
    const id = orderId++;
    const item = { id: id * 10, product_id, sku: `P-${product_id}`, product_name: `Produk ${String.fromCharCode(64 + product_id)}`, quantity: 1, unit_price: amount.toFixed(2), subtotal: amount.toFixed(2) };
    orders.push({ id, order_id: `ORD-${id}`, outlet_id, outlet_code: String(outlet_id), status, total_amount: amount.toFixed(2), paid_amount: '0.00', created_at: `${date}T10:00:00+07:00`, items: [item], status_history: [{ status, created_at: `${date}T10:00:00+07:00` }] });
  };
  add(101, 'New', '2026-09-20', 100); add(101, 'Delivered', '2026-09-20', 300, 2);
  add(101, 'Confirmed', '2026-09-22', 200); add(101, 'Partially Paid', '2026-09-22', 400, 3);
  add(101, 'New', '2026-09-25', 110); add(101, 'New', '2026-09-25', 120);
  add(101, 'Confirmed', '2026-09-25', 210, 2); add(101, 'Delivered', '2026-09-25', 310, 2);
  add(101, 'New', '2026-09-10', 130, 3); add(101, 'Confirmed', '2026-09-10', 220);
  add(102, 'New', '2026-09-21', 150); add(102, 'New', '2026-09-21', 160, 2); add(102, 'New', '2026-09-10', 170, 3); add(102, 'Confirmed', '2026-09-24', 180); add(102, 'Confirmed', '2026-09-10', 190, 2);
  add(103, 'New', '2026-09-23', 150); add(103, 'Confirmed', '2026-09-23', 160, 2);
  for (let i = 0; i < 10; i++) add(105, 'New', `2026-09-${String(16 + i).padStart(2, '0')}`, 100);
  return { master, tx: { orders, payments: [], invoices: [], deliveries: [], visits: [] }, window: { start: '2026-08-27', end: '2026-09-25' } };
}

describe('buildGeographic deterministic v2 fixture', () => {
  it('emits exact v2 literals and keeps the legacy row v1-shaped', () => {
    const fixture = geographicFixture();
    const result = buildGeographic(fixture.master, fixture.tx, fixture.window);
    const h = result.map_points.find((point) => point.outlet_id === 101) as any;
    expect(h).toMatchObject({ outlet_id: 101, outlet_name: 'Outlet H', latitude: -6.2, longitude: 106.8, plottable: true, orders: 10, sales: '2100.00', orders_by_status: { New: 4, Confirmed: 3, Delivered: 2, 'Partially Paid': 1 }, sales_by_status: { New: '460.00', Confirmed: '630.00', Delivered: '610.00', 'Partially Paid': '400.00' }, product_summary_truncated: false, latest_request: { order_id: 'ORD-1008', status: 'Delivered', created_at: '2026-09-25T10:00:00+07:00' } });
    expect(h.daily_by_status).toEqual([
      { date: '2026-09-10', counts: { New: 1, Confirmed: 1, Delivered: 0, 'Partially Paid': 0 }, sales: { New: '130.00', Confirmed: '220.00', Delivered: '0.00', 'Partially Paid': '0.00' } },
      { date: '2026-09-20', counts: { New: 1, Confirmed: 0, Delivered: 1, 'Partially Paid': 0 }, sales: { New: '100.00', Confirmed: '0.00', Delivered: '300.00', 'Partially Paid': '0.00' } },
      { date: '2026-09-22', counts: { New: 0, Confirmed: 1, Delivered: 0, 'Partially Paid': 1 }, sales: { New: '0.00', Confirmed: '200.00', Delivered: '0.00', 'Partially Paid': '400.00' } },
      { date: '2026-09-25', counts: { New: 2, Confirmed: 1, Delivered: 1, 'Partially Paid': 0 }, sales: { New: '230.00', Confirmed: '210.00', Delivered: '310.00', 'Partially Paid': '0.00' } },
    ]);
    expect(h.product_summary).toEqual([
      { product_id: 1, product_name: 'Produk A', quantity: 5, subtotal: '750.00' },
      { product_id: 2, product_name: 'Produk B', quantity: 3, subtotal: '820.00' },
      { product_id: 3, product_name: 'Produk C', quantity: 2, subtotal: '530.00' },
    ]);
    const e = result.map_points.find((point) => point.outlet_id === 102) as any;
    const f = result.map_points.find((point) => point.outlet_id === 103) as any;
    const c = result.map_points.find((point) => point.outlet_id === 104) as any;
    const v1 = result.map_points.find((point) => point.outlet_id === 105) as any;
    expect(e.plottable).toBe(false); expect(f.plottable).toBe(false); expect(c.orders).toBe(0);
    expect(v1).toMatchObject({ outlet_id: 105, orders: 10, sales: '1000.00', plottable: true });
    expect(v1).not.toHaveProperty('orders_by_status'); expect(v1).not.toHaveProperty('sales_by_status');
    expect(v1).not.toHaveProperty('daily_by_status'); expect(v1).not.toHaveProperty('product_summary'); expect(v1).not.toHaveProperty('latest_request');
  });
});


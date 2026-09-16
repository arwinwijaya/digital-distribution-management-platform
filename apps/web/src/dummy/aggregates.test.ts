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
import { buildAggregates } from '@/dummy/aggregates';

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
});

/**
 * RED cycle 1: buildFullDummy composition.
 *
 * Exercises the composition function that combines master data, transactions,
 * and aggregates into a single DummyEntities object.
 *
 * Given fixed today (2026-02-14) → buildFullDummy returns DummyEntities
 * with non-empty master keys, ~900 orders, 1:1 payments/invoices/deliveries,
 * non-empty analytics recommendations, geographic map_points 40..60.
 * Two calls with same date must be deep-equal (deterministic).
 */
import { buildFullDummy } from '@/dummy';

const TODAY = new Date('2026-02-14T10:00:00+07:00');

describe('buildFullDummy composition', () => {
  it('is a function', () => {
    expect(typeof buildFullDummy).toBe('function');
  });

  describe('with fixed today', () => {
    const result = buildFullDummy(TODAY);

    it('returns an object with master-data keys', () => {
      expect(Array.isArray(result.outlets)).toBe(true);
      expect(result.outlets.length).toBeGreaterThan(0);
      expect(Array.isArray(result.products)).toBe(true);
      expect(result.products.length).toBeGreaterThan(0);
      // `suppliers` at top level is the aggregate SupplierPerformanceData
      // (its `suppliers` record list shadows the plain master supplier array).
      const supplierPerf = result.suppliers as unknown as {
        suppliers: unknown[];
      };
      expect(supplierPerf.suppliers.length).toBeGreaterThan(0);
      expect(Array.isArray(result.territories)).toBe(true);
      expect(result.territories.length).toBeGreaterThan(0);
    });

    it('has 800..1000 orders', () => {
      expect(result.orders.length).toBeGreaterThanOrEqual(800);
      expect(result.orders.length).toBeLessThanOrEqual(1000);
    });

    it('has 1:1 payments/invoices/deliveries matching order count', () => {
      expect(result.payments.length).toBe(result.orders.length);
      expect(result.invoices.length).toBe(result.orders.length);
      expect(result.deliveries.length).toBe(result.orders.length);
    });

    it('has non-empty analytics.recommendations', () => {
      expect(result.analytics).toBeDefined();
      expect(result.analytics.recommendations.length).toBeGreaterThan(0);
    });

    it('has geographic.map_points between 40 and 60', () => {
      expect(result.geographic).toBeDefined();
      expect(result.geographic.map_points.length).toBeGreaterThanOrEqual(40);
      expect(result.geographic.map_points.length).toBeLessThanOrEqual(60);
    });

    it('has non-empty geographic.table (5 territories)', () => {
      expect(result.geographic.table.length).toBe(5);
    });
  });

  it('defaults to new Date() when called without arguments', () => {
    const result = buildFullDummy();
    expect(result).toBeDefined();
    expect(result.outlets.length).toBeGreaterThan(0);
    expect(result.orders.length).toBeGreaterThanOrEqual(800);
  });

  it('is deterministic: two calls with same date yield deep-equal results', () => {
    const first = buildFullDummy(TODAY);
    const second = buildFullDummy(TODAY);
    expect(first).toEqual(second);
  });
});

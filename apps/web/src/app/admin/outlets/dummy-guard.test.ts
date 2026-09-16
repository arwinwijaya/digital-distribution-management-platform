/**
 * Integration tests — dummy-mode read guards across admin/* and sales/* API modules.
 *
 * Exercises the exported API functions through the REAL store + guards
 * (no module mocks). Only `global.fetch` is faked; its call count is the
 * zero-network oracle.
 *
 * Step 1 (RED): admin outlet reads (fetchAdminOutlets / fetchOutletOrders /
 * fetchOutletSummary) return correctly-shaped dummy data with zero network
 * while dummy mode is ON, and fall back to the real fetch when OFF.
 */
import { useDummyStore, setDummyGenerator } from '@/dummy/store';
import type { DummyEntities } from '@/dummy/store';
import { buildFullDummy } from '@/dummy';
import {
  fetchAdminOutlets,
  fetchOutletOrders,
  fetchOutletSummary,
  type AdminOutlet,
} from '@/app/admin/outlets/api';
import { fetchAdminUsers } from '@/app/admin/users/api';
import { fetchProducts, fetchPriceHistory } from '@/app/admin/products/api';
import { fetchPromotions } from '@/app/admin/promotions/api';
import { fetchAdminSalesPerformance } from '@/app/admin/sales-performance/api';
import { fetchSalesOutlets, fetchCatalogProducts } from '@/app/sales/orders/api';
import { fetchMyPerformance } from '@/app/sales/performance/api';

const FIXED_TODAY = new Date('2026-02-14T10:00:00+07:00');

/**
 * Deterministic T5/T6 generator, pinned to a fixed "today" and generated once.
 * Registered through the store seam exactly like production `installDummy`.
 */
const DUMMY = buildFullDummy(FIXED_TODAY) as unknown as DummyEntities;

let originalFetch: typeof fetch | undefined;
let fetchMock: jest.Mock;

beforeEach(() => {
  localStorage.clear();
  localStorage.setItem('ddp_token', 't-token');
  useDummyStore.getState().reset();
  localStorage.setItem('ddp_token', 't-token');
  setDummyGenerator(() => DUMMY);

  fetchMock = jest.fn(async () => ({
    ok: true,
    status: 200,
    json: async () => ({ status: 'success', data: [] }),
  }) as unknown as Response);

  originalFetch = (globalThis as unknown as { fetch?: typeof fetch }).fetch;
  (globalThis as unknown as { fetch: unknown }).fetch = fetchMock;
});

afterEach(() => {
  if (originalFetch) (globalThis as unknown as { fetch: unknown }).fetch = originalFetch;
  else delete (globalThis as unknown as { fetch?: unknown }).fetch;
  useDummyStore.getState().reset();
});

function turnDummyOn(): void {
  useDummyStore.getState().toggle();
  expect(useDummyStore.getState().isDummy).toBe(true);
}

describe('admin outlet reads — dummy ON', () => {
  it('returns 15 paginated dummy outlets, outlet orders and a summary with zero network', async () => {
    turnDummyOn();

    const result = await fetchAdminOutlets('t-token');
    expect(Array.isArray(result.outlets)).toBe(true);
    expect(result.outlets.length).toBe(15);
    expect(result.hasMore).toBe(true);
    expect(result.limit).toBe(15);

    // Resolve a real dummy outlet id from the list for the follow-up reads.
    const first = result.outlets[0];
    expect(first).toBeDefined();
    expect(typeof first.id).toBe('number');

    const orders = await fetchOutletOrders('t-token', first.id);
    expect(Array.isArray(orders.orders)).toBe(true);
    expect(typeof orders.hasMore).toBe('boolean');

    const summary = await fetchOutletSummary('t-token', first.id);
    expect(summary).not.toBeNull();
    const realIds = result.outlets.map((o: AdminOutlet) => o.id);
    expect(realIds).toContain(summary.outlet_id);
    expect(typeof summary.outlet_name).toBe('string');
    expect(summary.outlet_name.length).toBeGreaterThan(0);
    expect(typeof summary.total_orders).toBe('number');
    expect(typeof summary.total_amount).toBe('string');

    expect(fetchMock).not.toHaveBeenCalled();
  });

  it('resolves an unknown/sentinel outlet id to a real dummy outlet (orders + summary non-null)', async () => {
    turnDummyOn();

    const list = await fetchAdminOutlets('t-token', { limit: 100 });
    const realIds = list.outlets.map((o: AdminOutlet) => o.id);
    const sentinelId = realIds.includes(-1) ? realIds[0] : -1;

    const orders = await fetchOutletOrders('t-token', sentinelId);
    const summary = await fetchOutletSummary('t-token', sentinelId);

    expect(Array.isArray(orders.orders)).toBe(true);
    expect(summary).not.toBeNull();
    expect(typeof summary.outlet_id).toBe('number');
    // Guard returns an existing dummy outlet when the id is unknown.
    expect(realIds).toContain(summary.outlet_id);

    expect(fetchMock).not.toHaveBeenCalled();
  });
});

describe('admin outlet reads — filter parity on dummy data', () => {
  it('applies search/category/territory filters to the dummy subset', async () => {
    turnDummyOn();

    const all = await fetchAdminOutlets('t-token', { limit: 200 });
    expect(all.outlets.length).toBeGreaterThan(15);

    const target = all.outlets[0];
    const byName = await fetchAdminOutlets('t-token', { search: target.name, limit: 200 });
    expect(byName.outlets.length).toBeLessThan(all.outlets.length);
    expect(byName.outlets.every((o: AdminOutlet) => o.name.includes(target.name))).toBe(true);

    const category = target.category as string;
    expect(typeof category).toBe('string');
    const byCategory = await fetchAdminOutlets('t-token', { category, limit: 200 });
    expect(byCategory.outlets.length).toBeLessThan(all.outlets.length);
    expect(byCategory.outlets.every((o: AdminOutlet) => o.category === category)).toBe(true);

    const territoryId = String(target.territory_id);
    const byTerritory = await fetchAdminOutlets('t-token', { territory_id: territoryId, limit: 200 });
    expect(byTerritory.outlets.length).toBeLessThan(all.outlets.length);
    expect(byTerritory.outlets.every((o: AdminOutlet) => String(o.territory_id) === territoryId)).toBe(true);

    expect(fetchMock).not.toHaveBeenCalled();
  });
});

describe('admin outlet reads — dummy OFF (no regression)', () => {
  it('calls the real endpoint with the original URL when dummy is OFF', async () => {
    const result = await fetchAdminOutlets('t-token');
    expect(result).toEqual({ outlets: [], hasMore: false, limit: 15, cursor: 0 });
    expect(fetchMock).toHaveBeenCalledTimes(1);
    expect(String(fetchMock.mock.calls[0][0])).toContain('/admin/outlets?');
  });

  it('surfaces the backend error message when the real fetch fails', async () => {
    fetchMock.mockResolvedValueOnce({
      ok: false,
      status: 500,
      json: async () => ({ message: 'backend down' }),
    } as unknown as Response);

    await expect(fetchAdminOutlets('t-token')).rejects.toThrow('backend down');
    expect(fetchMock).toHaveBeenCalledTimes(1);
  });
});

// ───────────────────────────────────────────────────────────────────────────
// Step 5: Zero-fetch sweep — every guarded admin/sales read module
//         returns correctly-shaped dummy data with zero network.
// ───────────────────────────────────────────────────────────────────────────

describe('zero-fetch sweep across every guarded admin/sales read module', () => {
  it('resolves all 11 reads to their declared shapes with zero network calls', async () => {
    turnDummyOn();

    // 1–3: admin/outlets
    const outlets = await fetchAdminOutlets('t-token', { limit: 15 });
    expect(outlets.outlets.length).toBe(15);

    const firstOutletId = outlets.outlets[0].id;

    await fetchOutletOrders('t-token', firstOutletId);
    await fetchOutletSummary('t-token', firstOutletId);

    // 4: admin/users
    const usersResult = await fetchAdminUsers('t-token', {});
    expect(Array.isArray(usersResult.users)).toBe(true);
    expect(usersResult.users.length).toBeGreaterThanOrEqual(1);
    expect(typeof usersResult.hasMore).toBe('boolean');
    expect(typeof usersResult.limit).toBe('number');

    // 5: admin/products + price history
    const products = await fetchProducts('t-token');
    expect(Array.isArray(products)).toBe(true);
    expect(products.length).toBeGreaterThanOrEqual(1);
    expect(typeof products[0].name).toBe('string');

    const ph = await fetchPriceHistory('t-token', products[0].id);
    expect(Array.isArray(ph.data)).toBe(true);
    expect(typeof ph.hasMore).toBe('boolean');

    // 6: admin/promotions
    const promos = await fetchPromotions('t-token', { limit: 15 });
    expect(Array.isArray(promos.promotions)).toBe(true);
    expect(typeof promos.hasMore).toBe('boolean');

    // 7: admin/sales-performance
    const perf = await fetchAdminSalesPerformance('t-token', {});
    expect(Array.isArray(perf.rows)).toBe(true);
    expect(typeof perf.hasMore).toBe('boolean');
    expect(perf.rows.length).toBeGreaterThanOrEqual(1);
    expect(typeof perf.rows[0].name).toBe('string');
    expect(typeof perf.rows[0].period).toBe('string');

    // 8: sales/outlets (read)
    const salesOutlets = await fetchSalesOutlets('t-token');
    expect(Array.isArray(salesOutlets)).toBe(true);
    expect(salesOutlets.length).toBeGreaterThanOrEqual(1);
    expect(typeof salesOutlets[0].name).toBe('string');

    // 9: sales/catalog products (read)
    const catalogProducts = await fetchCatalogProducts('t-token');
    expect(Array.isArray(catalogProducts)).toBe(true);
    expect(catalogProducts.length).toBeGreaterThanOrEqual(1);
    expect(typeof catalogProducts[0].name).toBe('string');
    expect(typeof catalogProducts[0].price).toBe('string');

    // 10: sales/performance
    const myPerf = await fetchMyPerformance('t-token');
    expect(typeof myPerf.user_id).toBe('number');
    expect(typeof myPerf.name).toBe('string');
    expect(typeof myPerf.period).toBe('string');
    expect(typeof myPerf.target).toBe('string');
    expect(typeof myPerf.achievement).toBe('string');
    expect(Number(myPerf.achievement)).toBeGreaterThan(0);
    expect(Number(myPerf.order_count)).toBeGreaterThan(0);
    expect(typeof myPerf.percentage).toBe('string');

    // THE ZERO-NETWORK ASSERTION: global fetch NEVER called across all reads.
    expect(fetchMock).not.toHaveBeenCalled();
  });
});

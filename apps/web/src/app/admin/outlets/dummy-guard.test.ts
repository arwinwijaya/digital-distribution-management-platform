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
  createOutlet,
  updateOutlet,
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
// Step 6: Dummy write round-trip — create / edit / deactivate with zero fetch
//         and deep-merge semantics that preserve synthesized row fields.
// ───────────────────────────────────────────────────────────────────────────

describe('admin outlet writes — dummy round-trip', () => {
  const baseInput = {
    name: 'Warung Uji Baru',
    phone: '081234000111',
    category: 'kafe',
    address: 'Jl. Uji No. 1',
    city: 'Bekasi',
    district: 'Bekasi Selatan',
    territory_id: 2,
    is_active: true,
  };

  it('creates, lists, edits, and deactivates an outlet with zero network', async () => {
    turnDummyOn();

    const created = await createOutlet('t-token', baseInput);
    expect(created.id).toBeLessThan(0);
    expect(created.name).toBe('Warung Uji Baru');
    expect(created.category).toBe('kafe');
    expect(created.territory_id).toBe(2);
    expect(created.is_active).toBe(true);

    // The created row must land on page 1 (created_at newer than every seed row).
    const page1 = await fetchAdminOutlets('t-token', { limit: 15 });
    expect(page1.outlets.map((o: AdminOutlet) => o.id)).toContain(created.id);

    // Edit by name only — the row must keep its category / territory.
    const edited = await updateOutlet('t-token', created.id, { name: 'Warung Uji Diubah' });
    expect(edited.id).toBe(created.id);
    expect(edited.name).toBe('Warung Uji Diubah');

    const afterEdit = await fetchAdminOutlets('t-token', { limit: 500 });
    const editedRow = afterEdit.outlets.find((o: AdminOutlet) => o.id === created.id);
    expect(editedRow).toBeDefined();
    expect(editedRow?.name).toBe('Warung Uji Diubah');
    expect(editedRow?.category).toBe('kafe');
    expect(editedRow?.territory_id).toBe(2);

    // Deactivate (soft) — row stays listed, marked inactive, fields preserved.
    const deactivated = await updateOutlet('t-token', created.id, { is_active: false });
    expect(deactivated.is_active).toBe(false);

    const afterDeactivate = await fetchAdminOutlets('t-token', { limit: 500 });
    const deactivatedRow = afterDeactivate.outlets.find((o: AdminOutlet) => o.id === created.id);
    expect(deactivatedRow?.is_active).toBe(false);
    expect(deactivatedRow?.category).toBe('kafe');
    expect(deactivatedRow?.territory_id).toBe(2);

    expect(fetchMock).not.toHaveBeenCalled();
  });

  it('editing a BASE outlet deep-merges and preserves its synthesized fields', async () => {
    turnDummyOn();

    const before = await fetchAdminOutlets('t-token', { limit: 500 });
    const target = before.outlets[0];
    expect(target).toBeDefined();

    const updated = await updateOutlet('t-token', target.id, { name: 'Nama Baru Base' });
    expect(updated.id).toBe(target.id);
    expect(updated.name).toBe('Nama Baru Base');

    const after = await fetchAdminOutlets('t-token', { limit: 500 });
    const row = after.outlets.find((o: AdminOutlet) => o.id === target.id);
    expect(row?.name).toBe('Nama Baru Base');
    // The partial edit must NOT blank the synthesized fields (W1 regression).
    expect(row?.category).toBe(target.category);
    expect(row?.territory_id).toBe(target.territory_id);
    expect(row?.score).toBe(target.score);
    expect(row?.is_active).toBe(target.is_active);

    expect(fetchMock).not.toHaveBeenCalled();
  });

  it('rejects a duplicate phone on dummy create with zero network', async () => {
    turnDummyOn();

    await createOutlet('t-token', baseInput);
    await expect(
      createOutlet('t-token', { ...baseInput, name: 'Warung Duplikat' }),
    ).rejects.toThrow();

    expect(fetchMock).not.toHaveBeenCalled();
  });

  it('resolves orders + summary for a newly created outlet', async () => {
    turnDummyOn();

    const created = await createOutlet('t-token', {
      ...baseInput,
      name: 'Outlet Ringkasan',
      phone: '081234000333',
      category: 'grosir',
    });

    const summary = await fetchOutletSummary('t-token', created.id);
    expect(summary.outlet_id).toBe(created.id);
    expect(summary.outlet_name).toBe('Outlet Ringkasan');
    expect(summary.total_orders).toBe(0);

    const orders = await fetchOutletOrders('t-token', created.id);
    expect(orders.orders).toEqual([]);

    expect(fetchMock).not.toHaveBeenCalled();
  });

  it('POSTs to the real endpoint when dummy is OFF', async () => {
    fetchMock.mockResolvedValueOnce({
      ok: true,
      status: 201,
      json: async () => ({ status: 'success', data: { id: 7, name: 'Outlet Nyata', category: 'warung' } }),
    } as unknown as Response);

    const created = await createOutlet('t-token', baseInput);
    expect(created.id).toBe(7);
    expect(created.name).toBe('Outlet Nyata');
    expect(fetchMock).toHaveBeenCalledTimes(1);
    expect(String(fetchMock.mock.calls[0][0])).toContain('/admin/outlets');
    expect((fetchMock.mock.calls[0][1] as { method?: string }).method).toBe('POST');
  });

  it('surfaces the backend error message when the real create fails', async () => {
    fetchMock.mockResolvedValueOnce({
      ok: false,
      status: 422,
      json: async () => ({ message: 'Nomor telepon sudah terdaftar.' }),
    } as unknown as Response);

    await expect(createOutlet('t-token', baseInput)).rejects.toThrow('Nomor telepon sudah terdaftar.');
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

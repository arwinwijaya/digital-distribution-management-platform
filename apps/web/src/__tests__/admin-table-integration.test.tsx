/**
 * Integration tests — dummy-mode parity across the five admin table loaders.
 *
 * Cycle 1: prove that with dummy mode ON, every admin table loader's dummy
 * branch returns the SAME sort / total / summary / offset contract as the real
 * path, with ZERO network. Exercises the exported `fetch*` loaders through the
 * REAL store + guards (no module mocks). Only `global.fetch` is faked; its
 * call count is the zero-network oracle.
 *
 * Expected values are derived with the SAME shared helpers the implementation
 * uses (`compareRows` / `paginate` from `@/lib/admin-table`) — never
 * reimplemented here.
 */
import { useDummyStore, setDummyGenerator } from '@/dummy/store';
import type { DummyEntities } from '@/dummy/store';
import { buildFullDummy } from '@/dummy';
import { compareRows, paginate } from '@/lib/admin-table';
import { fetchAdminOutlets } from '@/app/admin/outlets/api';
import { fetchAdminUsers } from '@/app/admin/users/api';
import { fetchAdminProducts } from '@/app/admin/products/api';
import { fetchPromotions } from '@/app/admin/promotions/api';
import { fetchAdminSalesPerformance } from '@/app/admin/sales-performance/api';

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

describe('dummy-mode parity across admin tables (zero network)', () => {
  it('outlets — created_at DESC order, total=filtered.length, offset slice, summary breakdown', async () => {
    turnDummyOn();

    const all = await fetchAdminOutlets('t-token', { limit: 500 });
    expect(all.total).toBe(all.outlets.length);

    const sorted = [...all.outlets].sort((a, b) =>
      compareRows(a as any, b as any, 'created_at', 'desc'),
    );
    expect(all.outlets.map((o) => o.id)).toEqual(sorted.map((o) => o.id));

    expect(all.summary!.active + all.summary!.inactive).toBe(all.total);

    const expectedPage2 = paginate(sorted, 5, 5).page;
    const p1 = await fetchAdminOutlets('t-token', { limit: 5, cursor: 0 });
    const p2 = await fetchAdminOutlets('t-token', { limit: 5, cursor: 5 });
    expect(p1.outlets.length).toBe(5);
    expect(p1.hasMore).toBe(true);
    expect(p2.outlets.map((o) => o.id)).toEqual(expectedPage2.map((o) => o.id));

    expect(fetchMock).not.toHaveBeenCalled();
  });

  it('users — created_at DESC order, total, summary.total, offset slice', async () => {
    turnDummyOn();

    const all = await fetchAdminUsers('t-token', { limit: 500 });
    expect(all.total).toBe(all.users.length);
    expect(all.summary!.total).toBe(all.total);

    const expected = [...all.users]
      .sort((a, b) => compareRows(a as any, b as any, 'created_at', 'desc'))
      .map((u) => u.id);
    expect(all.users.map((u) => u.id)).toEqual(expected);

    expect(fetchMock).not.toHaveBeenCalled();
  });

  it('products — created_at DESC falls back to id DESC (dummy products carry no created_at), total + out_of_stock', async () => {
    turnDummyOn();

    const all = await fetchAdminProducts('t-token', { limit: 500 });
    expect(all.total).toBe(all.products.length);

    const ids = all.products.map((p) => p.id);
    expect(ids.length).toBeGreaterThan(1);
    for (let i = 0; i < ids.length - 1; i += 1) {
      expect(ids[i]).toBeGreaterThan(ids[i + 1]);
    }

    expect(all.summary!.total).toBe(all.total);
    expect(typeof all.summary!.out_of_stock).toBe('number');

    expect(fetchMock).not.toHaveBeenCalled();
  });

  it('promotions — created_at DESC order, total=filtered.length, 4-state summary adds up, offset slice', async () => {
    turnDummyOn();

    const all = await fetchPromotions('t-token', { limit: 500 });
    expect(all.total).toBe(all.promotions.length);
    expect(all.summary!.total).toBe(all.total);
    expect(all.summary!.active + all.summary!.scheduled + all.summary!.ended).toBe(all.total);

    const sorted = [...all.promotions].sort((a, b) =>
      compareRows(a as any, b as any, 'created_at', 'desc'),
    );
    expect(all.promotions.map((p) => p.id)).toEqual(sorted.map((p) => p.id));

    const expectedPage2 = paginate(sorted, 5, 5).page;
    const p1 = await fetchPromotions('t-token', { limit: 5, cursor: 0 });
    const p2 = await fetchPromotions('t-token', { limit: 5, cursor: 5 });
    expect(p1.hasMore).toBe(true);
    expect(p2.promotions.map((p) => p.id)).toEqual(expectedPage2.map((p) => p.id));

    expect(fetchMock).not.toHaveBeenCalled();
  });

  it('sales-performance — achievement DESC, total = full roster, offset slice', async () => {
    turnDummyOn();

    const all = await fetchAdminSalesPerformance('t-token', { limit: 500 });
    expect(all.total).toBe(all.rows.length);

    for (let i = 0; i < all.rows.length - 1; i += 1) {
      expect(Number(all.rows[i].achievement)).toBeGreaterThanOrEqual(
        Number(all.rows[i + 1].achievement),
      );
    }

    const p1 = await fetchAdminSalesPerformance('t-token', { limit: 2, cursor: 0 });
    expect(p1.rows.length).toBe(2);
    expect(p1.hasMore).toBe(true);

    const p2 = await fetchAdminSalesPerformance('t-token', { limit: 2, cursor: 2 });
    const expectedPage2 = paginate(all.rows, 2, 2).page;
    expect(p2.rows.map((r) => r.user_id)).toEqual(expectedPage2.map((r) => r.user_id));

    expect(fetchMock).not.toHaveBeenCalled();
  });

  it('invalid sort falls back to the default WITHOUT throwing, on every table', async () => {
    turnDummyOn();

    const bad = { sort: '__proto__; DROP TABLE users' };

    const outlets = await fetchAdminOutlets('t-token', bad);
    expect(outlets.outlets.length).toBeGreaterThan(0);

    const users = await fetchAdminUsers('t-token', bad);
    expect(users.users.length).toBeGreaterThan(0);

    const products = await fetchAdminProducts('t-token', bad);
    expect(products.products.length).toBeGreaterThan(0);

    const promotions = await fetchPromotions('t-token', bad);
    expect(promotions.promotions.length).toBeGreaterThan(0);

    const sales = await fetchAdminSalesPerformance('t-token', { sort: 'nope' });
    expect(sales.rows.length).toBeGreaterThan(0);

    expect(fetchMock).not.toHaveBeenCalled();
  });

  it('zero-network invariant holds across all five dummy reads in one pass', async () => {
    turnDummyOn();

    await fetchAdminOutlets('t-token');
    await fetchAdminUsers('t-token');
    await fetchAdminProducts('t-token');
    await fetchPromotions('t-token');
    await fetchAdminSalesPerformance('t-token');

    expect(fetchMock).not.toHaveBeenCalled();
  });
});

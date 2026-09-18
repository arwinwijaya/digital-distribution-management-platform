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
import { compareRows, paginate, buildCountLabel } from '@/lib/admin-table';
import '@testing-library/jest-dom';
import { render, screen, waitFor } from '@testing-library/react';
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

/**
 * Cycle 2: with dummy mode OFF, the real path must forward the table query
 * params (`sort`/`order`/`cursor`/`limit` + filters) to the backend and use
 * the backend's REAL `meta.total` for the paging label — never an estimate.
 *
 * Only `global.fetch` is faked; the exported loaders, the shared
 * `buildCountLabel` helper and the real `<UsersPage />` are exercised end to
 * end (no module mocks).
 */
describe('real path — sort/cursor params forwarded + meta.total used', () => {
  beforeEach(() => {
    // Dummy mode OFF (explicit): the real branch must run.
    useDummyStore.getState().reset();
    expect(useDummyStore.getState().isDummy).toBe(false);
    localStorage.setItem('ddp_token', 't-token');

    // Per-test override seam; default is an empty success envelope.
    fetchMock = jest.fn(async () => ({
      ok: true,
      status: 200,
      json: async () => ({ status: 'success', data: [], meta: {} }),
    }) as unknown as Response);
    (globalThis as unknown as { fetch: unknown }).fetch = fetchMock;
  });

  const jsonResponse = (body: unknown): Response =>
    ({ ok: true, status: 200, json: async () => body }) as Response;

  const lastUrl = (): string =>
    String(fetchMock.mock.calls[fetchMock.mock.calls.length - 1][0]);

  it('outlets — forwards sort/order/cursor/limit and reads meta.total + summary', async () => {
    fetchMock.mockResolvedValueOnce(
      jsonResponse({
        status: 'success',
        data: {
          data: [{ id: 1, name: 'A', created_at: '2026-01-01T00:00:00Z' }],
          has_more: true,
          limit: 15,
          cursor: 30,
        },
        meta: {
          has_more: true,
          limit: 15,
          cursor: 30,
          total: 42,
          summary: { active: 1, inactive: 0 },
        },
      }),
    );

    const r = await fetchAdminOutlets('t-token', { sort: 'name', order: 'asc', cursor: 30, limit: 15 });

    const url = lastUrl();
    expect(url).toContain('sort=name');
    expect(url).toContain('order=asc');
    expect(url).toContain('cursor=30');
    expect(url).toContain('limit=15');

    expect(r.total).toBe(42);
    expect(r.cursor).toBe(30);
    expect(r.hasMore).toBe(true);
    expect(r.summary).toEqual({ active: 1, inactive: 0 });
    expect(fetchMock).toHaveBeenCalledTimes(1);
  });

  it('users — forwards role/search/sort/order/cursor and reads meta.total + summary', async () => {
    fetchMock.mockResolvedValueOnce(
      jsonResponse({
        status: 'success',
        data: [{ id: 1, name: 'U', email: 'u@x', role: 'admin', created_at: '2026-01-01T00:00:00Z' }],
        meta: { has_more: false, limit: 20, cursor: 0, total: 8, summary: { total: 8 } },
      }),
    );

    const r = await fetchAdminUsers('t-token', {
      role: 'admin',
      search: 'U',
      sort: 'name',
      order: 'asc',
      cursor: 0,
      limit: 20,
    });

    const url = lastUrl();
    expect(url).toContain('sort=name');
    expect(url).toContain('order=asc');
    expect(url).toContain('cursor=0');
    expect(url).toContain('limit=20');
    expect(url).toContain('role=admin');
    expect(url).toContain('search=U');

    expect(r.total).toBe(8);
    expect(r.summary!.total).toBe(8);
    expect(fetchMock).toHaveBeenCalledTimes(1);
  });

  it('products — forwards sort/order/cursor and reads meta.total + summary', async () => {
    fetchMock.mockResolvedValueOnce(
      jsonResponse({
        status: 'success',
        data: [{ id: 1, name: 'P', price: '10.00', sku: 'S', stock_quantity: 1 }],
        meta: { has_more: true, limit: 15, cursor: 15, total: 100, summary: { total: 100, out_of_stock: 3 } },
      }),
    );

    const r = await fetchAdminProducts('t-token', { sort: 'price', order: 'desc', cursor: 15, limit: 15 });

    const url = lastUrl();
    expect(url).toContain('sort=price');
    expect(url).toContain('order=desc');
    expect(url).toContain('cursor=15');
    expect(url).toContain('limit=15');

    expect(r.total).toBe(100);
    expect(r.summary!.out_of_stock).toBe(3);
    expect(fetchMock).toHaveBeenCalledTimes(1);
  });

  it('promotions — forwards sort/order/cursor, reads meta.total + summary, nextCursor = cursor + limit', async () => {
    fetchMock.mockResolvedValueOnce(
      jsonResponse({
        status: 'success',
        data: [
          {
            id: 1,
            name: 'Pr',
            start_date: '2026-01-01T00:00:00+07:00',
            end_date: '2026-02-01T00:00:00+07:00',
            created_at: '2026-01-01T00:00:00+07:00',
          },
        ],
        meta: { has_more: true, limit: 15, cursor: 15, total: 9, summary: { total: 9, active: 4, scheduled: 2, ended: 3 } },
      }),
    );

    const r = await fetchPromotions('t-token', { sort: 'start_date', order: 'desc', cursor: 15, limit: 15 });

    const url = lastUrl();
    expect(url).toContain('sort=start_date');
    expect(url).toContain('order=desc');
    expect(url).toContain('cursor=15');
    expect(url).toContain('limit=15');

    expect(r.total).toBe(9);
    // Offset-derived (cursor + limit) — NEVER the row id.
    expect(r.nextCursor).toBe(30);
    expect(r.summary!.active + r.summary!.scheduled + r.summary!.ended).toBe(r.total);
    expect(fetchMock).toHaveBeenCalledTimes(1);
  });

  it('sales-performance — forwards sort/order/cursor, reads meta.total, nextCursor = cursor + limit when has_more', async () => {
    fetchMock.mockResolvedValueOnce(
      jsonResponse({
        status: 'success',
        data: [
          {
            user_id: 1,
            name: 'Budi',
            period: '2026-02',
            target: '100',
            achievement: '80',
            percentage: '80',
            order_count: 2,
          },
        ],
        meta: { has_more: true, limit: 15, cursor: 0, total: 6 },
      }),
    );

    const r = await fetchAdminSalesPerformance('t-token', { sort: 'name', order: 'asc', cursor: 0, limit: 15 });

    const url = lastUrl();
    expect(url).toContain('sort=name');
    expect(url).toContain('order=asc');
    expect(url).toContain('cursor=0');
    expect(url).toContain('limit=15');

    expect(r.total).toBe(6);
    expect(r.nextCursor).toBe(15);
    expect(fetchMock).toHaveBeenCalledTimes(1);
  });

  it('paging label uses meta.total (not an estimate) when total is present', () => {
    expect(buildCountLabel({ total: 42, cursor: 30, limit: 15, hasMore: true })).toBe(
      'Halaman 3 dari 3 · 42 data',
    );
  });

  it('paging label falls back to the estimate when total is absent', () => {
    expect(buildCountLabel({ cursor: 15, limit: 15, hasMore: true })).toBe('Halaman 2 · ada data lain');
  });

  it('integration: rendering an admin page with meta.total shows the total-based paging label (dummy OFF)', async () => {
    const rows = Array.from({ length: 20 }, (_, i) => ({
      id: i + 1,
      name: `User ${i + 1}`,
      email: `user${i + 1}@x`,
      role: 'admin',
      created_at: '2026-01-01T00:00:00Z',
    }));
    fetchMock.mockResolvedValueOnce(
      jsonResponse({
        status: 'success',
        data: rows,
        meta: { has_more: true, limit: 20, cursor: 0, total: 42, summary: { total: 42 } },
      }),
    );

    const { default: UsersPage } = await import('@/app/admin/users/page');
    render(<UsersPage />);

    await waitFor(() => expect(screen.getByText('User 1')).toBeInTheDocument());
    // End-to-end: meta.total flows loader → page state → TablePagination label.
    expect(screen.getByText(/42 data/)).toBeInTheDocument();
  });
});

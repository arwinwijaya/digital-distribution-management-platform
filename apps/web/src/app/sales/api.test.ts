/**
 * Sales "Jadwal kunjungan" paging — 10 rows/page, newest first, with
 * dummy-mode parity.
 *
 * Exercises the REAL `loadSalesList` loader + REAL store/guards (no module
 * mocks). Only `global.fetch` is faked; its call count is the zero-network
 * oracle for the dummy branch.
 */
import { useDummyStore, setDummyGenerator } from '@/dummy/store';
import type { DummyEntities } from '@/dummy/store';
import { buildFullDummy } from '@/dummy';
import { loadSalesList } from '@/app/sales/api';

const FIXED_TODAY = new Date('2026-02-14T10:00:00+07:00');
const DUMMY = buildFullDummy(FIXED_TODAY) as unknown as DummyEntities;

const PAGE_SIZE = 10;

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
    json: async () => ({ status: 'success', data: [], meta: {} }),
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

/** Dummy parity mirrors the loader: 20 outlet-derived rows. */
const dummyTotal = () =>
  (DUMMY as unknown as { outlets: unknown[] }).outlets.slice(0, 20).length;

describe('sales visits — dummy paging parity (10/page, newest first)', () => {
  it('returns exactly 10 rows on page 1 with a real total and has_more', async () => {
    turnDummyOn();

    const result = await loadSalesList('t-token', 1);

    expect(result.visits).toHaveLength(10);
    expect(result.meta).toEqual({
      page: 1,
      limit: 10,
      total: dummyTotal(),
      has_more: true,
    });
    expect(fetchMock).not.toHaveBeenCalled();
  });

  it('orders newest first (visit_date DESC)', async () => {
    turnDummyOn();

    const result = await loadSalesList('t-token', 1);
    const dates = result.visits.map((visit) => visit.visit_date);

    for (let i = 0; i < dates.length - 1; i += 1) {
      expect(dates[i] >= dates[i + 1]).toBe(true);
    }
  });

  it('slices page 2 at the correct offset without overlapping page 1', async () => {
    turnDummyOn();

    const page1 = await loadSalesList('t-token', 1);
    const page2 = await loadSalesList('t-token', 2);

    expect(page2.meta.page).toBe(2);
    expect(page2.visits).toHaveLength(10);

    const page1Ids = page1.visits.map((v) => v.id);
    const page2Ids = page2.visits.map((v) => v.id);
    expect(page2Ids).not.toEqual(page1Ids);
    expect(page1Ids.some((id) => page2Ids.includes(id))).toBe(false);
  });

  it('reports has_more=false on the last page', async () => {
    turnDummyOn();

    const lastPage = Math.ceil(dummyTotal() / PAGE_SIZE);
    const result = await loadSalesList('t-token', lastPage);

    expect(result.meta.has_more).toBe(false);
    expect(result.visits.length).toBeGreaterThan(0);
    expect(result.visits.length).toBeLessThanOrEqual(PAGE_SIZE);
  });
});

describe('sales visits — real path forwards page + limit=10', () => {
  it('requests the backend with the 10-per-page contract', async () => {
    useDummyStore.getState().reset();
    expect(useDummyStore.getState().isDummy).toBe(false);

    await loadSalesList('t-token', 3);

    const url = String(fetchMock.mock.calls[0][0]);
    expect(url).toContain('/sales/visits?');
    expect(url).toContain('page=3');
    expect(url).toContain('limit=10');
    expect(fetchMock).toHaveBeenCalledTimes(1);
  });

  it('maps the backend meta envelope', async () => {
    useDummyStore.getState().reset();
    expect(useDummyStore.getState().isDummy).toBe(false);

    fetchMock.mockResolvedValueOnce({
      ok: true,
      status: 200,
      json: async () => ({
        status: 'success',
        data: [{ id: 1, target: 'Outlet A', visit_date: '2026-10-12', status: 'planned', notes: null }],
        meta: { page: 2, limit: 10, total: 12, has_more: false },
      }),
    } as unknown as Response);

    const result = await loadSalesList('t-token', 2);

    expect(result.visits).toHaveLength(1);
    expect(result.meta).toEqual({ page: 2, limit: 10, total: 12, has_more: false });
  });
});

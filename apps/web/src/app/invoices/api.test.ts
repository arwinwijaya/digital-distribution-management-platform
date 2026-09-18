/**
 * Invoice history paging — 15 rows/page, newest first, with dummy-mode parity.
 *
 * Exercises the REAL `loadInvoices` loader + REAL store/guards (no module
 * mocks). Only `global.fetch` is faked; its call count is the zero-network
 * oracle for the dummy branch.
 */
import { useDummyStore, setDummyGenerator } from '@/dummy/store';
import type { DummyEntities } from '@/dummy/store';
import { buildFullDummy } from '@/dummy';
import { loadInvoices } from '@/app/invoices/api';

const FIXED_TODAY = new Date('2026-02-14T10:00:00+07:00');
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

describe('invoice history — dummy paging parity (15/page, newest first)', () => {
  it('returns exactly 15 rows on page 1 with a real total and has_more', async () => {
    turnDummyOn();

    const total = (DUMMY as unknown as { invoices: unknown[] }).invoices.length;
    const result = await loadInvoices('t-token', 1);

    expect(result.invoices).toHaveLength(15);
    expect(result.meta).toEqual({ page: 1, limit: 15, total, has_more: true });
    expect(fetchMock).not.toHaveBeenCalled();
  });

  it('orders newest first (id DESC), matching the backend orderByDesc(id)', async () => {
    turnDummyOn();

    const result = await loadInvoices('t-token', 1);
    const ids = result.invoices.map((invoice) => invoice.id);

    for (let i = 0; i < ids.length - 1; i += 1) {
      expect(ids[i]).toBeGreaterThan(ids[i + 1]);
    }

    const allIds = (DUMMY as unknown as { invoices: Array<{ id: number }> }).invoices
      .map((invoice) => invoice.id)
      .sort((a, b) => b - a);
    expect(ids).toEqual(allIds.slice(0, 15));
  });

  it('slices page 2 at the correct offset (rows 16–30)', async () => {
    turnDummyOn();

    const page1 = await loadInvoices('t-token', 1);
    const page2 = await loadInvoices('t-token', 2);

    expect(page2.meta.page).toBe(2);
    expect(page2.invoices).toHaveLength(15);
    expect(page2.invoices.map((i) => i.id)).not.toEqual(page1.invoices.map((i) => i.id));

    const allIds = (DUMMY as unknown as { invoices: Array<{ id: number }> }).invoices
      .map((invoice) => invoice.id)
      .sort((a, b) => b - a);
    expect(page2.invoices.map((i) => i.id)).toEqual(allIds.slice(15, 30));
  });

  it('reports has_more=false on the last page', async () => {
    turnDummyOn();

    const total = (DUMMY as unknown as { invoices: unknown[] }).invoices.length;
    const lastPage = Math.ceil(total / 15);
    const result = await loadInvoices('t-token', lastPage);

    expect(result.meta.has_more).toBe(false);
    expect(result.invoices.length).toBeGreaterThan(0);
    expect(result.invoices.length).toBeLessThanOrEqual(15);
  });
});

describe('invoice history — real path forwards page + limit=15', () => {
  it('requests the backend with the 15-per-page contract', async () => {
    useDummyStore.getState().reset();
    expect(useDummyStore.getState().isDummy).toBe(false);

    await loadInvoices('t-token', 3);

    const url = String(fetchMock.mock.calls[0][0]);
    expect(url).toContain('/invoices?');
    expect(url).toContain('page=3');
    expect(url).toContain('limit=15');
    expect(fetchMock).toHaveBeenCalledTimes(1);
  });
});

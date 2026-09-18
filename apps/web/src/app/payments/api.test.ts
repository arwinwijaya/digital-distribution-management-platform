/**
 * Payment history paging — 15 rows/page, newest first, with dummy-mode parity.
 *
 * Exercises the REAL `loadPaymentsList` loader + REAL store/guards (no module
 * mocks). Only `global.fetch` is faked; its call count is the zero-network
 * oracle for the dummy branch.
 */
import { useDummyStore, setDummyGenerator } from '@/dummy/store';
import type { DummyEntities } from '@/dummy/store';
import { buildFullDummy } from '@/dummy';
import { loadPaymentsList } from '@/app/payments/api';

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

const paymentIds = () =>
  (DUMMY as unknown as { payments: Array<{ id: number }> }).payments.map((p) => p.id);

describe('payment history — dummy paging parity (15/page, newest first)', () => {
  it('returns exactly 15 rows on page 1 with a real total and has_more', async () => {
    turnDummyOn();

    const result = await loadPaymentsList('t-token', 'admin', 1);

    expect(result.payments).toHaveLength(15);
    expect(result.paymentMeta).toEqual({
      page: 1,
      limit: 15,
      total: paymentIds().length,
      has_more: true,
    });
    expect(fetchMock).not.toHaveBeenCalled();
  });

  it('orders newest first (created_at DESC, id DESC tiebreak)', async () => {
    turnDummyOn();

    const result = await loadPaymentsList('t-token', 'admin', 1);
    const dates = result.payments.map((payment) => new Date(payment.created_at).getTime());

    for (let i = 0; i < dates.length - 1; i += 1) {
      expect(dates[i]).toBeGreaterThanOrEqual(dates[i + 1]);
    }
  });

  it('slices page 2 at the correct offset without overlapping page 1', async () => {
    turnDummyOn();

    const page1 = await loadPaymentsList('t-token', 'admin', 1);
    const page2 = await loadPaymentsList('t-token', 'admin', 2);

    expect(page2.paymentMeta.page).toBe(2);
    expect(page2.payments).toHaveLength(15);

    const page1Ids = page1.payments.map((p) => p.id);
    const page2Ids = page2.payments.map((p) => p.id);
    expect(page2Ids).not.toEqual(page1Ids);
    expect(page1Ids.some((id) => page2Ids.includes(id))).toBe(false);
  });

  it('reports has_more=false on the last page', async () => {
    turnDummyOn();

    const lastPage = Math.ceil(paymentIds().length / 15);
    const result = await loadPaymentsList('t-token', 'admin', lastPage);

    expect(result.paymentMeta.has_more).toBe(false);
    expect(result.payments.length).toBeGreaterThan(0);
    expect(result.payments.length).toBeLessThanOrEqual(15);
  });
});

describe('payment history — real path forwards page + limit=15', () => {
  it('requests both /payments and /invoices with the 15-per-page contract', async () => {
    useDummyStore.getState().reset();
    expect(useDummyStore.getState().isDummy).toBe(false);

    await loadPaymentsList('t-token', 'admin', 2);

    const urls = fetchMock.mock.calls.map((call) => String(call[0]));
    const paymentsUrl = urls.find((url) => url.includes('/payments?')) ?? '';
    const invoicesUrl = urls.find((url) => url.includes('/invoices?')) ?? '';

    expect(paymentsUrl).toContain('page=2');
    expect(paymentsUrl).toContain('limit=15');
    expect(invoicesUrl).toContain('page=2');
    expect(invoicesUrl).toContain('limit=15');
  });
});

describe('payment history — credit summary is outlet-scoped', () => {
  beforeEach(() => {
    useDummyStore.getState().reset();
    expect(useDummyStore.getState().isDummy).toBe(false);
  });

  it.each(['admin', 'finance'])('does NOT request /credit-limit for %s (would 422 for admin)', async (role) => {
    await loadPaymentsList('t-token', role, 1);

    const urls = fetchMock.mock.calls.map((call) => String(call[0]));
    expect(urls.some((url) => url.includes('/credit-limit'))).toBe(false);
    expect(urls).toHaveLength(2);
  });

  it('requests /credit-limit exactly once for an outlet', async () => {
    await loadPaymentsList('t-token', 'outlet', 1);

    const urls = fetchMock.mock.calls.map((call) => String(call[0]));
    expect(urls.filter((url) => url.includes('/credit-limit'))).toHaveLength(1);
    expect(urls).toHaveLength(3);
  });
});

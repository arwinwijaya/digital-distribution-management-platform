/**
 * Delivery loader — dummy-mode enrichment + timezone-safe date bucketing.
 *
 * Exercises the REAL `loadDeliveries` + REAL store/guards (no module mocks).
 * Only `global.fetch` is faked; its call count is the zero-network oracle for
 * the dummy branch.
 */
import { useDummyStore, setDummyGenerator } from '@/dummy/store';
import type { DummyEntities } from '@/dummy/store';
import { buildFullDummy } from '@/dummy';
import { loadDeliveries, toLocalDateString, uploadProof } from '@/app/delivery/api';

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

describe('toLocalDateString — Asia/Jakarta bucketing', () => {
  it('maps a UTC instant near midnight to the NEXT local day', () => {
    // 2026-02-13T23:00:00Z == 2026-02-14T06:00:00+07:00 (WIB)
    expect(toLocalDateString('2026-02-13T23:00:00.000000Z')).toBe('2026-02-14');
  });

  it('maps a +07:00 instant to the same local day', () => {
    expect(toLocalDateString('2026-02-14T06:00:00+07:00')).toBe('2026-02-14');
  });

  it('returns null for missing or unparseable input (never throws)', () => {
    expect(toLocalDateString(null)).toBeNull();
    expect(toLocalDateString(undefined)).toBeNull();
    expect(toLocalDateString('')).toBeNull();
    expect(toLocalDateString('not-a-date')).toBeNull();
  });
});

describe('loadDeliveries — dummy enrichment', () => {
  it('joins order + outlet and synthesizes name fallbacks without network', async () => {
    turnDummyOn();

    const rows = await loadDeliveries('t-token');

    expect(fetchMock).not.toHaveBeenCalled();
    expect(rows.length).toBeGreaterThan(0);

    const first = rows[0];
    // Order is joined (dummy delivery.order_id is a numeric order id).
    expect(first.order).not.toBeNull();
    expect(typeof first.order?.order_id).toBe('string');
    expect(Array.isArray(first.order?.items)).toBe(true);
    expect(first.order?.items.length).toBeGreaterThan(0);
    expect(first.order?.items[0]).toHaveProperty('product_name');
    // Outlet is resolved via `outlet_code === outlet.id` (dummy-00N).
    expect(first.order?.outlet).not.toBeNull();
    expect(typeof first.order?.outlet?.name).toBe('string');
    expect(first.order?.outlet?.address).toBeNull();
    // Dummy has no driver/sales master — names fall back to stable labels.
    expect(first.driver?.name).toMatch(/^Driver #\d+$/);
    expect(first.order?.sales?.name).toMatch(/^Sales #[1-5]$/);
    // The date filter key is populated for every dummy row.
    expect(first.assigned_date).toMatch(/^\d{4}-\d{2}-\d{2}$/);
    expect(first.assigned_at).not.toBeNull();
  });

  it('keeps every row enrichable (no null order for factory deliveries)', async () => {
    turnDummyOn();

    const rows = await loadDeliveries('t-token');
    expect(rows.every((row) => row.order !== null)).toBe(true);
    expect(rows.every((row) => row.assigned_date !== null)).toBe(true);
  });
});

/**
 * Upload-proof helper tests — exercise dummy branch and network branch.
 */
describe('uploadProof — PoD upload', () => {
  let originalFetch: typeof fetch | undefined;
  let fetchMock: jest.Mock;

  beforeEach(() => {
    localStorage.clear();
    localStorage.setItem('ddp_token', 't-token');
    useDummyStore.getState().reset();
    setDummyGenerator(() => DUMMY);

    fetchMock = jest.fn(async () => ({
      ok: true,
      status: 201,
      json: async () => ({
        status: 'success',
        data: {
          id: 1,
          order_id: 1,
          driver_id: 1,
          status: 'delivered',
          delivered_at: '2026-09-22T08:00:00Z',
          proof_of_delivery: {
            photo_url: 'http://example.com/photo.png',
            signature_url: 'http://example.com/sig.png',
            captured_at: '2026-09-22T08:00:00Z',
          },
        },
      }),
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

  it('returns a fake proof response in dummy mode without network', async () => {
    turnDummyOn();

    const formData = new FormData();
    formData.append('photo', new File(['x'], 'photo.jpg', { type: 'image/jpeg' }));
    formData.append('signature', new File(['x'], 'sig.png', { type: 'image/png' }));

    const result = await uploadProof(1, formData);

    expect(fetchMock).not.toHaveBeenCalled();
    expect(result.id).toBe(1);
    expect(result.status).toBe('delivered');
    expect(result.proof_of_delivery?.photo_url).toMatch(/^dummy:/);
    expect(result.proof_of_delivery?.signature_url).toMatch(/^dummy:/);
  });

  it('POSTs multipart/form-data to the backend when dummy is OFF', async () => {
    // dummy OFF (default)
    const formData = new FormData();
    formData.append('photo', new File(['x'], 'photo.jpg', { type: 'image/jpeg' }));
    formData.append('signature', new File(['x'], 'sig.png', { type: 'image/png' }));

    await uploadProof(42, formData);

    expect(fetchMock).toHaveBeenCalledTimes(1);
    const [url, init] = fetchMock.mock.calls[0];
    expect(url).toMatch(/\/deliveries\/42\/proof$/);
    expect(init).toEqual(
      expect.objectContaining({
        method: 'POST',
        headers: expect.objectContaining({ Authorization: 'Bearer t-token' }),
        body: formData,
      }),
    );
  });

  it('throws on non-OK response', async () => {
    fetchMock.mockResolvedValueOnce({
      ok: false,
      status: 422,
      json: async () => ({ message: 'Ukuran foto melebihi batas.' }),
    } as unknown as Response);

    const formData = new FormData();
    formData.append('photo', new File(['x'], 'photo.jpg', { type: 'image/jpeg' }));
    formData.append('signature', new File(['x'], 'sig.png', { type: 'image/png' }));

    await expect(uploadProof(1, formData)).rejects.toThrow(/ukuran foto/i);
  });
});

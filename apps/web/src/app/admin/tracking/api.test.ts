import { getTrack } from '@/app/admin/tracking/api';
import { useDummyStore, setDummyGenerator } from '@/dummy/store';
import type { DummyEntities } from '@/dummy/store';
import { buildFullDummy } from '@/dummy';

jest.mock('@/lib/api', () => ({
  apiUrl: (path: string) => `http://localhost:8000/api${path}`,
  authHeaders: (token: string) => ({ Authorization: `Bearer ${token}` }),
  getStoredToken: () => 'test-token',
}));

const DUMMY = buildFullDummy(new Date('2026-09-22T08:00:00Z'));

describe('getTrack — dummy branch', () => {
  beforeEach(() => {
    localStorage.clear();
    useDummyStore.getState().reset();
    setDummyGenerator(() => DUMMY as unknown as DummyEntities);
  });

  afterEach(() => {
    useDummyStore.getState().reset();
  });

  it('returns a deterministic fixture without network when dummy is ON', async () => {
    useDummyStore.getState().toggle();
    expect(useDummyStore.getState().isDummy).toBe(true);

    const fetchMock = jest.fn();
    const originalFetch = globalThis.fetch;
    globalThis.fetch = fetchMock as unknown as typeof fetch;

    try {
      const first = DUMMY.deliveries[0];
      const result = await getTrack(first.id);

      expect(fetchMock).not.toHaveBeenCalled();
      expect(result.delivery_id).toBe(first.id);
      expect(result.status).toBe(first.status);
      expect(result.last_position).not.toBeNull();
      expect(result.pings.length).toBeGreaterThanOrEqual(1);
    } finally {
      globalThis.fetch = originalFetch;
    }
  });

  it('returns a static point near Jakarta for every dummy delivery', async () => {
    useDummyStore.getState().toggle();

    for (const d of DUMMY.deliveries.slice(0, 5)) {
      const result = await getTrack(d.id);
      expect(result.last_position?.latitude).toBeLessThan(0);
      expect(result.last_position?.longitude).toBeGreaterThan(100);
    }
  });
});

describe('getTrack — network branch', () => {
  let fetchMock: jest.Mock;
  let originalFetch: typeof fetch | undefined;

  beforeEach(() => {
    localStorage.clear();
    useDummyStore.getState().reset();
    fetchMock = jest.fn(async () => ({
      ok: true,
      status: 200,
      json: async () => ({
        status: 'success',
        data: {
          delivery_id: 42,
          status: 'in_progress',
          driver: { id: 1, name: 'Driver 1' },
          last_position: {
            latitude: -6.2,
            longitude: 106.8,
            accuracy_m: 8,
            recorded_at: '2026-09-22T08:00:00Z',
          },
          pings: [],
        },
      }),
    }) as unknown as Response);
    originalFetch = globalThis.fetch;
    globalThis.fetch = fetchMock as unknown as typeof fetch;
  });

  afterEach(() => {
    if (originalFetch) globalThis.fetch = originalFetch;
    useDummyStore.getState().reset();
  });

  it('GETs the admin track endpoint with bearer auth', async () => {
    const result = await getTrack(42);

    expect(fetchMock).toHaveBeenCalledTimes(1);
    const [url, init] = fetchMock.mock.calls[0];
    expect(url).toMatch(/\/admin\/deliveries\/42\/track$/);
    expect(init.headers).toEqual(expect.objectContaining({ Authorization: 'Bearer test-token' }));
    expect(result.delivery_id).toBe(42);
  });

  it('throws a friendly error on failure', async () => {
    fetchMock.mockResolvedValueOnce({
      ok: false,
      status: 403,
      json: async () => ({ message: 'Tidak diizinkan.' }),
    } as unknown as Response);

    await expect(getTrack(42)).rejects.toThrow(/tidak diizinkan/i);
  });
});

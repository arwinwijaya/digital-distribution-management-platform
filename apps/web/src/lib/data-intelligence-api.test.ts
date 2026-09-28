/**
 * T5 — Cycle 2: `fetchGeographicData` propagates the typed `ApiError`
 * unchanged (same instance, same `status`, same `retryable`).
 *
 * Level: unit. Only the outermost collaborator (`global.fetch`) is doubled;
 * the unit under test (`fetchGeographicData`) and its wrapper (`adminFetch`)
 * are the real production code.
 *
 * Failure classification branches on `ApiError.status` / `instanceof` —
 * never on localized message text. The dummy path must never mask a real
 * failure, and `withDummyRead` must still short-circuit when dummy is ON.
 */
import { fetchGeographicData } from '@/lib/data-intelligence-api';
import { ApiError, isRetryableStatus } from '@/lib/api-error';
import { useDummyStore, setDummyGenerator } from '@/dummy/store';

const realFetch = global.fetch;

const DUMMY_GEO = {
  table: [{ territory: 'dummy', sales: '0', orders: 0, outlets: 0 }],
  map_points: [],
  snapshot_version: 0,
  window: { start: '2026-01-01', end: '2026-01-07', timezone: 'Asia/Jakarta' },
};

function mockHttpResponse(status: number, body: unknown): void {
  global.fetch = jest.fn().mockResolvedValue({
    ok: status >= 200 && status < 300,
    status,
    json: async () => body,
  }) as unknown as typeof fetch;
}

function mockFetchRejection(error: unknown): void {
  global.fetch = jest.fn().mockRejectedValue(error) as unknown as typeof fetch;
}

async function captureGeographicError(): Promise<unknown> {
  try {
    await fetchGeographicData();
  } catch (error) {
    return error;
  }
  throw new Error('fetchGeographicData did not reject');
}

beforeEach(() => {
  localStorage.clear();
  useDummyStore.getState().reset();
  localStorage.setItem('ddp_token', 't-token');
  setDummyGenerator(() => ({ geographic: DUMMY_GEO }));
});

afterEach(() => {
  global.fetch = realFetch;
  jest.restoreAllMocks();
});

describe('fetchGeographicData — typed ApiError propagates unchanged', () => {
  it('propagates the SAME ApiError instance (status:403, retryable:false) when the fetch wrapper rejects with it, and no dummy fallback masks it', async () => {
    // Arrange: dummy entities exist in memory, but dummy mode is OFF, so the
    // real path must run and the failure must surface untouched.
    useDummyStore.setState({ isDummy: false, dummyEntities: { geographic: DUMMY_GEO } });
    const expected = new ApiError(403, 'Akses ditolak');
    mockFetchRejection(expected);

    // Act
    const caught = await captureGeographicError();

    // Assert identity: not wrapped, not reclassified, not swallowed
    expect(caught).toBe(expected);
    expect(caught).toBeInstanceOf(ApiError);
    expect((caught as ApiError).status).toBe(403);
    expect((caught as ApiError).retryable).toBe(false);
    expect(isRetryableStatus((caught as ApiError).status)).toBe(false);
    expect((caught as ApiError).name).toBe('ApiError');

    // Assert the dummy path was NOT used even though entities exist
    expect(useDummyStore.getState().isDummy).toBe(false);
    expect(global.fetch).toHaveBeenCalledTimes(1);
  });

  it('propagates ApiError{status:403, retryable:false} from a real 403 HTTP response (status-based, no message branching)', async () => {
    mockHttpResponse(403, { status: 'error', message: 'Server sedang sibuk' });

    const caught = (await captureGeographicError()) as ApiError;

    expect(caught).toBeInstanceOf(ApiError);
    expect(caught.status).toBe(403);
    // A 403 must NEVER be retryable, regardless of the message text
    expect(caught.retryable).toBe(false);
    expect(isRetryableStatus(caught.status)).toBe(false);
    expect(global.fetch).toHaveBeenCalledTimes(1);
  });

  it('propagates ApiError{status:500, retryable:true} from a real 500 HTTP response (status-based, no message branching)', async () => {
    mockHttpResponse(500, { status: 'error', message: 'Akses ditolak' });

    const caught = (await captureGeographicError()) as ApiError;

    expect(caught).toBeInstanceOf(ApiError);
    expect(caught.status).toBe(500);
    // A 500 must be retryable, even though the message reads like a denial
    expect(caught.retryable).toBe(true);
    expect(isRetryableStatus(caught.status)).toBe(true);
  });

  it('keeps withDummyRead short-circuit semantics when isDummy is true (dummy value, zero network)', async () => {
    useDummyStore.getState().toggle();
    expect(useDummyStore.getState().isDummy).toBe(true);
    // Even a fetch stub that would reject must never be reached while ON
    mockFetchRejection(new ApiError(403, 'should never be called'));

    const result = await fetchGeographicData();

    expect(result).toEqual(DUMMY_GEO);
    expect(global.fetch).not.toHaveBeenCalled();
  });
});

/**
 * T5 — Cycle 1: typed `ApiError` with the real HTTP status and a
 * status-driven `retryable` classification.
 *
 * Level: unit. Only `global.fetch` is doubled; the unit under test
 * (`adminFetch`, the real fetch wrapper in `@/lib/data-intelligence-api`)
 * is the real production code.
 *
 * Classification MUST branch on the HTTP status (and the null network
 * status) — never on the localized message string.
 */
import { adminFetch } from '@/lib/data-intelligence-api';
import { ApiError } from '@/lib/api-error';

const realFetch = global.fetch;

function mockHttpResponse(status: number, body: unknown): void {
  global.fetch = jest.fn().mockResolvedValue({
    ok: status >= 200 && status < 300,
    status,
    json: async () => body,
  }) as unknown as typeof fetch;
}

async function captureError(path: string): Promise<ApiError> {
  try {
    await adminFetch(path);
  } catch (error) {
    return error as ApiError;
  }
  throw new Error('adminFetch did not throw');
}

beforeEach(() => {
  localStorage.clear();
  localStorage.setItem('ddp_token', 'test-token');
});

afterEach(() => {
  global.fetch = realFetch;
  jest.restoreAllMocks();
});

describe('adminFetch — typed ApiError from real HTTP status', () => {
  it('throws ApiError{status:500, retryable:true} for HTTP 500', async () => {
    mockHttpResponse(500, { status: 'error', message: 'Kesalahan server' });

    const error = await captureError('/admin/analytics/geographic');

    expect(error).toBeInstanceOf(ApiError);
    expect(error.status).toBe(500);
    expect(error.retryable).toBe(true);
    expect(error.message).toBe('Kesalahan server');
  });

  it.each([401, 403])(
    'throws ApiError{status:%i, retryable:false} for HTTP %i',
    async (status) => {
      mockHttpResponse(status, { status: 'error', message: 'Ditolak' });

      const error = await captureError('/admin/analytics/geographic');

      expect(error).toBeInstanceOf(ApiError);
      expect(error.status).toBe(status);
      expect(error.retryable).toBe(false);
    },
  );

  it('throws ApiError{status:null, retryable:true} on a network rejection', async () => {
    global.fetch = jest
      .fn()
      .mockRejectedValue(new TypeError('Failed to fetch')) as unknown as typeof fetch;

    const error = await captureError('/admin/analytics/geographic');

    expect(error).toBeInstanceOf(ApiError);
    expect(error.status).toBeNull();
    expect(error.retryable).toBe(true);
  });

  it('classifies only by status, never by localized message text', async () => {
    // 500 that *reads* like an access error must still be retryable.
    mockHttpResponse(500, { status: 'error', message: 'Akses ditolak' });
    const retryable = await captureError('/x');
    expect(retryable.status).toBe(500);
    expect(retryable.retryable).toBe(true);

    // 403 that *reads* like a transient server error must still not retry.
    mockHttpResponse(403, { status: 'error', message: 'Server sedang sibuk' });
    const forbidden = await captureError('/x');
    expect(forbidden.status).toBe(403);
    expect(forbidden.retryable).toBe(false);
  });
});

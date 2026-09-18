/**
 * Insight loader — real fetch path + dummy-mode zero-network parity.
 *
 * Exercises the REAL `loadAnalyticsInsight` loader + REAL store/guards (no
 * module mocks). Only `global.fetch` is faked; its call count is the
 * zero-network oracle for the dummy branch.
 */
import { useDummyStore, setDummyGenerator } from '@/dummy/store';
import type { DummyEntities } from '@/dummy/store';
import { buildFullDummy } from '@/dummy';
import { loadAnalyticsInsight } from '@/app/analytics/api';

const FIXED_TODAY = new Date('2026-02-14T10:00:00+07:00');
const DUMMY = buildFullDummy(FIXED_TODAY) as unknown as DummyEntities;

let originalFetch: typeof fetch | undefined;
let fetchMock: jest.Mock;

beforeEach(() => {
  localStorage.clear();
  useDummyStore.getState().reset();
  localStorage.setItem('ddp_token', 't-token');
  setDummyGenerator(() => DUMMY);

  fetchMock = jest.fn();
  originalFetch = (globalThis as unknown as { fetch?: typeof fetch }).fetch;
  (globalThis as unknown as { fetch: unknown }).fetch = fetchMock;
});

afterEach(() => {
  if (originalFetch) (globalThis as unknown as { fetch: unknown }).fetch = originalFetch;
  else delete (globalThis as unknown as { fetch?: unknown }).fetch;
  useDummyStore.getState().reset();
});

describe('loadAnalyticsInsight — real path', () => {
  it('fetches /analytics/insight exactly once and resolves the insight payload', async () => {
    const payload = {
      status: 'success',
      data: {
        comparison: {
          period: { start_date: '2026-08-20', end_date: '2026-09-18' },
          previous_period: { start_date: '2026-07-21', end_date: '2026-08-19' },
        },
        metrics: {
          orders_total: 12,
          sales_total: '1200.00',
          outlets_total: 3,
          products_total: 5,
          payments_total: '900.00',
          outstanding_total: '300.00',
        },
        metrics_delta: {
          orders_total: { delta_percent: 20.0, direction: 'up' },
          sales_total: { delta_percent: -10.0, direction: 'down' },
          payments_total: { delta_percent: null, direction: 'neutral' },
          outstanding_total: { delta_percent: 0.0, direction: 'neutral' },
        },
        needs_attention: [
          {
            outlet_id: 1,
            outlet_name: 'Toko A',
            reason: 'sales_decline',
            delta_percent: -30.0,
            outstanding_total: '100.00',
          },
        ],
        sales_trends: Array.from({ length: 30 }, (_, i) => ({
          period: `2026-08-${String(i + 1).padStart(2, '0')}`,
          orders_total: 1,
          sales_total: '10.00',
          payments_total: '10.00',
        })),
        outlet_performance: [],
        outlet_performance_total: 3,
        outlet_performance_has_more: false,
      },
    };
    fetchMock.mockResolvedValue({
      ok: true,
      status: 200,
      json: async () => payload,
    } as unknown as Response);

    const result = await loadAnalyticsInsight('t-token');

    expect(fetchMock).toHaveBeenCalledTimes(1);
    expect(String(fetchMock.mock.calls[0][0])).toContain('/analytics/insight');
    expect(result.metrics_delta.sales_total).toEqual({
      delta_percent: -10.0,
      direction: 'down',
    });
    expect(result.sales_trends).toHaveLength(30);
    expect(result.needs_attention).toHaveLength(1);
    expect(result.needs_attention[0].reason).toBe('sales_decline');
  });

  it('rejects with the server message when the envelope reports an error', async () => {
    fetchMock.mockResolvedValue({
      ok: false,
      status: 403,
      json: async () => ({ status: 'error', message: 'Unauthorized. Only admins can view analytics.' }),
    } as unknown as Response);

    await expect(loadAnalyticsInsight('t-token')).rejects.toThrow(
      'Unauthorized. Only admins can view analytics.',
    );
  });
});

describe('loadAnalyticsInsight — dummy mode parity', () => {
  function turnDummyOn(): void {
    useDummyStore.getState().toggle();
    expect(useDummyStore.getState().isDummy).toBe(true);
  }

  it('never touches the network and returns the split-window fixture', async () => {
    turnDummyOn();

    const result = await loadAnalyticsInsight('t-token');

    expect(fetchMock).not.toHaveBeenCalled();
    expect(result.sales_trends).toHaveLength(30);
    expect(result.metrics_delta).toBeDefined();
    expect(result.needs_attention).toBeDefined();
    expect(result.comparison.period).toEqual({
      start_date: '2026-01-16',
      end_date: '2026-02-14',
    });
  });
});

/**
 * Integration tests for outlet dashboard API branch & retry isolation (T4).
 */
import { loadDashboard, fetchOutletDashboard, __clearOutletCacheForTests } from './api';
import { useDummyStore } from '@/dummy/store';
import { buildFullDummy } from '@/dummy';

// Mock global fetch
const mockFetch = jest.fn();
global.fetch = mockFetch;

describe('loadDashboard / outlet branch (T4)', () => {
  beforeEach(() => {
    jest.clearAllMocks();
    __clearOutletCacheForTests();
    useDummyStore.setState({ isDummy: false, dummyEntities: null });
  });

  it('fetches newest 1000 orders without date filters, then applies exact UTC-instant filtering client-side', async () => {
    // Boundary-aware fixture including orders outside/in-range relative to (now - 29 days)
    const ordersData = [{
      id: 3, order_id: 'ORD-3', status: 'New', total_amount: '100.00', paid_amount: '100.00',
      created_at: new Date(Date.now() - 29 * 86400000).toISOString(),
      items: [{ product_id: 1, product_name: 'Beras Premium', quantity: 2, unit_price: '50.00', subtotal: '100.00' }],
    }, {
      id: 2, order_id: 'ORD-2', status: 'Delivered', total_amount: '100.00', paid_amount: '100.00',
      created_at: new Date(Date.now() - 30 * 86400000 - 60000).toISOString(),
      items: [{ product_id: 1, product_name: 'Beras Premium', quantity: 2, unit_price: '50.00', subtotal: '100.00' }],
    }, {
      id: 4, order_id: 'ORD-4', status: 'New', total_amount: '100.00', paid_amount: '100.00',
      created_at: new Date().toISOString(),
      items: [{ product_id: 1, product_name: 'Beras Premium', quantity: 2, unit_price: '50.00', subtotal: '100.00' }],
    }];
    mockFetch.mockImplementation(async (url: string) => {
      if (url.includes('/orders')) {
        expect(url).not.toContain('start=');
        expect(url).not.toContain('end=');
        return {
          ok: true,
          json: async () => ({
            status: 'success',
            data: ordersData,
            meta: { has_more: false, total: 3, limit: 100, cursor: 0 },
          }),
        };
      }
      if (url.includes('/credit-limit')) {
        return {
          ok: true,
          json: async () => ({
            status: 'success',
            data: { credit_limit: 5000000, outstanding_balance: '200000.00', available_credit: 4800000 },
          }),
        };
      }
      return { ok: false, json: async () => ({ message: 'Not found' }) };
    });
    const result = await loadDashboard('token-outlet', 'outlet', 'monthly');
    expect(result.kind).toBe('outlet');
    if (result.kind === 'outlet') {
      // summary/recent/meta: newest 3 unfiltered; period aggregates: only start-instant..now (excl. prior)
      expect(result.data.summary.total).toBe(3);
      expect(result.data.summary.truncation).toEqual({ capped: false, total: 3 });
      expect(result.data.shopping.count).toBe(2);
      expect(result.data.credit.credit_limit).toBe('5000000.00');
      expect(result.data.favorites).toEqual([{
        product_id: 1,
        display_name: 'Beras Premium',
        total_qty: 4,
      }]);
    }
  });

  it('retry isolation: credit fetch failure and retry does NOT re-fetch order pages', async () => {
    // Orders fetch succeeds
    mockFetch.mockImplementation(async (url: string) => {
      if (url.includes('/orders')) {
        return {
          ok: true,
          json: async () => ({
            status: 'success',
            data: [{ id: 1, order_id: 'ORD-1', status: 'New', total_amount: '100.00', paid_amount: '0.00', created_at: '2026-05-10T10:00:00Z', items: [{ product_id: 1, product_name: 'Gula', quantity: 1, unit_price: '100.00', subtotal: '100.00' }] }],
            meta: { has_more: false, total: 1, limit: 100, cursor: 0 },
          }),
        };
      }
      if (url.includes('/credit-limit')) {
        return { ok: false, status: 500, json: async () => ({ message: 'Server error' }) };
      }
      return { ok: false, json: async () => {} };
    });

    // First load should throw because credit failed
    await expect(fetchOutletDashboard('token-outlet')).rejects.toThrow('Server error');

    // Verify orders was fetched once
    const orderFetchCount = mockFetch.mock.calls.filter(([url]) => String(url).includes('/orders')).length;
    expect(orderFetchCount).toBe(1);

    // Now mock credit-limit to succeed on retry
    mockFetch.mockImplementation(async (url: string) => {
      if (url.includes('/credit-limit')) {
        return {
          ok: true,
          json: async () => ({
            status: 'success',
            data: { credit_limit: 1000000, outstanding_balance: '0.00', available_credit: 1000000 },
          }),
        };
      }
      return { ok: false, json: async () => {} };
    });

    // Call fetchOutletDashboard again (simulating per-section credit retry)
    const retryResult = await fetchOutletDashboard('token-outlet');
    expect(retryResult.kind).toBe('outlet');

    // Verify orders fetch count did NOT increase (cached!)
    const orderFetchCountAfterRetry = mockFetch.mock.calls.filter(([url]) => String(url).includes('/orders')).length;
    expect(orderFetchCountAfterRetry).toBe(1); // 0 additional order fetches
  });

  it('withDummyRead short-circuits to dummy.dashboardOutlet when isDummy is true', async () => {
    const dummy = buildFullDummy(new Date('2026-02-14T10:00:00+07:00'));
    useDummyStore.setState({ isDummy: true, dummyEntities: dummy as unknown as Record<string, unknown> });

    const result = await loadDashboard('token-outlet', 'outlet', 'monthly');
    expect(result.kind).toBe('outlet');
    if (result.kind === 'outlet') {
      expect(result.data).toEqual(dummy.dashboardOutlet);
    }
    // Verify 0 fetch calls made
    expect(mockFetch).not.toHaveBeenCalled();
  });
});

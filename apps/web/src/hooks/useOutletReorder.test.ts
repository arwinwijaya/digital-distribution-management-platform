// Pass: RED→GREEN contract retries each failed cycle.
// The spec's "loadOrderFormProducts" restriction is met — no catalog fetch here.

import { act, renderHook, waitFor } from '@testing-library/react';
import { useOutletReorder } from './useOutletReorder';
import * as api from '@/app/dashboard/api';
import type { Product } from '@/components/OrderForm';

jest.mock('@/app/dashboard/api', () => ({
  ...(jest.requireActual('@/app/dashboard/api') as Record<string, unknown>),
  fetchOutletDashboard: jest.fn(),
  loadOutletCachedSections: jest.fn(),
}));

function makeProduct(overrides: Partial<Product> & Required<Pick<Product, 'id'>>): Product {
  return {
    name: `Product ${String(overrides.id)}`,
    price: '10000',
    stock_quantity: 50,
    is_active: true,
    ...overrides,
  };
}

describe('useOutletReorder', () => {
  beforeEach(() => {
    jest.clearAllMocks();
  });

  it('R1: reorder list composition — favorites + 3 recent unique, exclude inactive (dedup)', async () => {
    (api.loadOutletCachedSections as jest.Mock).mockReturnValue({
      favorites: [
        { product_id: 1, display_name: 'Product A', total_qty: 10 },
        { product_id: 2, display_name: 'Product B', total_qty: 8 },
        { product_id: 3, display_name: 'Product C', total_qty: 6 },
        { product_id: 4, display_name: 'Product D', total_qty: 4 },
        { product_id: 5, display_name: 'Product E', total_qty: 2 },
      ],
      shopping: { total: '0', count: 0, period_label: '30 hari terakhir' },
      orders: [
        {
          id: 3,
          order_id: 'ORD-3',
          status: 'Delivered',
          created_at: '2026-10-01T10:00:00Z',
          items: [
            { product_id: 1, product_name: 'Product A', quantity: 2, unit_price: '1000', subtotal: '2000' },
            { product_id: 6, product_name: 'Product F', quantity: 1, unit_price: '1500', subtotal: '1500' },
          ],
        },
        {
          id: 2,
          order_id: 'ORD-2',
          status: 'Delivered',
          created_at: '2026-09-28T10:00:00Z',
          items: [
            { product_id: 7, product_name: 'Product G', quantity: 3, unit_price: '2000', subtotal: '6000' },
            { product_id: 2, product_name: 'Product B', quantity: 1, unit_price: '1000', subtotal: '1000' },
            { product_id: 8, product_name: 'Product H (inactive)', quantity: 1, unit_price: '500', subtotal: '500' },
          ],
        },
        {
          id: 1,
          order_id: 'ORD-1',
          status: 'Delivered',
          created_at: '2026-09-27T10:00:00Z',
          items: [
            { product_id: 1, product_name: 'Product A', quantity: 5, unit_price: '1000', subtotal: '5000' },
          ],
        },
      ],
    });

    const productsFixture: Product[] = [
      makeProduct({ id: 1, price: '12000' }),
      makeProduct({ id: 2, price: '10000' }),
      makeProduct({ id: 3, price: '8000' }),
      makeProduct({ id: 4, price: '6000' }),
      makeProduct({ id: 5, price: '5000' }),
      makeProduct({ id: 6, price: '15000', name: 'Product F' }),
      makeProduct({ id: 7, price: '20000', name: 'Product G' }),
      makeProduct({ id: 8, price: '5000', stock_quantity: 0, is_active: false, name: 'Product H (inactive)' }),
    ];

    const { result } = renderHook(() => useOutletReorder('token-test', productsFixture));

    await waitFor(() => expect(result.current.loading).toBe(false));

    const ids = result.current.reorderItems.map((item) => item.product_id);
    expect(ids).toEqual([1, 2, 3, 4, 5, 6, 7]);
    expect(ids).not.toContain(8);
  });

  it('R5: enrichment uses current_price from products param, not historical unit_price', async () => {
    (api.loadOutletCachedSections as jest.Mock).mockReturnValue({
      favorites: [{ product_id: 1, display_name: 'Product A', total_qty: 5 }],
      shopping: { total: '0', count: 0, period_label: '30 hari terakhir' },
      orders: [
        {
          id: 1,
          order_id: 'ORD-1',
          status: 'Delivered',
          created_at: '2026-10-01T10:00:00Z',
          items: [{ product_id: 1, product_name: 'Product A', quantity: 2, unit_price: '10000', subtotal: '20000' }],
        },
      ],
    });

    const productsFixture: Product[] = [makeProduct({ id: 1, price: '12500', stock_quantity: 45, is_active: true })];

    const { result } = renderHook(() => useOutletReorder('token-test', productsFixture));

    await waitFor(() => expect(result.current.loading).toBe(false));

    expect(result.current.reorderItems).toHaveLength(1);
    expect(result.current.reorderItems[0].current_price).toBe('12500');
    expect(result.current.reorderItems[0].last_qty).toBe(2);
  });

  it('R6: cache miss triggers fetchOutletDashboard exactly once', async () => {
    (api.loadOutletCachedSections as jest.Mock)
      .mockReturnValueOnce(null)
      .mockReturnValueOnce({
        favorites: [{ product_id: 1, display_name: 'Product A', total_qty: 2 }],
        shopping: { total: '0', count: 0, period_label: '30 hari terakhir' },
        orders: [],
      });

    (api.fetchOutletDashboard as jest.Mock).mockResolvedValue({
      kind: 'outlet',
      data: {
        summary: { total: 1, statuses: {}, recent: [], truncation: { capped: false, total: 1 } },
        shopping: { total: '0', count: 0, period_label: '30 hari terakhir' },
        credit: { credit_limit: '1000000', outstanding_balance: '0', available_credit: '1000000', hidden: false },
        favorites: [{ product_id: 1, display_name: 'Product A', total_qty: 2 }],
      },
    });

    const { result } = renderHook(() => useOutletReorder('token-test', [makeProduct({ id: 1 })]));

    await waitFor(() => expect(result.current.loading).toBe(false));

    expect(api.fetchOutletDashboard).toHaveBeenCalledTimes(1);
    expect(api.fetchOutletDashboard).toHaveBeenCalledWith('token-test');
    expect(api.loadOutletCachedSections).toHaveBeenCalledTimes(2);
  });

  it('R4: credit_exceeded computed as (current_price × last_qty) > available_credit', async () => {
    (api.loadOutletCachedSections as jest.Mock)
      .mockReturnValueOnce(null)
      .mockReturnValueOnce({
        favorites: [{ product_id: 4, display_name: 'Product D', total_qty: 3 }],
        shopping: { total: '0', count: 0, period_label: '30 hari terakhir' },
        orders: [
          {
            id: 1,
            order_id: 'ORD-1',
            status: 'Delivered',
            created_at: '2026-10-01T10:00:00Z',
            items: [{ product_id: 4, product_name: 'Product D', quantity: 2, unit_price: '30000', subtotal: '60000' }],
          },
        ],
      });

    (api.fetchOutletDashboard as jest.Mock).mockResolvedValue({
      kind: 'outlet',
      data: {
        summary: {
          total: 1,
          statuses: {},
          recent: [
            { id: 1, order_id: 'ORD-1', status: 'Delivered', status_label: 'Terkirim', total_amount: '60000.00', created_at: '2026-10-01T10:00:00Z' },
          ],
          truncation: { capped: false, total: 1 },
        },
        shopping: { total: '60000', count: 1, period_label: '30 hari terakhir' },
        credit: { credit_limit: '100000', outstanding_balance: '0', available_credit: '50000.00', hidden: false },
        favorites: [{ product_id: 4, display_name: 'Product D', total_qty: 2 }],
      },
    });

    const { result } = renderHook(() => useOutletReorder('token-test', [makeProduct({ id: 4, price: '30000' })]));

    await waitFor(() => expect(result.current.loading).toBe(false));

    expect(result.current.reorderItems).toHaveLength(1);
    expect(result.current.reorderItems[0].credit_exceeded).toBe(true);
  });
});

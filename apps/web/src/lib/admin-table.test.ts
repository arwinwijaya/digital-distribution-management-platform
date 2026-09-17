/**
 * T2 — admin-table helpers (pure, no React).
 *
 * Cycle 1: sort allowlist mirror + normalizeSort.
 */
import { SORT_ALLOWLISTS, normalizeSort } from '@/lib/admin-table';

describe('SORT_ALLOWLISTS (mirror of backend controller allowlists)', () => {
  it('exposes an allowlist for all 6 admin pages', () => {
    expect(Object.keys(SORT_ALLOWLISTS).sort()).toEqual([
      'orders',
      'outlets',
      'products',
      'promotions',
      'sales-performance',
      'users',
    ]);
  });

  it('mirrors the backend sortable columns per page', () => {
    expect(SORT_ALLOWLISTS.outlets).toEqual([
      'created_at',
      'updated_at',
      'name',
      'id',
      'category',
      'score',
    ]);
    expect(SORT_ALLOWLISTS.products).toEqual([
      'created_at',
      'updated_at',
      'name',
      'sku',
      'price',
      'stock_quantity',
      'id',
    ]);
    expect(SORT_ALLOWLISTS.users).toEqual([
      'created_at',
      'updated_at',
      'name',
      'email',
      'role',
      'id',
    ]);
    expect(SORT_ALLOWLISTS.promotions).toEqual([
      'created_at',
      'updated_at',
      'start_date',
      'end_date',
      'id',
    ]);
    expect(SORT_ALLOWLISTS.orders).toEqual([
      'created_at',
      'updated_at',
      'order_id',
      'status',
      'total_amount',
      'id',
    ]);
    expect(SORT_ALLOWLISTS['sales-performance']).toEqual(['name', 'id']);
  });
});

describe('normalizeSort', () => {
  it('keeps an allowed column with a valid order', () => {
    expect(normalizeSort(SORT_ALLOWLISTS.outlets, 'name', 'asc')).toEqual({
      sort: 'name',
      order: 'asc',
    });
  });

  it('falls back to created_at desc for a disallowed column', () => {
    expect(normalizeSort(SORT_ALLOWLISTS.outlets, '__proto__', 'desc')).toEqual({
      sort: 'created_at',
      order: 'desc',
    });
  });

  it('falls back to desc for an invalid order', () => {
    expect(normalizeSort(SORT_ALLOWLISTS.outlets, 'name', 'DROP')).toEqual({
      sort: 'name',
      order: 'desc',
    });
  });
});

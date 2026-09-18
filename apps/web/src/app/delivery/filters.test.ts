/**
 * Delivery list search + filter — pure predicate unit tests.
 *
 * The date filter compares the LOCAL calendar date string (`assigned_date`,
 * `YYYY-MM-DD`) rather than the UTC-serialized `assigned_at`, so a delivery
 * assigned at 23:00Z (= 06:00 WIB next day) is not misfiled.
 */
import { filterDeliveries } from '@/app/delivery/filters';
import type { Delivery } from '@/app/delivery/api';

function row(overrides: Partial<Delivery> = {}): Delivery {
  return {
    id: 1,
    order_id: 1,
    driver_id: 1,
    status: 'assigned',
    delivered_at: null,
    recipient_name: null,
    assigned_at: '2026-02-14T06:00:00+07:00',
    assigned_date: '2026-02-14',
    started_at: null,
    failure_reason: null,
    notes: null,
    order: {
      order_id: 'ORD-2026-02-14-0001',
      total_amount: '60000.00',
      outlet: { id: 1, name: 'Toko Bogor Indah', city: 'Bogor', address: null },
      sales: { id: 2, name: 'Budi Sales' },
      items: [
        { product_id: 1, product_name: 'Beras Premium', quantity: 3, unit_price: '20000.00', subtotal: '60000.00' },
      ],
    },
    driver: { name: 'Driver #4' },
    assigned_by: { name: 'Admin JABODETABEK' },
    ...overrides,
  };
}

describe('filterDeliveries', () => {
  it('returns every row when no filter is set', () => {
    const rows = [row({ id: 1 }), row({ id: 2, status: 'delivered' })];
    expect(filterDeliveries(rows)).toHaveLength(2);
    expect(filterDeliveries(rows, {})).toHaveLength(2);
    expect(filterDeliveries(rows, { search: '   ' })).toHaveLength(2);
  });

  it('matches search against order code, outlet name, recipient and driver name', () => {
    const rows = [
      row({ id: 1, order: { ...row().order!, order_id: 'ORD-AAA-0001' } }),
      row({ id: 2, recipient_name: 'Siti Rahayu', order: { ...row().order!, outlet: { id: 2, name: 'Warung Melati', city: 'Depok', address: null } } }),
      row({ id: 3, driver: { name: 'Agus Santoso' } }),
    ];

    expect(filterDeliveries(rows, { search: 'aaa' }).map((r) => r.id)).toEqual([1]);
    expect(filterDeliveries(rows, { search: 'melati' }).map((r) => r.id)).toEqual([2]);
    expect(filterDeliveries(rows, { search: 'SITI' }).map((r) => r.id)).toEqual([2]);
    expect(filterDeliveries(rows, { search: 'agus' }).map((r) => r.id)).toEqual([3]);
    expect(filterDeliveries(rows, { search: 'zzz' })).toHaveLength(0);
  });

  it('null-guards a row whose order/driver/outlet is missing', () => {
    const bare = row({ id: 9, order: null, driver: null, assigned_by: null });
    expect(filterDeliveries([bare], { search: 'tok' })).toHaveLength(0);
    expect(filterDeliveries([bare], {})).toHaveLength(1);
  });

  it('filters by exact status across both vocabularies', () => {
    const rows = [
      row({ id: 1, status: 'assigned' }),
      row({ id: 2, status: 'in_progress' }),
      row({ id: 3, status: 'in_transit' }),
      row({ id: 4, status: 'pending' }),
      row({ id: 5, status: 'delivered' }),
    ];

    expect(filterDeliveries(rows, { status: 'assigned' }).map((r) => r.id)).toEqual([1]);
    expect(filterDeliveries(rows, { status: 'in_progress' }).map((r) => r.id)).toEqual([2]);
    expect(filterDeliveries(rows, { status: 'in_transit' }).map((r) => r.id)).toEqual([3]);
    expect(filterDeliveries(rows, { status: 'pending' }).map((r) => r.id)).toEqual([4]);
  });

  it('applies an inclusive date range on the local assigned_date', () => {
    const rows = [
      row({ id: 1, assigned_date: '2026-02-12' }),
      row({ id: 2, assigned_date: '2026-02-14' }),
      row({ id: 3, assigned_date: '2026-02-16' }),
    ];

    expect(filterDeliveries(rows, { dateFrom: '2026-02-14' }).map((r) => r.id)).toEqual([2, 3]);
    expect(filterDeliveries(rows, { dateTo: '2026-02-14' }).map((r) => r.id)).toEqual([1, 2]);
    expect(filterDeliveries(rows, { dateFrom: '2026-02-12', dateTo: '2026-02-16' })).toHaveLength(3);
    expect(filterDeliveries(rows, { dateFrom: '2026-02-13', dateTo: '2026-02-15' }).map((r) => r.id)).toEqual([2]);
  });

  it('treats a 23:00Z assignment as the next local day', () => {
    // 2026-02-13T23:00:00Z == 2026-02-14T06:00:00+07:00 (WIB)
    const rows = [row({ id: 1, assigned_date: '2026-02-14' })];
    expect(filterDeliveries(rows, { dateFrom: '2026-02-14', dateTo: '2026-02-14' }).map((r) => r.id)).toEqual([1]);
  });

  it('excludes null assigned_date only when a date filter is active', () => {
    const rows = [row({ id: 1, assigned_date: null }), row({ id: 2, assigned_date: '2026-02-14' })];
    expect(filterDeliveries(rows, {})).toHaveLength(2);
    expect(filterDeliveries(rows, { dateFrom: '2026-02-01' }).map((r) => r.id)).toEqual([2]);
  });

  it('ignores blank/invalid dates and returns nothing when from > to', () => {
    const rows = [row({ id: 1, assigned_date: '2026-02-14' })];
    expect(filterDeliveries(rows, { dateFrom: '   ' })).toHaveLength(1);
    expect(filterDeliveries(rows, { dateFrom: 'not-a-date' })).toHaveLength(1);
    expect(filterDeliveries(rows, { dateTo: '14/02/2026' })).toHaveLength(1);
    expect(filterDeliveries(rows, { dateFrom: '2026-03-01', dateTo: '2026-02-01' })).toHaveLength(0);
  });

  it('combines search + status + date range', () => {
    const rows = [
      row({ id: 1, status: 'delivered', assigned_date: '2026-02-14', order: { ...row().order!, outlet: { id: 1, name: 'Toko Bogor Indah', city: 'Bogor', address: null } } }),
      row({ id: 2, status: 'delivered', assigned_date: '2026-02-14', order: { ...row().order!, outlet: { id: 2, name: 'Warung Melati', city: 'Depok', address: null } } }),
      row({ id: 3, status: 'assigned', assigned_date: '2026-02-14' }),
    ];

    expect(
      filterDeliveries(rows, { search: 'bogor', status: 'delivered', dateFrom: '2026-02-14', dateTo: '2026-02-14' }).map((r) => r.id),
    ).toEqual([1]);
  });
});

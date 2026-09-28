import { periodToRange, ELIGIBLE_STATUSES, expandSemua, normalizeStatuses, computeFilteredCounts, FilteredCounts } from './geographic-filters';

type SnapshotWindow = {
  start: string;
  end: string;
  timezone: string;
};

const window: SnapshotWindow = {
  start: '2026-08-27',
  end: '2026-09-25',
  timezone: 'Asia/Jakarta',
};

describe('periodToRange', () => {
  it('maps periods to inclusive snapshot date ranges', () => {
    expect(periodToRange(window, '7d')).toEqual({
      start: '2026-09-19',
      end: '2026-09-25',
    });
    expect(periodToRange(window, '30d')).toEqual({
      start: '2026-08-27',
      end: '2026-09-25',
    });
    expect(periodToRange(window, 'today')).toEqual({
      start: '2026-09-25',
      end: '2026-09-25',
    });
  });
});

describe('status canonicalization', () => {
  it('exposes the four canonical eligible statuses', () => {
    expect(ELIGIBLE_STATUSES).toEqual(['New', 'Confirmed', 'Delivered', 'Partially Paid']);
  });

  it('expandSemua expands Semua to all four eligible statuses', () => {
    expect(expandSemua(['Semua'])).toEqual(['New', 'Confirmed', 'Delivered', 'Partially Paid']);
    expect(expandSemua(['New', 'Semua'])).toEqual(['New', 'Confirmed', 'Delivered', 'Partially Paid']);
    expect(expandSemua(['Confirmed', 'New'])).toEqual(['Confirmed', 'New']);
    expect(expandSemua([])).toEqual([]);
  });

  it('normalizeStatuses handles canonical statuses and Semua', () => {
    expect(normalizeStatuses(['New', 'Confirmed'])).toEqual(['New', 'Confirmed']);
    expect(normalizeStatuses(['Semua'])).toEqual(['New', 'Confirmed', 'Delivered', 'Partially Paid']);
    expect(normalizeStatuses(['Delivered', 'New'])).toEqual(['Delivered', 'New']);
    expect(normalizeStatuses(['Partially Paid', 'Confirmed', 'New'])).toEqual(['Partially Paid', 'Confirmed', 'New']);
  });
});

type TestPoint = {
  orders_by_status?: Record<string, number>;
  sales_by_status?: Record<string, string>;
  daily_by_status?: Array<{
    date: string;
    counts: Record<string, number>;
    sales: Record<string, string>;
  }>;
};

const v2Point: TestPoint = {
  orders_by_status: { New: 5, Confirmed: 2, Delivered: 1, 'Partially Paid': 1 },
  sales_by_status: {
    New: '50000.00',
    Confirmed: '20000.00',
    Delivered: '10000.00',
    'Partially Paid': '5000.00',
  },
  daily_by_status: [
    {
      date: '2026-09-10',
      counts: { New: 3, Confirmed: 0, Delivered: 0, 'Partially Paid': 0 },
      sales: { New: '30000.00', Confirmed: '0.00', Delivered: '0.00', 'Partially Paid': '0.00' },
    },
    {
      date: '2026-09-25',
      counts: { New: 2, Confirmed: 1, Delivered: 0, 'Partially Paid': 0 },
      sales: { New: '20000.00', Confirmed: '10000.00', Delivered: '0.00', 'Partially Paid': '0.00' },
    },
  ],
};

describe('computeFilteredCounts', () => {
  it('composes status selection with a 7d period using only in-range daily buckets', () => {
    const result: FilteredCounts = computeFilteredCounts(v2Point, ['New'], '7d', window);
    expect(result.filteredOrders).toBe(2);
    expect(result.statusCounts.New).toBe(2);
    expect(result.salesCents).toBe(2000000);
    expect(result.legacyOnly).toBe(false);
    expect(result.hasDailyDetail).toBe(true);
  });

  it('uses status-map totals for 30d across the selected statuses', () => {
    const result = computeFilteredCounts(v2Point, ['New', 'Confirmed'], '30d', window);
    expect(result.filteredOrders).toBe(7);
    expect(result.statusCounts).toEqual({ New: 5, Confirmed: 2 });
    expect(result.salesCents).toBe(7000000);
  });

  it('excludes daily buckets outside the period', () => {
    const result = computeFilteredCounts(v2Point, ['New'], '7d', window);
    expect(result.filteredOrders).not.toBe(5);
    expect(result.filteredOrders).toBe(2);
  });

  it('limits Hari ini to the snapshot end bucket only', () => {
    const result = computeFilteredCounts(v2Point, ['New', 'Confirmed'], 'today', window);
    expect(result.filteredOrders).toBe(3);
    expect(result.statusCounts).toEqual({ New: 2, Confirmed: 1 });
    expect(result.salesCents).toBe(3000000);
  });

  it('sums money as exact integer cents', () => {
    const point: TestPoint = {
      orders_by_status: { New: 1, Confirmed: 0, Delivered: 0, 'Partially Paid': 0 },
      sales_by_status: { New: '0.10', Confirmed: '0.00', Delivered: '0.00', 'Partially Paid': '0.00' },
      daily_by_status: [
        {
          date: '2026-09-24',
          counts: { New: 1, Confirmed: 0, Delivered: 0, 'Partially Paid': 0 },
          sales: { New: '0.10', Confirmed: '0.00', Delivered: '0.00', 'Partially Paid': '0.00' },
        },
        {
          date: '2026-09-25',
          counts: { New: 1, Confirmed: 0, Delivered: 0, 'Partially Paid': 0 },
          sales: { New: '0.20', Confirmed: '0.00', Delivered: '0.00', 'Partially Paid': '0.00' },
        },
      ],
    };
    const result = computeFilteredCounts(point, ['New'], '7d', window);
    expect(result.filteredOrders).toBe(2);
    expect(result.salesCents).toBe(30);
  });
});

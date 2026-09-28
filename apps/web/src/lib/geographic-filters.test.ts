import { periodToRange, ELIGIBLE_STATUSES, expandSemua, normalizeStatuses } from './geographic-filters';

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

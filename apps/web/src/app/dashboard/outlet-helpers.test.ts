import { toOutletStatus, periodWindow, topFavorites } from './outlet-helpers';

describe('Outlet Helpers (T2)', () => {
  describe('toOutletStatus', () => {
    it('maps the six canonical statuses and normalizes Canceled', () => {
      expect(toOutletStatus('New')).toMatchObject({ label: 'Menunggu konfirmasi admin', hint: 'Tidak ada aksi' });
      expect(toOutletStatus('Confirmed').label).toBe('Dikonfirmasi');
      expect(toOutletStatus('Delivered')).toMatchObject({ label: 'Terkirim, periksa invoice', ctaKey: 'view_invoice' });
      expect(toOutletStatus('Partially Paid').label).toBe('Dibayar sebagian');
      expect(toOutletStatus('Paid').label).toBe('Lunas');
      expect(toOutletStatus('Cancelled').label).toBe('Dibatalkan');
      expect(toOutletStatus('Canceled')).toEqual(toOutletStatus('Cancelled'));
    });

    it('escapes unknown statuses, truncates to 20 characters plus ellipsis, and provides a tooltip', () => {
      const fallback = toOutletStatus('Super-Pending-Review!!!');
      expect(fallback).toMatchObject({ label: 'Super-Pending-Review…', tooltip: 'Lainnya', isFallback: true });

      const escaped = toOutletStatus('<script>alert(1)</script>');
      expect(escaped.isFallback).toBe(true);
      expect(escaped.label).not.toContain('<script>');
      expect(escaped.label.endsWith('…')).toBe(true);

      expect(toOutletStatus('')).toMatchObject({ label: 'Lainnya', tooltip: 'Lainnya', isFallback: true });
    });
  });

  describe('periodWindow', () => {
    it.each([
      [7, '2026-05-07', '2026-05-06T17:00:00.000Z'],
      [30, '2026-04-14', '2026-04-13T17:00:00.000Z'],
      [90, '2026-02-13', '2026-02-12T17:00:00.000Z'],
    ] as const)('computes %i full Jakarta calendar dates as exact UTC instants', (days, start, instant) => {
      const now = new Date('2026-05-13T23:55:00+07:00');
      const w = periodWindow(days, now);
      expect(w).toEqual({
        start, end: '2026-05-13',
        startInstantUTC: instant,
        endInstantUTC: '2026-05-13T16:55:00.000Z',
        label: `${days} hari terakhir`,
      });
    });

    it('excludes 23:59 previous-day, includes 00:00 start, and excludes instants after now', () => {
      const w = periodWindow(7, new Date('2026-05-13T23:55:00+07:00'));
      const inWindow = (createdAt: string) =>
        createdAt >= w.startInstantUTC && createdAt <= w.endInstantUTC;
      expect(inWindow('2026-05-06T16:59:59.000Z')).toBe(false);
      expect(inWindow('2026-05-06T17:00:00.000Z')).toBe(true);
      expect(inWindow('2026-05-13T16:55:00.000Z')).toBe(true);
      expect(inWindow('2026-05-13T16:55:00.001Z')).toBe(false);
    });
  });

  describe('topFavorites', () => {
    it('aggregates top-5 favorites correctly with quantity sums, most recent product name, fallback, and tie-break', () => {
      const orders = [
        {
          created_at: '2026-05-01T10:00:00Z',
          status: 'Paid',
          items: [
            { product_id: 1, product_name: 'A', quantity: 5 },
            { product_id: 2, product_name: 'B', quantity: 6 },
            { product_id: null, product_name: 'NullID', quantity: 10 },
          ],
        },
        {
          created_at: '2026-05-05T10:00:00Z',
          status: 'New',
          items: [
            { product_id: 1, product_name: 'A-renamed', quantity: 7 }, // total qty 12, most recent name
            { product_id: 3, product_name: null, quantity: 2 }, // fallback to Produk #3
            { product_id: 4, product_name: 'Z-Product', quantity: 2 }, // tie quantity with product 3, sort name asc -> Produk #3 then Z-Product
          ],
        },
      ];

      const top = topFavorites(orders as any, 5);
      expect(top).toEqual([
        { product_id: 1, displayName: 'A-renamed', totalQty: 12 },
        { product_id: 2, displayName: 'B', totalQty: 6 },
        { product_id: 3, displayName: 'Produk #3', totalQty: 2 },
        { product_id: 4, displayName: 'Z-Product', totalQty: 2 },
      ]);
    });
  });
});

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
    it('computes 7 calendar dates inclusive in Asia/Jakarta timezone and asserts startUTC/endUTC', () => {
      const now = new Date('2026-05-13T23:55:00+07:00');
      const w = periodWindow(7, now);
      expect(w.start).toBe('2026-05-07');
      expect(w.end).toBe('2026-05-13');
      expect(w.startUTC).toBe('2026-05-06');
      expect(w.endUTC).toBe('2026-05-13');
      expect(w.label).toBe('7 hari terakhir');
    });

    it('computes 90 calendar dates inclusive (start = today - 89) and asserts startUTC/endUTC', () => {
      const now = new Date('2026-05-13T23:55:00+07:00');
      const w = periodWindow(90, now);
      expect(w.start).toBe('2026-02-13');
      expect(w.end).toBe('2026-05-13');
      expect(w.startUTC).toBe('2026-02-13');
      expect(w.endUTC).toBe('2026-05-13');
      expect(w.label).toBe('90 hari terakhir');
    });

    // TODO: Add timezone boundary test — verify startUTC/endUTC round-trip matches
    // backend inclusive range for edge cases (e.g., now at UTC midnight, DST transitions).

    it('excludes orders at 23:59 Jakarta on the day before window start and includes at 00:00 on start day', () => {
      const now = new Date('2026-05-13T23:55:00+07:00');
      const w = periodWindow(7, now);

      const isInWindow = (createdAt: string) => {
        const dateObj = new Date(createdAt);
        const jakartaDateStr = dateObj.toLocaleDateString('sv-SE', { timeZone: 'Asia/Jakarta' });
        return jakartaDateStr >= w.start && jakartaDateStr <= w.end;
      };

      // 2026-05-06T16:59:59Z is 2026-05-06 23:59:59 Jakarta (day before start) -> excluded
      expect(isInWindow('2026-05-06T16:59:59Z')).toBe(false);
      // 2026-05-06T17:00:00Z is 2026-05-07 00:00:00 Jakarta (start day) -> included
      expect(isInWindow('2026-05-06T17:00:00Z')).toBe(true);
      // 2026-05-13T10:00:00Z is 2026-05-13 17:00 Jakarta (same day, before now) -> included
      expect(isInWindow('2026-05-13T10:00:00Z')).toBe(true);
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

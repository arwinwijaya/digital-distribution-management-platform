import { toOutletStatus } from './outlet-helpers';

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

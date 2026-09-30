import '@testing-library/jest-dom';
import { formatRupiahDecimal } from '@/app/admin/ai-experiments/page';

describe('formatRupiahDecimal (T15 cycle 2 — decimal-safe large Rupiah)', () => {
  it('renders 12345678901234.56 as Rp 12.345.678.901.234,56 without floating-point rounding', () => {
    expect(formatRupiahDecimal('12345678901234.56')).toBe('Rp 12.345.678.901.234,56');
  });

  it('zero baseline is not treated as 1.00', () => {
    expect(formatRupiahDecimal('0.00')).toBe('Rp 0,00');
  });

  it('keeps exactly 2 decimal places with id-ID separators', () => {
    expect(formatRupiahDecimal('1000.00')).toBe('Rp 1.000,00');
  });
});

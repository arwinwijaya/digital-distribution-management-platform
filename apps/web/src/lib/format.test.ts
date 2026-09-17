import { formatCount, formatDecimal, formatPercentage, formatRupiah, parseMoney } from '@/lib/format';

describe('shared number/money formatters', () => {
  describe('parseMoney', () => {
    it('parses a fixed-2-decimal string', () => {
      expect(parseMoney('150000.00')).toBe(150000);
    });

    it('strips a currency prefix and whitespace', () => {
      expect(parseMoney('Rp 150000.00')).toBe(150000);
    });

    it('returns 0 for null/undefined/non-numeric input', () => {
      expect(parseMoney(null)).toBe(0);
      expect(parseMoney(undefined)).toBe(0);
      expect(parseMoney('bukan angka')).toBe(0);
    });

    it('passes numbers through', () => {
      expect(parseMoney(0.85)).toBe(0.85);
    });
  });

  describe('formatRupiah', () => {
    it('formats a money string with id-ID separators and an Rp prefix', () => {
      expect(formatRupiah('150000.00')).toBe('Rp 150.000');
    });

    it('formats large amounts with full grouping', () => {
      expect(formatRupiah('365228000.00')).toBe('Rp 365.228.000');
    });

    it('formats null as Rp 0', () => {
      expect(formatRupiah(null)).toBe('Rp 0');
    });
  });

  describe('formatPercentage', () => {
    it('renders 2 decimals with a % suffix', () => {
      expect(formatPercentage(0.85)).toBe('0.85%');
      expect(formatPercentage('100.00')).toBe('100.00%');
    });
  });

  describe('formatCount', () => {
    it('groups integers with id-ID separators', () => {
      expect(formatCount(1248)).toBe('1.248');
      expect(formatCount(157)).toBe('157');
    });
  });

  describe('formatDecimal', () => {
    it('keeps up to 2 fraction digits with id-ID separators', () => {
      expect(formatDecimal(1.6667)).toBe('1,67');
      expect(formatDecimal(2)).toBe('2');
      expect(formatDecimal(0.83)).toBe('0,83');
    });
  });
});

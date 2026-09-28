import {
  normalizeStock,
  classifyStockHealth,
  stockHealthLabel,
  STOCK_UNIT_LABEL,
  STOCK_VALUE_LABEL,
  STOCK_UNIT_NOTE,
} from './product-clarity';

describe('product clarity stock helpers', () => {
  it.each([
    [null, 0, 'out', 'Habis'],
    [undefined, 0, 'out', 'Habis'],
    [-2, -2, 'out', 'Habis'],
    [0, 0, 'out', 'Habis'],
    [0.9, 0, 'out', 'Habis'],
    [1, 1, 'low', 'Rendah'],
    [10, 10, 'low', 'Rendah'],
    [10.9, 10, 'low', 'Rendah'],
    [11, 11, 'ok', 'Aman'],
  ])('stock %p → normalized %s, health %s, label %s', (value, normalized, health, label) => {
    expect(normalizeStock(value as number | null | undefined)).toBe(normalized);
    expect(classifyStockHealth(value as number | null | undefined)).toBe(health);
    expect(stockHealthLabel(health as 'out' | 'low' | 'ok')).toBe(label);
  });

  it('exports explicit stock labels and static unit note', () => {
    expect(STOCK_UNIT_LABEL).toBe('Stok (unit)');
    expect(STOCK_VALUE_LABEL).toBe('Nilai stok');
    expect(STOCK_UNIT_NOTE).toBe('Satuan belum terdefinisi di sistem');
  });
});

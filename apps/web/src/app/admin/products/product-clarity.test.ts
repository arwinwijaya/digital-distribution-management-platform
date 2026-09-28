import {
  normalizeStock,
  classifyStockHealth,
  stockHealthLabel,
  STOCK_UNIT_LABEL,
  STOCK_VALUE_LABEL,
  STOCK_UNIT_NOTE,
  deriveDisplayStatus,
  deriveProductDetailFacts,
  DisplayStatus,
  categoryDisplay,
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

describe('product clarity status helpers', () => {
  const activeSupplier = { id: 1, name: 'PT Sumber Rejeki', subscription_status: 'active' };
  const inactiveSupplier = { id: 2, name: 'CV Lama Jaya', subscription_status: 'expired' };

  it.each([
    ['active product / active supplier', { is_active: true, supplier: activeSupplier }, 'Aktif'],
    ['active product / non-active supplier', { is_active: true, supplier: inactiveSupplier }, 'Tidak bisa dibeli'],
    ['active product / orphan supplier', { is_active: true, supplier: null }, 'Aktif'],
    ['inactive product / active supplier', { is_active: false, supplier: activeSupplier }, 'Nonaktif'],
    ['inactive product / non-active supplier', { is_active: false, supplier: inactiveSupplier }, 'Nonaktif'],
    ['active product / null supplier', { is_active: true, supplier: undefined }, 'Aktif'],
  ])('%s → %s', (_label, product, expected) => {
    expect(deriveDisplayStatus(product as never)).toBe(expected as DisplayStatus);
  });

  it('never marks orphan/null supplier as Tidak bisa dibeli even when is_active is absent', () => {
    expect(deriveDisplayStatus({ supplier: null } as never)).toBe('Aktif');
    expect(deriveDisplayStatus({ supplier_id: 9, supplier: null } as never)).toBe('Aktif');
  });

  it('is_active wins over supplier status', () => {
    expect(deriveDisplayStatus({ is_active: false, supplier: inactiveSupplier } as never)).toBe('Nonaktif');
  });

  it('retains both product and supplier facts for the detail panel', () => {
    expect(deriveProductDetailFacts({ is_active: false, supplier: inactiveSupplier } as never)).toEqual({
      status: 'Nonaktif',
      productActive: false,
      supplierName: 'CV Lama Jaya',
      supplierStatus: 'Tidak aktif',
    });
    expect(deriveProductDetailFacts({ is_active: true, supplier: null } as never)).toEqual({
      status: 'Aktif',
      productActive: true,
      supplierName: '—',
      supplierStatus: '—',
    });
  });

  it('renders category trim-only with em dash fallback', () => {
    expect(categoryDisplay('  Minuman  ')).toBe('Minuman');
    expect(categoryDisplay('')).toBe('—');
    expect(categoryDisplay('   ')).toBe('—');
    expect(categoryDisplay(null)).toBe('—');
    expect(categoryDisplay(undefined)).toBe('—');
  });
});

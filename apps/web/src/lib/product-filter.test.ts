import { filterProducts } from './product-filter';

const rows = [
  { name: 'Beras Premium', price: '20000.00' },
  { name: 'Minyak Goreng', price: '25000.00' },
  { name: 'Gula Pasir', price: '15000.00' },
  { name: 'Kopi Bubuk', price: 75000 },
];

describe('filterProducts', () => {
  it('returns all rows for empty filters', () => {
    expect(filterProducts(rows)).toHaveLength(4);
    expect(filterProducts(rows, {})).toHaveLength(4);
  });

  it('matches name case-insensitively as a substring', () => {
    expect(filterProducts(rows, { name: 'beras' }).map((r) => r.name)).toEqual(['Beras Premium']);
    expect(filterProducts(rows, { name: 'PREM' }).map((r) => r.name)).toEqual(['Beras Premium']);
    expect(filterProducts(rows, { name: 'o' }).map((r) => r.name)).toEqual(['Minyak Goreng', 'Kopi Bubuk']);
  });

  it('treats a blank/whitespace name as unbounded', () => {
    expect(filterProducts(rows, { name: '   ' })).toHaveLength(4);
  });

  it('applies a minimum price only (inclusive)', () => {
    expect(filterProducts(rows, { minPrice: '20000' }).map((r) => r.name)).toEqual([
      'Beras Premium',
      'Minyak Goreng',
      'Kopi Bubuk',
    ]);
  });

  it('applies a maximum price only (inclusive)', () => {
    expect(filterProducts(rows, { maxPrice: '20000' }).map((r) => r.name)).toEqual([
      'Beras Premium',
      'Gula Pasir',
    ]);
  });

  it('applies a min + max range together', () => {
    expect(filterProducts(rows, { minPrice: '20000', maxPrice: '25000' }).map((r) => r.name)).toEqual([
      'Beras Premium',
      'Minyak Goreng',
    ]);
  });

  it('ignores blank/empty price bounds', () => {
    expect(filterProducts(rows, { minPrice: '', maxPrice: '' })).toHaveLength(4);
  });

  it('ignores negative bounds', () => {
    expect(filterProducts(rows, { minPrice: '-5000', maxPrice: '-1' })).toHaveLength(4);
  });

  it('ignores non-numeric bounds', () => {
    expect(filterProducts(rows, { minPrice: 'abc', maxPrice: 'NaN' })).toHaveLength(4);
  });

  it('compares numeric price strings and numbers equally', () => {
    expect(filterProducts(rows, { minPrice: 20000, maxPrice: 25000 }).map((r) => r.name)).toEqual([
      'Beras Premium',
      'Minyak Goreng',
    ]);
  });
});

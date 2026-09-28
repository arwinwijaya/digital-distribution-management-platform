import React from 'react';
import '@testing-library/jest-dom';
import { render, screen } from '@testing-library/react';

import ProductRowDetail from './ProductRowDetail';
import type { AdminProduct } from './api';

const fixture: AdminProduct = {
  id: 3,
  name: 'Kopi Kapal',
  sku: 'SKU-001',
  price: '15000.00',
  stock_quantity: 40,
  category: 'Minuman',
  description: 'Kopi robusta sangrai premium.',
  is_active: true,
  supplier: { id: 1, name: 'PT Segar', subscription_status: 'active' },
  created_at: '2026-09-05T08:00:00Z',
  updated_at: '2026-09-10T10:00:00Z',
};

/** Text of the value span next to a fact label. */
function factValue(label: string): string {
  const labelEl = screen.getByText(label);
  return labelEl.parentElement?.textContent ?? '';
}

describe('ProductRowDetail', () => {
  it('renders identity, supplier/product facts, value, unit note and timestamps', () => {
    render(<ProductRowDetail product={fixture} detailId="product-detail-3" />);

    // Identity facts.
    expect(screen.getByText('Kopi Kapal')).toBeInTheDocument();
    expect(screen.getByText('SKU-001')).toBeInTheDocument();
    expect(screen.getByText('Minuman')).toBeInTheDocument();
    expect(screen.getByText('Kopi robusta sangrai premium.')).toBeInTheDocument();

    // Supplier + product-status facts.
    expect(factValue('Supplier')).toContain('PT Segar');
    expect(factValue('Status supplier')).toContain('Aktif');
    expect(factValue('Status produk')).toContain('Aktif');

    // Stock value + static unit note, no invented UoM.
    expect(factValue('Nilai stok')).toMatch(/Nilai stok\s*Rp 600\.000/);
    expect(screen.getByText('Satuan belum terdefinisi di sistem')).toBeInTheDocument();
    const body = document.body.textContent ?? '';
    expect(body).not.toMatch(/\b(kg|gram|pcs|liter|ml)\b/i);

    // Timestamps render as formatted dates, not raw ISO.
    expect(screen.queryByText('2026-09-05T08:00:00Z')).not.toBeInTheDocument();
    expect(screen.queryByText('2026-09-10T10:00:00Z')).not.toBeInTheDocument();
    expect(body).toMatch(/2026/);
  });

  it('explains an inactive supplier even when the product is inactive', () => {
    render(
      <ProductRowDetail
        product={{
          ...fixture,
          is_active: false,
          supplier: { id: 2, name: 'CV Lama', subscription_status: 'expired' },
        }}
        detailId="product-detail-3"
      />,
    );

    expect(factValue('Status produk')).toContain('Nonaktif');
    expect(factValue('Supplier')).toContain('CV Lama');
    expect(factValue('Status supplier')).toContain('Tidak aktif');
  });

  it('shows em dash for missing category/description/supplier and Rp 0 for null stock', () => {
    render(
      <ProductRowDetail
        product={{
          ...fixture,
          category: null,
          description: null,
          supplier: null,
          stock_quantity: null,
        } as AdminProduct}
        detailId="product-detail-3"
      />,
    );

    expect(factValue('Nilai stok')).toMatch(/Nilai stok\s*Rp 0/);
    expect(factValue('Kategori')).toContain('\u2014');
    expect(factValue('Deskripsi')).toContain('\u2014');
    expect(factValue('Supplier')).toContain('\u2014');
    expect(factValue('Status supplier')).toContain('\u2014');
  });
});

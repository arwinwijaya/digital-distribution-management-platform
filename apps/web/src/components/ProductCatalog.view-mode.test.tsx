import React from 'react';
import '@testing-library/jest-dom';
import { render, screen, waitFor, fireEvent } from '@testing-library/react';

jest.mock('@/lib/api', () => ({
  apiUrl: (p: string) => `http://localhost:8000/api${p}`,
  authHeaders: (token: string) => ({ Authorization: `Bearer ${token}` }),
  getStoredToken: () => 'test-token',
}));

import { useDummyStore } from '@/dummy/store';
import { installDummy } from '@/dummy/install';
import ProductCatalog from '@/components/ProductCatalog';

const productsResponse = {
  status: 'success',
  data: [
    { id: 1, name: 'Beras Premium', description: 'Beras', price: '20000.00', stock_quantity: 50, category: 'pokok', is_active: true },
    { id: 2, name: 'Minyak Goreng', description: 'Minyak', price: '25000.00', stock_quantity: 30, category: 'pokok', is_active: true },
    { id: 3, name: 'Kopi Bubuk', description: 'Kopi', price: '75000.00', stock_quantity: 0, category: 'minuman', is_active: true },
  ],
};

describe('ProductCatalog — view mode + product filters', () => {
  let originalFetch: typeof fetch | undefined;

  beforeEach(() => {
    localStorage.clear();
    useDummyStore.getState().reset();
    installDummy();
    originalFetch = (globalThis as unknown as { fetch?: typeof fetch }).fetch;
    (globalThis as unknown as { fetch: unknown }).fetch = jest.fn(async (url: unknown) => {
      const urlString = String(url);
      if (urlString.includes('/products')) {
        return { ok: true, status: 200, json: async () => productsResponse } as Response;
      }
      return { ok: false, status: 404, json: async () => ({ status: 'error', message: 'not found' }) } as Response;
    });
  });

  afterEach(() => {
    if (originalFetch) {
      (globalThis as unknown as { fetch: unknown }).fetch = originalFetch;
    } else {
      delete (globalThis as unknown as { fetch?: unknown }).fetch;
    }
  });

  const renderCatalog = async () => {
    render(<ProductCatalog token="test-token" />);
    await waitFor(() => expect(screen.getByText('Beras Premium')).toBeInTheDocument());
  };

  it('defaults to the card grid (no table) when no preference is stored', async () => {
    await renderCatalog();
    expect(screen.queryByRole('table')).not.toBeInTheDocument();
  });

  it('switches to a table when the "Tabel" toggle is clicked', async () => {
    await renderCatalog();
    fireEvent.click(screen.getByRole('button', { name: /tabel/i }));
    expect(screen.getByRole('table')).toBeInTheDocument();
    expect(screen.getByRole('columnheader', { name: /kategori/i })).toBeInTheDocument();
  });

  it('rehydrates table mode from localStorage', async () => {
    localStorage.setItem('ui:view-mode', 'table');
    await renderCatalog();
    await waitFor(() => expect(screen.getByRole('table')).toBeInTheDocument());
  });

  it('filters by product name client-side (no refetch)', async () => {
    await renderCatalog();
    const fetchMock = (globalThis as unknown as { fetch: jest.Mock }).fetch;
    const callsBefore = fetchMock.mock.calls.length;
    fireEvent.change(screen.getByLabelText('Cari produk'), { target: { value: 'kopi' } });
    expect(screen.getByText('Kopi Bubuk')).toBeInTheDocument();
    expect(screen.queryByText('Beras Premium')).not.toBeInTheDocument();
    expect(fetchMock.mock.calls.length).toBe(callsBefore);
  });

  it('filters by price range (min + max)', async () => {
    await renderCatalog();
    fireEvent.change(screen.getByLabelText('Harga minimum'), { target: { value: '22000' } });
    fireEvent.change(screen.getByLabelText('Harga maksimum'), { target: { value: '30000' } });
    expect(screen.getByText('Minyak Goreng')).toBeInTheDocument();
    expect(screen.queryByText('Beras Premium')).not.toBeInTheDocument();
    expect(screen.queryByText('Kopi Bubuk')).not.toBeInTheDocument();
  });

  it('shows a filtered-empty state when no product matches', async () => {
    await renderCatalog();
    fireEvent.change(screen.getByLabelText('Cari produk'), { target: { value: 'zzz' } });
    expect(screen.getByText('Produk tidak ditemukan')).toBeInTheDocument();
  });
});

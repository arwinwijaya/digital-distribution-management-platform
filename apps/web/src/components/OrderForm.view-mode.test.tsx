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
import OrderForm from '@/components/OrderForm';

const productsResponse = {
  status: 'success',
  data: [
    { id: 1, name: 'Beras Premium', price: '20000.00', stock_quantity: 50, is_active: true },
    { id: 2, name: 'Minyak Goreng', price: '25000.00', stock_quantity: 30, is_active: true },
    { id: 3, name: 'Kopi Bubuk', price: '75000.00', stock_quantity: 0, is_active: true },
  ],
};

describe('OrderForm — view mode + product filters', () => {
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

  const renderForm = async () => {
    render(<OrderForm token="test-token" />);
    await waitFor(() => expect(screen.getByText('Beras Premium')).toBeInTheDocument());
  };

  it('defaults to the card grid (no table) when no preference is stored', async () => {
    await renderForm();
    expect(screen.queryByRole('table')).not.toBeInTheDocument();
    expect(screen.getAllByLabelText(/^Jumlah /)).toHaveLength(3);
  });

  it('switches to a table when the "Tabel" toggle is clicked', async () => {
    await renderForm();
    fireEvent.click(screen.getByRole('button', { name: /tabel/i }));
    expect(screen.getByRole('table')).toBeInTheDocument();
    expect(screen.getAllByLabelText(/^Jumlah /)).toHaveLength(3);
  });

  it('rehydrates table mode from localStorage', async () => {
    localStorage.setItem('ui:view-mode', 'table');
    await renderForm();
    await waitFor(() => expect(screen.getByRole('table')).toBeInTheDocument());
  });

  it('keeps the quantity input reachable by its aria-label in table mode', async () => {
    localStorage.setItem('ui:view-mode', 'table');
    await renderForm();
    await waitFor(() => expect(screen.getByRole('table')).toBeInTheDocument());
    const input = screen.getByLabelText('Jumlah Beras Premium') as HTMLInputElement;
    expect(input).toHaveAttribute('max', '50');
    fireEvent.change(input, { target: { value: '2' } });
    expect(input).toHaveValue(2);
  });

  it('filters by product name', async () => {
    await renderForm();
    fireEvent.change(screen.getByLabelText('Cari produk'), { target: { value: 'beras' } });
    expect(screen.getByText('Beras Premium')).toBeInTheDocument();
    expect(screen.queryByText('Minyak Goreng')).not.toBeInTheDocument();
    expect(screen.getByText('1 dari 3 produk')).toBeInTheDocument();
  });

  it('filters by price range (min + max)', async () => {
    await renderForm();
    fireEvent.change(screen.getByLabelText('Harga minimum'), { target: { value: '22000' } });
    fireEvent.change(screen.getByLabelText('Harga maksimum'), { target: { value: '30000' } });
    expect(screen.getByText('Minyak Goreng')).toBeInTheDocument();
    expect(screen.queryByText('Beras Premium')).not.toBeInTheDocument();
    expect(screen.queryByText('Kopi Bubuk')).not.toBeInTheDocument();
  });

  it('shows a filtered-empty state when no product matches', async () => {
    await renderForm();
    fireEvent.change(screen.getByLabelText('Harga minimum'), { target: { value: '1000000' } });
    expect(screen.getByText('Produk tidak ditemukan')).toBeInTheDocument();
  });
});

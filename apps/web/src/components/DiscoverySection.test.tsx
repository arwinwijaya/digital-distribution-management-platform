import '@testing-library/jest-dom';
import { render, screen, fireEvent } from '@testing-library/react';
import DiscoverySection from './DiscoverySection';
import type { DiscoveryItem } from '@/hooks/useOutletDiscovery';

const items: DiscoveryItem[] = [
  {
    product_id: 42,
    product_name: 'Kopi Arabika',
    reason: 'Populer di kategori Minuman',
    price: '15000',
    stock_quantity: 10,
    is_active: true,
  },
];

it('DiscoverySection_renders_items_with_reason_add_dismiss', () => {
  const onAdd = jest.fn();
  const onDismiss = jest.fn();

  render(<DiscoverySection items={items} onAdd={onAdd} onDismiss={onDismiss} />);

  expect(screen.getByText('Kopi Arabika')).toBeInTheDocument();
  expect(screen.getByText('Populer di kategori Minuman')).toBeInTheDocument();
  expect(screen.getByText(/15\.000/)).toBeInTheDocument();
  expect(screen.getByText(/Stok 10/)).toBeInTheDocument();
  expect(screen.getByRole('button', { name: /Tambah/i })).toBeInTheDocument();
  expect(screen.getByRole('button', { name: /Abaikan/i })).toBeInTheDocument();
});

it('DiscoverySection_offline_shows_message', () => {
  render(<DiscoverySection items={items} onAdd={jest.fn()} onDismiss={jest.fn()} offline />);

  expect(screen.getByText(/tersedia saat online/i)).toBeInTheDocument();
  expect(screen.queryByText('Kopi Arabika')).not.toBeInTheDocument();
  expect(screen.queryByRole('button', { name: /Tambah/i })).not.toBeInTheDocument();
});

it('DiscoverySection_fetch_error_shows_retry', () => {
  const onRetry = jest.fn();
  render(<DiscoverySection items={[]} onAdd={jest.fn()} onDismiss={jest.fn()} error="Failed to load" onRetry={onRetry} />);

  expect(screen.getByText(/Gagal memuat rekomendasi/i)).toBeInTheDocument();
  expect(screen.getByRole('button', { name: /Coba lagi/i })).toBeInTheDocument();
  fireEvent.click(screen.getByRole('button', { name: /Coba lagi/i }));
  expect(onRetry).toHaveBeenCalledTimes(1);
});

it('DiscoverySection_empty_state_shows_message', () => {
  render(<DiscoverySection items={[]} onAdd={jest.fn()} onDismiss={jest.fn()} offline={false} error={null} />);

  expect(screen.getByText(/Belum ada rekomendasi baru saat ini/i)).toBeInTheDocument();
  expect(screen.queryByRole('button', { name: /Tambah/i })).not.toBeInTheDocument();
});

const stockZeroItems: DiscoveryItem[] = [
  {
    product_id: 43,
    product_name: 'Teh Hijau',
    reason: 'Populer di platform',
    price: '8000',
    stock_quantity: 0,
    is_active: true,
  },
];

it('DiscoverySection_disables_add_when_stock_zero', () => {
  const onAdd = jest.fn();
  const onDismiss = jest.fn();

  render(<DiscoverySection items={stockZeroItems} onAdd={onAdd} onDismiss={onDismiss} />);

  expect(screen.getByText('Teh Hijau')).toBeInTheDocument();
  const addBtn = screen.getByRole('button', { name: /Tambah/i });
  expect(addBtn).toBeDisabled();
  expect(screen.getByText(/Stok habis/)).toBeInTheDocument();
});

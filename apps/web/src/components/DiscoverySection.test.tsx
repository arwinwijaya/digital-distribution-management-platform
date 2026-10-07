import { render, screen } from '@testing-library/react';
import DiscoverySection from './DiscoverySection';
import type { DiscoveryItem } from '@/hooks/useOutletDiscovery';

it('DiscoverySection_renders_items_with_reason_and_add_button', () => {
  const items: DiscoveryItem[] = [
    { product_id: 42, product_name: 'Kopi Arabika', reason: 'Populer di sekitar Anda' },
  ];
  const onAdd = jest.fn();

  render(<DiscoverySection items={items} onAdd={onAdd} />);

  expect(screen.getByText('Kopi Arabika')).toBeInTheDocument();
  expect(screen.getByText('Populer di sekitar Anda')).toBeInTheDocument();
  expect(screen.getByRole('button', { name: /Tambah/i })).toBeInTheDocument();
});

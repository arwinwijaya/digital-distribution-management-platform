import { renderHook, waitFor } from '@testing-library/react';
import type { Product } from '@/components/OrderForm';
import { useOutletDiscovery } from './useOutletDiscovery';

const product: Product = {
  id: 42,
  name: 'Kopi Arabika',
  price: '15000',
  stock_quantity: 10,
  is_active: true,
};

it('useOutletDiscovery_fetches_discovery_when_online', async () => {
  const item = { product_id: 42, product_name: 'Kopi Arabika', reason: 'Populer di sekitar Anda' };
  const fetchMock = jest.fn().mockResolvedValue({
    ok: true,
    json: async () => ({ discovery: [item] }),
  });
  const originalFetch = global.fetch;
  global.fetch = fetchMock as typeof fetch;

  try {
    const { result } = renderHook(() => useOutletDiscovery('outlet-token', [product], true));

    await waitFor(() => expect(fetchMock).toHaveBeenCalledWith(
      expect.stringContaining('/api/ai/discovery'),
      expect.anything(),
    ));
    await waitFor(() => expect(result.current.discovery).toEqual([item]));
  } finally {
    global.fetch = originalFetch;
  }
});

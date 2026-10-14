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

it('useOutletDiscovery_adds_item_with_current_catalog_price', async () => {
  const catalogProduct: Product = { id: 3, name: 'P3', price: '12000', stock_quantity: 5, is_active: true };
  const onAdd = jest.fn();
  const fetchMock = jest.fn().mockResolvedValue({
    ok: true,
    json: async () => ({ data: { recommendations: [{ product_id: 3, name: 'P3', reason: 'Populer di platform' }] } }),
  });
  const originalFetch = global.fetch;
  global.fetch = fetchMock as typeof fetch;

  try {
    const { result } = renderHook(() => useOutletDiscovery('token', [catalogProduct], {
      online: true,
      onAdd,
    }));
    await waitFor(() => expect(result.current.discovery).toHaveLength(1));
    result.current.addToCart(3);
    expect(onAdd).toHaveBeenCalledWith(3, '12000');
  } finally {
    global.fetch = originalFetch;
  }
});

it('useOutletDiscovery_hides_item_when_stock_zero_in_catalog', async () => {
  const outOfStock: Product = { id: 3, name: 'P3', price: '12000', stock_quantity: 0, is_active: true };
  const fetchMock = jest.fn().mockResolvedValue({
    ok: true,
    json: async () => ({ data: { recommendations: [{ product_id: 3, name: 'P3', reason: 'Populer di platform' }] } }),
  });
  const originalFetch = global.fetch;
  global.fetch = fetchMock as typeof fetch;
  try {
    const { result } = renderHook(() => useOutletDiscovery('token', [outOfStock], { online: true }));
    await waitFor(() => expect(result.current.discovery).toHaveLength(1));
    expect(result.current.discovery[0]).toMatchObject({
      product_id: 3,
      stock_quantity: 0,
      is_active: true,
      price: '12000',
    });
    expect(result.current.discovery[0].available).toBe(false);
  } finally {
    global.fetch = originalFetch;
  }
});

it('useOutletDiscovery_dismiss_session_only', async () => {
  const catalog: Product[] = [
    { id: 3, name: 'P3', price: '12000', stock_quantity: 5, is_active: true },
    { id: 4, name: 'P4', price: '9000', stock_quantity: 5, is_active: true },
  ];
  const payload = {
    ok: true,
    json: async () => ({
      data: { recommendations: [
        { product_id: 3, name: 'P3', reason: 'Populer di platform' },
        { product_id: 4, name: 'P4', reason: 'Populer di platform' },
      ] },
    }),
  };
  const fetchMock = jest.fn().mockResolvedValue(payload);
  const originalFetch = global.fetch;
  global.fetch = fetchMock as typeof fetch;
  const dismissed = new Set<number>();
  try {
    const { result } = renderHook(() =>
      useOutletDiscovery('token', catalog, { online: true, dismissed }));
    await waitFor(() => expect(result.current.discovery).toHaveLength(2));
    result.current.dismiss(3);
    await waitFor(() => expect(result.current.discovery.map((entry) => entry.product_id))
      .toEqual([4]));
    // Session-only: a fresh hook instance (page reload) shows the item again.
    const reloaded = renderHook(() =>
      useOutletDiscovery('token', catalog, { online: true, dismissed: new Set<number>() }));
    await waitFor(() => expect(reloaded.result.current.discovery.map((entry) => entry.product_id))
      .toEqual(expect.arrayContaining([3])));
  } finally {
    global.fetch = originalFetch;
  }
});

it('useOutletDiscovery_sends_tracking_events_fire_and_forget', async () => {
  const catalog: Product[] = [
    { id: 3, name: 'P3', price: '12000', stock_quantity: 5, is_active: true },
    { id: 4, name: 'P4', price: '9000', stock_quantity: 5, is_active: true },
  ];
  const fetchMock = jest.fn().mockImplementation((url: string) => {
    if (String(url).includes('/ai/discovery/events')) {
      return Promise.resolve({ ok: true, json: async () => ({}) });
    }
    return Promise.resolve({
      ok: true,
      json: async () => ({
        data: { recommendations: [
          { product_id: 3, name: 'P3', reason: 'Populer di platform' },
          { product_id: 4, name: 'P4', reason: 'Populer di platform' },
        ] },
      }),
    });
  });
  const originalFetch = global.fetch;
  global.fetch = fetchMock as typeof fetch;
  const onAdd = jest.fn();
  try {
    const { result } = renderHook(() =>
      useOutletDiscovery('token', catalog, { online: true, onAdd }));
    await waitFor(() => expect(result.current.discovery).toHaveLength(2));

    const eventCalls = () => fetchMock.mock.calls.filter(([url]) =>
      String(url).includes('/ai/discovery/events'));

    await waitFor(() => {
      const displayed = eventCalls().filter(([, init]) =>
        JSON.parse(String((init as RequestInit).body)).event_type === 'displayed');
      expect(displayed).toHaveLength(2);
    });

    result.current.addToCart(3);

    await waitFor(() => {
      const types = eventCalls().map(([, init]) =>
        JSON.parse(String((init as RequestInit).body)).event_type);
      expect(types).toEqual(expect.arrayContaining(['clicked', 'cart']));
    });

    const firstEvent = eventCalls()[0][1] as RequestInit;
    const body = JSON.parse(String(firstEvent.body));
    expect(body).toMatchObject({ product_id: expect.any(Number), event_type: expect.any(String) });
    expect(typeof body.event_uuid).toBe('string');
    expect(body.event_uuid.length).toBeGreaterThan(0);
    expect(typeof body.occurred_at).toBe('string');
  } finally {
    global.fetch = originalFetch;
  }
});

it('useOutletDiscovery_tracking_failure_is_swallowed', async () => {
  const catalog: Product[] = [{ id: 3, name: 'P3', price: '12000', stock_quantity: 5, is_active: true }];
  const warnSpy = jest.spyOn(console, 'warn').mockImplementation(() => {});
  const fetchMock = jest.fn().mockImplementation((url: string) => {
    if (String(url).includes('/ai/discovery/events')) {
      return Promise.reject(new Error('tracking down'));
    }
    return Promise.resolve({
      ok: true,
      json: async () => ({ data: { recommendations: [{ product_id: 3, name: 'P3', reason: 'Populer di platform' }] } }),
    });
  });
  const originalFetch = global.fetch;
  global.fetch = fetchMock as typeof fetch;
  try {
    const { result } = renderHook(() =>
      useOutletDiscovery('token', catalog, { online: true }));
    await waitFor(() => expect(result.current.discovery).toHaveLength(1));
    result.current.addToCart(3);
    await waitFor(() => expect(warnSpy).toHaveBeenCalled());
    // Discovery still loaded; tracking failure did not block.
    expect(result.current.discovery).toHaveLength(1);
  } finally {
    warnSpy.mockRestore();
    global.fetch = originalFetch;
  }
});

it('useOutletDiscovery_cart_deduplication', async () => {
  const catalog: Product[] = [
    { id: 3, name: 'P3', price: '12000', stock_quantity: 5, is_active: true },
    { id: 4, name: 'P4', price: '9000', stock_quantity: 5, is_active: true },
  ];
  const fetchMock = jest.fn().mockResolvedValue({
    ok: true,
    json: async () => ({
      data: { recommendations: [
        { product_id: 3, name: 'P3', reason: 'Populer di platform' },
        { product_id: 4, name: 'P4', reason: 'Populer di platform' },
      ] },
    }),
  });
  const originalFetch = global.fetch;
  global.fetch = fetchMock as typeof fetch;
  try {
    const { result } = renderHook(() =>
      useOutletDiscovery('token', catalog, { online: true, cart: { 3: 1 } }));
    await waitFor(() => expect(result.current.discovery).toHaveLength(1));
    expect(result.current.discovery.map((entry) => entry.product_id)).toEqual([4]);
  } finally {
    global.fetch = originalFetch;
  }
});

it('useOutletDiscovery_refetches_on_reconnect', async () => {
  const catalog: Product[] = [{ id: 3, name: 'P3', price: '12000', stock_quantity: 5, is_active: true }];
  const fetchMock = jest.fn().mockResolvedValue({
    ok: true,
    json: async () => ({ data: { recommendations: [{ product_id: 3, name: 'P3', reason: 'Populer di platform' }] } }),
  });
  const originalFetch = global.fetch;
  global.fetch = fetchMock as typeof fetch;
  try {
    const { result, rerender } = renderHook(
      ({ online }: { online: boolean }) =>
        useOutletDiscovery('token', catalog, { online }),
      { initialProps: { online: false } },
    );
    // Offline: no fetch, empty items.
    expect(result.current.discovery).toHaveLength(0);
    expect(fetchMock).not.toHaveBeenCalledWith(
      expect.stringContaining('/ai/discovery'),
      expect.anything(),
    );

    rerender({ online: true });

    await waitFor(() => expect(fetchMock).toHaveBeenCalledWith(
      expect.stringContaining('/ai/discovery'),
      expect.anything(),
    ));
    await waitFor(() => expect(result.current.discovery).toHaveLength(1));
  } finally {
    global.fetch = originalFetch;
  }
});

it('useOutletDiscovery_fetches_discovery_when_online', async () => {
  const item = { product_id: 42, product_name: 'Kopi Arabika', reason: 'Populer di sekitar Anda' };
  const fetchMock = jest.fn().mockResolvedValue({
    ok: true,
    json: async () => ({ discovery: [item] }),
  });
  const originalFetch = global.fetch;
  global.fetch = fetchMock as typeof fetch;

  try {
    const { result } = renderHook(() => useOutletDiscovery('outlet-token', [product], { online: true }));

    await waitFor(() => expect(fetchMock).toHaveBeenCalledWith(
      expect.stringContaining('/api/ai/discovery'),
      expect.anything(),
    ));
    await waitFor(() => expect(result.current.discovery).toMatchObject([item]));
  } finally {
    global.fetch = originalFetch;
  }
});

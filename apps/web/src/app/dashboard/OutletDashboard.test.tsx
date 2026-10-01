import '@testing-library/jest-dom';
import React from 'react';
import { fireEvent, render, screen, waitFor, within } from '@testing-library/react';
import type { OutletDashboardData } from '@/dummy';
import { __clearOutletCacheForTests } from './api';
import { useDummyStore } from '@/dummy/store';
import { toOutletStatus, periodWindow, topFavorites } from './outlet-helpers';
import OutletDashboard from './OutletDashboard';
import DashboardPage from './page';
import OrdersPage from '../orders/page';
import { getStoredToken } from '@/lib/api';

// Keep the real pure helpers in this component test: they are the contract shared
// by the dummy aggregate and the live dashboard adapter.
const recent: OutletDashboardData['summary']['recent'] = Array.from({ length: 12 }, (_, index) => ({
  id: index + 1,
  order_id: `ORD-${index + 1}`,
  status: index < 3 ? 'New' : 'Delivered',
  status_label: toOutletStatus(index < 3 ? 'New' : 'Delivered').label,
  total_amount: '100000.00',
  created_at: new Date(Date.now() - index * 86400000).toISOString(),
}));
const outletData: OutletDashboardData = {
  summary: { total: 5, statuses: { New: 3, Delivered: 2 }, recent, truncation: { capped: false, total: 5 } },
  shopping: { total: '400000.00', count: 4, period_label: periodWindow(30).label },
  credit: { credit_limit: '1000000.00', outstanding_balance: '200000.00', available_credit: '800000.00', hidden: false },
  favorites: topFavorites([{ items: [
    { product_id: 1, product_name: 'Produk A', quantity: 10 },
    { product_id: 2, product_name: 'Produk B', quantity: 6 },
  ] }]).map((item) => ({ product_id: item.product_id, display_name: item.displayName, total_qty: item.totalQty })),
};

const emptyData: OutletDashboardData = {
  ...outletData,
  summary: { total: 0, statuses: {}, recent: [], truncation: { capped: false, total: 0 } },
  shopping: { total: '0', count: 0, period_label: periodWindow(30).label },
  credit: { ...outletData.credit, credit_limit: null, available_credit: null, hidden: true },
  favorites: [],
};

// Integration harness: mocked HTTP fetch seam + real loadDashboard, page session guard,
// role branch, and OutletDashboard. This proves the client does not send outlet_id or
// locally filter cross-outlet records; actual tenant enforcement remains server-side.
const mockReplace = jest.fn();
jest.mock('next/navigation', () => ({ useRouter: () => ({ replace: mockReplace }) }));

jest.mock('@/lib/api', () => ({
  apiUrl: (path: string) => path,
  authHeaders: (token: string) => ({ Authorization: `Bearer ${token}` }),
  getStoredToken: jest.fn(),
}));

jest.mock('@/components/OrderForm', () => ({ __esModule: true, default: () => <div data-testid="ordering-form" /> }));

const mockedGetToken = getStoredToken as jest.MockedFunction<typeof getStoredToken>;

beforeEach(() => {
  jest.clearAllMocks();
  __clearOutletCacheForTests();
  useDummyStore.setState({ isDummy: false, dummyEntities: null });
  mockedGetToken.mockReturnValue('outlet-A-token');
});

test('integration isolation: only server-scoped outlet A orders render and requests never send tampered outlet_id', async () => {
  const urls: string[] = [];
  global.fetch = jest.fn(async (input: RequestInfo | URL) => {
    const url = String(input);
    urls.push(url);
    if (url.includes('/auth/me')) return { ok: true, json: async () => ({ data: { role: 'outlet' } }) } as Response;
    if (url.includes('/orders')) {
      const params = new URL(url, 'http://test').searchParams;
      const cursor = params.get('cursor');
      return { ok: true, json: async () => ({
        status: 'success',
        // Mock backend returns A-only pages (server token override). B has a tempting ID.
        data: cursor === '0'
          ? [{ id: 1, order_id: 'A-001', status: 'New', total_amount: '100', paid_amount: '0', created_at: new Date().toISOString(), items: [] }]
          : [{ id: 2, order_id: 'A-002', status: 'Delivered', total_amount: '200', paid_amount: '0', created_at: new Date().toISOString(), items: [] }],
        meta: { has_more: cursor === '0', total: 2, limit: 100, cursor: Number(cursor) },
      }) } as Response;
    }
    if (url.includes('/credit-limit')) return { ok: true, json: async () => ({ data: { credit_limit: 1000, outstanding_balance: '10', available_credit: 990 } }) } as Response;
    throw new Error(`Unexpected URL ${url}`);
  });

  render(<DashboardPage />);
  expect(await screen.findByText(/A-001/)).toBeInTheDocument();
  expect(screen.getByText(/A-002/)).toBeInTheDocument();
  expect(screen.queryByText(/B-999/)).not.toBeInTheDocument();
  const orderUrls = urls.filter((url) => url.includes('/orders'));
  expect(orderUrls).toHaveLength(2);
  for (const url of orderUrls) {
    expect(url).not.toContain('outlet_id');
    expect(url).toContain('limit=100');
  }
  expect(orderUrls[1]).toContain('cursor=1');
  for (const [, options] of (global.fetch as jest.Mock).mock.calls.filter(([url]) => String(url).includes('/orders'))) {
    expect(options.headers).toEqual({ Authorization: 'Bearer outlet-A-token' });
  }
});

test('integration auth: no token redirects to login', async () => {
  mockedGetToken.mockReturnValue(null);
  render(<DashboardPage />);
  await waitFor(() => expect(mockReplace).toHaveBeenCalledWith('/login?redirect=%2Fdashboard'));
});

test('outlet retains direct access to /orders ordering form', async () => {
  render(<OrdersPage />);
  expect(await screen.findByTestId('ordering-form')).toBeInTheDocument();
  expect(mockReplace).not.toHaveBeenCalled();
});

test('real page flow: orders 403 without outlet relation shows inline guidance without retry', async () => {
  const urls: string[] = [];
  global.fetch = jest.fn(async (input: RequestInfo | URL) => {
    const url = String(input);
    urls.push(url);
    if (url.includes('/auth/me')) return { ok: true, json: async () => ({ data: { role: 'outlet' } }) } as Response;
    if (url.includes('/orders')) return { ok: false, status: 403, json: async () => ({ message: 'User is not associated with an outlet' }) } as Response;
    throw new Error(`Unexpected URL ${url}`);
  }) as jest.Mock;
  mockedGetToken.mockReturnValue('valid-token-without-outlet');
  const warn = jest.spyOn(console, 'warn').mockImplementation(() => undefined);
  render(<DashboardPage />);
  await waitFor(() => expect(screen.getAllByRole('alert').length).toBeGreaterThan(0));
  const alerts = screen.getAllByRole('alert');
  expect(alerts[0]).toHaveTextContent('Akun ini tidak terhubung ke outlet. Hubungi admin.');
  expect(screen.queryByTestId('outlet-retry')).not.toBeInTheDocument();
  expect(urls.some((url) => url.includes('/credit-limit'))).toBe(false);
  warn.mockRestore();
});

test('period switching recomputes shopping from cached orders without refetching', async () => {
  const urls: string[] = [];
  global.fetch = jest.fn(async (input: RequestInfo | URL) => {
    const url = String(input);
    urls.push(url);
    if (url.includes('/auth/me')) return { ok: true, json: async () => ({ data: { role: 'outlet' } }) } as Response;
    if (url.includes('/orders')) {
      return { ok: true, json: async () => ({
        status: 'success',
        data: [
          { id: 1, order_id: 'A-001', status: 'Delivered', total_amount: '100', paid_amount: '0', created_at: new Date(Date.now() - 1 * 86400000).toISOString(), items: [{ product_id: 1, product_name: 'Beras', quantity: 2, unit_price: '50', subtotal: '100' }] },
          { id: 2, order_id: 'A-002', status: 'Delivered', total_amount: '200', paid_amount: '0', created_at: new Date(Date.now() - 10 * 86400000).toISOString(), items: [{ product_id: 1, product_name: 'Beras', quantity: 4, unit_price: '50', subtotal: '200' }] },
        ],
        meta: { has_more: false, total: 2, limit: 100, cursor: 0 },
      }) } as Response;
    }
    if (url.includes('/credit-limit')) return { ok: true, json: async () => ({ data: { credit_limit: 1000000, outstanding_balance: '0', available_credit: 1000000 } }) } as Response;
    throw new Error(`Unexpected URL ${url}`);
  });

  render(<DashboardPage />);
  await waitFor(() => expect(screen.getByTestId('outlet-dashboard')).toBeInTheDocument());

  // Default period 30 hari - both orders (1d and 10d ago) in window
  await waitFor(() => expect(screen.getByTestId('outlet-shopping-summary').textContent).toContain('Rp 300'));

  // Switch to 7 hari - only order 1 (1d ago) in window
  fireEvent.change(screen.getByLabelText(/periode/i), { target: { value: '7' } });
  await waitFor(() => expect(screen.getByTestId('outlet-shopping-summary').textContent).toContain('Rp 100'));

  // Switch to 90 hari - both in window again
  fireEvent.change(screen.getByLabelText(/periode/i), { target: { value: '90' } });
  await waitFor(() => expect(screen.getByTestId('outlet-shopping-summary').textContent).toContain('Rp 300'));

  // Verify no additional /orders fetches (orders served from cache; shopping recomputed client-side)
  const orderUrls = urls.filter((url) => url.includes('/orders'));
  expect(orderUrls).toHaveLength(1);
});

test('credit retry isolates to /credit-limit without refetching orders', async () => {
  let creditFail = true;
  const urls: string[] = [];
  global.fetch = jest.fn(async (input: RequestInfo | URL) => {
    const url = String(input);
    urls.push(url);
    if (url.includes('/auth/me')) return { ok: true, json: async () => ({ data: { role: 'outlet' } }) } as Response;
    if (url.includes('/orders')) {
      return { ok: true, json: async () => ({
        status: 'success',
        data: [{ id: 1, order_id: 'A-001', status: 'New', total_amount: '100', paid_amount: '0', created_at: new Date().toISOString(), items: [] }],
        meta: { has_more: false, total: 1, limit: 100, cursor: 0 },
      }) } as Response;
    }
    if (url.includes('/credit-limit')) {
      if (creditFail) return { ok: false, status: 500, json: async () => ({ message: 'Server error' }) } as Response;
      return { ok: true, json: async () => ({ data: { credit_limit: 1000000, outstanding_balance: '0', available_credit: 1000000 } }) } as Response;
    }
    throw new Error(`Unexpected URL ${url}`);
  });

  render(<DashboardPage />);
  await waitFor(() => expect(screen.getByTestId('outlet-dashboard')).toBeInTheDocument());

  // Credit should show error
  expect(screen.queryByTestId('outlet-credit-card')).toBeNull();

  // Click credit retry button
  creditFail = false;
  fireEvent.click(screen.getByTestId('outlet-retry'));
  await waitFor(() => expect(screen.getByTestId('outlet-credit-card')).toBeInTheDocument());

  // Verify only one /orders fetch and two /credit-limit fetches (initial + retry)
  const orderUrls = urls.filter((url) => url.includes('/orders'));
  const creditUrls = urls.filter((url) => url.includes('/credit-limit'));
  expect(orderUrls).toHaveLength(1);
  expect(creditUrls).toHaveLength(2);
});

test('happy render: outlet sections display newest orders, shopping, credit and favorites', () => {
  render(<OutletDashboard data={outletData} />);
  expect(screen.getByTestId('outlet-dashboard')).toBeTruthy();
  const summary = screen.getByTestId('outlet-orders-summary');
  expect(summary.textContent).toContain('Menunggu konfirmasi admin');
  expect(summary.textContent).toContain('3');
  expect(summary.textContent).toContain('Terkirim, periksa invoice');
  expect(summary.textContent).toContain('2');
  const orders = within(screen.getByTestId('outlet-orders-recent')).getAllByRole('link', { name: /ORD-/ });
  expect(orders).toHaveLength(10);
  expect(orders[0].getAttribute('href')).toBe('/orders?order_id=ORD-1');
  expect(orders[9].getAttribute('href')).toBe('/orders?order_id=ORD-10');
  expect(screen.getByTestId('outlet-shopping-summary').textContent).toContain('30 hari terakhir');
  expect(screen.getByTestId('outlet-shopping-summary').textContent).toContain('Rp 400.000');
  expect(screen.getByTestId('outlet-shopping-summary').textContent).toContain('4');
  expect(screen.getByTestId('outlet-credit-card').textContent).toContain('Rp 1.000.000');
  expect(screen.getByTestId('outlet-credit-card').textContent).toContain('Rp 200.000');
  expect(screen.getByTestId('outlet-credit-card').textContent).toContain('Rp 800.000');
  const favorites = screen.getByTestId('outlet-favorites');
  expect(favorites.textContent!.indexOf('Produk A')).toBeLessThan(favorites.textContent!.indexOf('Produk B'));
  expect(within(favorites).getAllByRole('link', { name: 'Pesan lagi' })[0].getAttribute('href')).toBe('/orders');
});

test('empty vs error: distinguishes no history from empty shopping window and isolates section retry', () => {
  const onRetryOrders = jest.fn();
  const onRetryShopping = jest.fn();
  const onRetryCredit = jest.fn();
  const onRetryFavorites = jest.fn();
  const failedOrders = Object.assign(new Error('failed'), { status: 500 });
  const { rerender, container } = render(<OutletDashboard data={emptyData} />);
  expect(screen.getByTestId('outlet-orders-recent').textContent).toContain('Belum ada pesanan');
  expect(screen.queryByTestId('outlet-credit-card')).toBeNull();
  rerender(<OutletDashboard data={{ ...outletData, shopping: { total: '0', count: 0, period_label: '7 hari terakhir' } }} />);
  fireEvent.change(screen.getByLabelText(/periode/i), { target: { value: '7' } });
  expect(screen.getByTestId('outlet-shopping-summary').textContent).toContain('Tidak ada transaksi pada periode ini');
  rerender(<OutletDashboard data={outletData} ordersError={failedOrders} onRetryOrders={onRetryOrders} onRetryShopping={onRetryShopping} onRetryCredit={onRetryCredit} onRetryFavorites={onRetryFavorites} />);
  fireEvent.click(within(screen.getByTestId('outlet-section-error')).getByTestId('outlet-retry'));
  expect(onRetryOrders).toHaveBeenCalledTimes(1);
  expect(onRetryShopping).not.toHaveBeenCalled();
  expect(onRetryCredit).not.toHaveBeenCalled();
  expect(onRetryFavorites).not.toHaveBeenCalled();
  expect(container.querySelector('[data-credit-hidden="true"]')).toBeNull();
});

test('403 and a11y: inline access warning has no retry; retry keyboard focus and warnings are safe', () => {
  const warn = jest.spyOn(console, 'warn').mockImplementation(() => undefined);
  const retry = jest.fn();
  const authError = Object.assign(new Error('private payload should not appear'), { status: 403, inline: true });
  const partialError = Object.assign(new Error('private payload'), { status: 500 });
  const { rerender } = render(<OutletDashboard data={outletData} ordersError={authError} />);
  const sectionError = screen.getByTestId('outlet-section-error');
  expect(within(sectionError).getByRole('alert')).toBeTruthy();
  expect(sectionError.textContent).toContain('Akun ini tidak terhubung ke outlet. Hubungi admin.');
  expect(within(sectionError).queryByTestId('outlet-retry')).toBeNull();
  rerender(<OutletDashboard data={outletData} ordersError={partialError} onRetryOrders={retry} />);
  const button = screen.getByTestId('outlet-retry');
  button.focus();
  fireEvent.keyDown(button, { key: 'Enter' });
  expect(retry).toHaveBeenCalledTimes(1);
  expect(document.activeElement).toBe(button);
  expect(warn).toHaveBeenCalled();
  expect(warn.mock.calls.flat().join(' ')).not.toContain('private payload');
  warn.mockRestore();
});

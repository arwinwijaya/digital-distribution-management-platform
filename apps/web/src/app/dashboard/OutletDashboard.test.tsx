import '@testing-library/jest-dom';
import React from 'react';
import { fireEvent, render, screen, within } from '@testing-library/react';
import type { OutletDashboardData } from '@/dummy';
import { toOutletStatus, periodWindow, topFavorites } from './outlet-helpers';
import OutletDashboard from './OutletDashboard';

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

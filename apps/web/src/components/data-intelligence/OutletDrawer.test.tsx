import React from 'react';
import '@testing-library/jest-dom';
import { render, screen } from '@testing-library/react';
import OutletDrawer from './OutletDrawer';
import type { GeographicMapPoint } from '@/lib/data-intelligence-api';

const window30 = { start: '2026-08-27', end: '2026-09-25', timezone: 'Asia/Jakarta' };
const filter1 = { statuses: ['New', 'Confirmed'], period: '30d' as const };

const v2Point: GeographicMapPoint = {
  outlet_id: 12,
  outlet_name: 'Outlet Jaya',
  territory: 'Jakarta Selatan',
  latitude: -6.2,
  longitude: 106.8,
  orders: 9,
  sales: '125000.00',
  orders_by_status: { New: 5, Confirmed: 2, Delivered: 1, 'Partially Paid': 1 },
  sales_by_status: { New: '60000.00', Confirmed: '30000.00', Delivered: '20000.00', 'Partially Paid': '15000.00' },
  daily_by_status: [
    { date: '2026-09-24', counts: { New: 2, Confirmed: 1 }, sales: { New: '20000.00', Confirmed: '10000.00' } },
    { date: '2026-09-25', counts: { New: 1, Confirmed: 0 }, sales: { New: '10000.00', Confirmed: '0.00' } },
  ],
  product_summary: [{ product_id: 8, product_name: 'Produk A', quantity: 12, subtotal: '90000.00' }],
  product_summary_truncated: false,
  latest_request: { order_id: '301', status: 'New', created_at: '2026-09-25T10:20:00+07:00' },
};

describe('OutletDrawer', () => {
  it('renders v2 outlet detail and an encoded order context link', () => {
    render(
      <OutletDrawer
        point={v2Point}
        openingFilter={filter1}
        currentFilter={filter1}
        window={window30}
        onClose={jest.fn()}
      />,
    );

    expect(screen.getByTestId('outlet-drawer')).toBeVisible();
    expect(screen.getByText('Outlet Jaya')).toBeInTheDocument();
    expect(screen.getByText('Jakarta Selatan')).toBeInTheDocument();
    expect(screen.getByText('7')).toBeInTheDocument();
    expect(screen.getByText('New: 5')).toBeInTheDocument();
    expect(screen.getByText('Confirmed: 2')).toBeInTheDocument();
    expect(screen.getByText('2026-09-24: 3')).toBeInTheDocument();
    expect(screen.getByText('2026-09-25: 1')).toBeInTheDocument();
    expect(screen.getByText(/301/)).toBeInTheDocument();
    expect(screen.getByText(/Produk A/)).toBeInTheDocument();

    const link = screen.getByRole('link', { name: 'Lihat semua order' });
    expect(link).toHaveAttribute(
      'href',
      '/admin/orders?outlet_id=12&status=New%2CConfirmed&start=2026-08-27&end=2026-09-25',
    );
  });

  it('uses an explicit fallback for v1 rows and invents no status/daily values', () => {
    const v1: GeographicMapPoint = {
      outlet_id: 105,
      outlet_name: 'Outlet V1',
      territory: 'Bekasi',
      latitude: -6.4,
      longitude: 107,
      orders: 10,
      sales: '500000.00',
    };
    render(
      <OutletDrawer
        point={v1}
        openingFilter={filter1}
        currentFilter={filter1}
        window={window30}
        onClose={jest.fn()}
      />,
    );

    expect(
      screen.getByText('Detail produk belum tersedia — jalankan pipeline data'),
    ).toBeInTheDocument();
    // No fabricated status/daily breakdown for a v1 row.
    expect(screen.queryByText(/New:/)).not.toBeInTheDocument();
    expect(screen.queryByText(/Confirmed:/)).not.toBeInTheDocument();
    expect(screen.queryByText(/2026-09-24/)).not.toBeInTheDocument();
    expect(screen.queryByText(/301/)).not.toBeInTheDocument();
  });

  it('labels a truncated product list as Top 5 and a narrow period as snapshot-window', () => {
    const { unmount } = render(
      <OutletDrawer
        point={{ ...v2Point, product_summary_truncated: true }}
        openingFilter={filter1}
        currentFilter={filter1}
        window={window30}
        onClose={jest.fn()}
      />,
    );
    expect(screen.getByText('Top 5 produk')).toBeInTheDocument();
    unmount();

    const filter7d = { statuses: ['New', 'Confirmed'], period: '7d' as const };
    render(
      <OutletDrawer
        point={v2Point}
        openingFilter={filter7d}
        currentFilter={filter7d}
        window={window30}
        onClose={jest.fn()}
      />,
    );
    expect(screen.getByText(/snapshot-window/i)).toBeInTheDocument();
    expect(screen.queryByText('Top 5 produk')).not.toBeInTheDocument();
  });
});

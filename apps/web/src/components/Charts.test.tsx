import React from 'react';
import '@testing-library/jest-dom';
import { render, screen } from '@testing-library/react';

import { OutletPerformanceChart, SalesTrendChart } from '@/components/Charts';

/**
 * Regression guard for the dashboard money column.
 *
 * The value used to live in a fixed `6rem` grid track, which is narrower than
 * `Rp 239.992.000` at `text-sm`, so the browser wrapped it at the space between
 * "Rp" and the digits. jsdom cannot compute layout, so the wrapping guard is
 * asserted via the grid template + `whitespace-nowrap` on the value span.
 */
describe('Charts money column', () => {
  const trend = [{ period: '2026-09', orders_total: 10, sales_total: '239992000.00', payments_total: '0.00' }];
  const outlets = [{ rank: 1, outlet_id: 1, outlet_name: 'Outlet A', orders_total: 10, sales_total: '239992000.00' }];

  it('renders the sales trend money as one grouped Rupiah string', () => {
    render(<SalesTrendChart points={trend} />);

    const value = screen.getByText('Rp 239.992.000');
    expect(value).toBeInTheDocument();
    // The raw fixed-2-decimal string must not leak into the DOM.
    expect(screen.queryByText(/239992000\.00/)).not.toBeInTheDocument();
  });

  it('keeps the sales trend money on one line (no fixed 6rem track)', () => {
    const { container } = render(<SalesTrendChart points={trend} />);

    const value = screen.getByText('Rp 239.992.000');
    expect(value).toHaveClass('whitespace-nowrap');
    expect(value).toHaveClass('tabular-nums');

    const row = container.querySelector('[class*="grid-cols-"]');
    expect(row?.className).toContain('grid-cols-[7rem_minmax(0,1fr)_auto]');
    expect(row?.className).not.toContain('_6rem]');
  });

  it('renders the outlet performance money as one grouped Rupiah string', () => {
    render(<OutletPerformanceChart outlets={outlets} />);

    const value = screen.getByText('Rp 239.992.000');
    expect(value).toBeInTheDocument();
    expect(value).toHaveClass('whitespace-nowrap');
    expect(value).toHaveClass('tabular-nums');
  });

  it('keeps the outlet performance money on one line (no fixed 6rem track)', () => {
    const { container } = render(<OutletPerformanceChart outlets={outlets} />);

    const row = container.querySelector('[class*="grid-cols-"]');
    expect(row?.className).toContain('grid-cols-[2rem_9rem_minmax(0,1fr)_auto]');
    expect(row?.className).not.toContain('_6rem]');
  });

  it('groups the aria-label money the same way as the visible text', () => {
    render(<OutletPerformanceChart outlets={outlets} />);

    expect(screen.getByRole('img', { name: 'Outlet A: Rp 239.992.000' })).toBeInTheDocument();
  });

  it('renders the empty states when there is no data', () => {
    render(<SalesTrendChart points={[]} />);
    expect(screen.getByText('No sales in this period.')).toBeInTheDocument();

    render(<OutletPerformanceChart outlets={[]} />);
    expect(screen.getByText('No outlet sales in this period.')).toBeInTheDocument();
  });
});

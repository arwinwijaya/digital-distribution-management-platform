import React from 'react';
import '@testing-library/jest-dom';
import { render, screen } from '@testing-library/react';
import TableSummary from './TableSummary';

describe('TableSummary', () => {
  it('renders total with noun', () => {
    render(<TableSummary total={48} noun="outlet" />);
    expect(screen.getByText('48 outlet')).toBeInTheDocument();
  });

  it('renders total with breakdown', () => {
    render(
      <TableSummary
        total={48}
        noun="outlet"
        breakdown={[
          { label: 'aktif', value: 32 },
          { label: 'nonaktif', value: 16 },
        ]}
      />
    );
    expect(screen.getByText('48 outlet · 32 aktif · 16 nonaktif')).toBeInTheDocument();
  });

  it('renders zero state', () => {
    render(<TableSummary total={0} noun="outlet" />);
    expect(screen.getByText('0 outlet')).toBeInTheDocument();
  });

  it('renders zero state with breakdown', () => {
    render(
      <TableSummary
        total={0}
        noun="produk"
        breakdown={[
          { label: 'aktif', value: 0 },
          { label: 'nonaktif', value: 0 },
        ]}
      />
    );
    expect(screen.getByText('0 produk · 0 aktif · 0 nonaktif')).toBeInTheDocument();
  });

  it('renders without breakdown', () => {
    render(<TableSummary total={48} noun="outlet" />);
    expect(screen.getByText('48 outlet')).toBeInTheDocument();
    expect(screen.queryByText(/·/)).not.toBeInTheDocument();
  });

  it('renders single breakdown item', () => {
    render(
      <TableSummary
        total={10}
        noun="pengguna"
        breakdown={[{ label: 'aktif', value: 10 }]}
      />
    );
    expect(screen.getByText('10 pengguna · 10 aktif')).toBeInTheDocument();
  });
});

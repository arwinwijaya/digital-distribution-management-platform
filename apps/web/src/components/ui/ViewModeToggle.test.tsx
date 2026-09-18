import React from 'react';
import '@testing-library/jest-dom';
import { render, screen, fireEvent } from '@testing-library/react';
import ViewModeToggle from './ViewModeToggle';

describe('ViewModeToggle', () => {
  const mockOnChange = jest.fn();

  beforeEach(() => {
    mockOnChange.mockClear();
  });

  describe('Click handlers', () => {
    it('calls onChange with "table" when Tabel button clicked', () => {
      render(<ViewModeToggle value="card" onChange={mockOnChange} />);
      fireEvent.click(screen.getByRole('button', { name: /tabel/i }));
      expect(mockOnChange).toHaveBeenCalledWith('table');
    });

    it('calls onChange with "card" when Card button clicked', () => {
      render(<ViewModeToggle value="table" onChange={mockOnChange} />);
      fireEvent.click(screen.getByRole('button', { name: /card/i }));
      expect(mockOnChange).toHaveBeenCalledWith('card');
    });
  });

  describe('Selected state', () => {
    it('highlights "card" when value is "card"', () => {
      render(<ViewModeToggle value="card" onChange={mockOnChange} />);
      expect(screen.getByRole('button', { name: /card/i })).toHaveAttribute('aria-pressed', 'true');
      expect(screen.getByRole('button', { name: /tabel/i })).toHaveAttribute('aria-pressed', 'false');
    });

    it('highlights "table" when value is "table"', () => {
      render(<ViewModeToggle value="table" onChange={mockOnChange} />);
      expect(screen.getByRole('button', { name: /card/i })).toHaveAttribute('aria-pressed', 'false');
      expect(screen.getByRole('button', { name: /tabel/i })).toHaveAttribute('aria-pressed', 'true');
    });
  });

  describe('Accessibility + labels', () => {
    it('exposes a distinct group label', () => {
      render(<ViewModeToggle value="card" onChange={mockOnChange} />);
      expect(screen.getByRole('group', { name: /tampilan produk/i })).toBeInTheDocument();
    });

    it('renders both view options', () => {
      render(<ViewModeToggle value="card" onChange={mockOnChange} />);
      expect(screen.getByRole('button', { name: /card/i })).toBeInTheDocument();
      expect(screen.getByRole('button', { name: /tabel/i })).toBeInTheDocument();
    });
  });
});

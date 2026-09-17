import React from 'react';
import '@testing-library/jest-dom';
import { render, screen, fireEvent } from '@testing-library/react';
import TableDensityToggle from './TableDensityToggle';

describe('TableDensityToggle', () => {
  const mockOnChange = jest.fn();

  beforeEach(() => {
    mockOnChange.mockClear();
  });

  describe('Click handlers', () => {
    it('calls onChange with "compact" when Compact button clicked', () => {
      render(<TableDensityToggle value="default" onChange={mockOnChange} />);
      fireEvent.click(screen.getByRole('button', { name: /compact/i }));
      expect(mockOnChange).toHaveBeenCalledWith('compact');
    });

    it('calls onChange with "default" when Default button clicked', () => {
      render(<TableDensityToggle value="compact" onChange={mockOnChange} />);
      fireEvent.click(screen.getByRole('button', { name: /default/i }));
      expect(mockOnChange).toHaveBeenCalledWith('default');
    });

    it('calls onChange with "comfortable" when Comfortable button clicked', () => {
      render(<TableDensityToggle value="compact" onChange={mockOnChange} />);
      fireEvent.click(screen.getByRole('button', { name: /comfortable/i }));
      expect(mockOnChange).toHaveBeenCalledWith('comfortable');
    });
  });

  describe('Selected state', () => {
    it('highlights "compact" when value is "compact"', () => {
      render(<TableDensityToggle value="compact" onChange={mockOnChange} />);
      const compactButton = screen.getByRole('button', { name: /compact/i });
      const defaultButton = screen.getByRole('button', { name: /default/i });
      const comfortableButton = screen.getByRole('button', { name: /comfortable/i });

      expect(compactButton).toHaveAttribute('aria-pressed', 'true');
      expect(defaultButton).toHaveAttribute('aria-pressed', 'false');
      expect(comfortableButton).toHaveAttribute('aria-pressed', 'false');
    });

    it('highlights "default" when value is "default"', () => {
      render(<TableDensityToggle value="default" onChange={mockOnChange} />);
      const compactButton = screen.getByRole('button', { name: /compact/i });
      const defaultButton = screen.getByRole('button', { name: /default/i });
      const comfortableButton = screen.getByRole('button', { name: /comfortable/i });

      expect(compactButton).toHaveAttribute('aria-pressed', 'false');
      expect(defaultButton).toHaveAttribute('aria-pressed', 'true');
      expect(comfortableButton).toHaveAttribute('aria-pressed', 'false');
    });

    it('highlights "comfortable" when value is "comfortable"', () => {
      render(<TableDensityToggle value="comfortable" onChange={mockOnChange} />);
      const compactButton = screen.getByRole('button', { name: /compact/i });
      const defaultButton = screen.getByRole('button', { name: /default/i });
      const comfortableButton = screen.getByRole('button', { name: /comfortable/i });

      expect(compactButton).toHaveAttribute('aria-pressed', 'false');
      expect(defaultButton).toHaveAttribute('aria-pressed', 'false');
      expect(comfortableButton).toHaveAttribute('aria-pressed', 'true');
    });
  });

  describe('Button labels', () => {
    it('renders all three density options', () => {
      render(<TableDensityToggle value="default" onChange={mockOnChange} />);
      expect(screen.getByRole('button', { name: /compact/i })).toBeInTheDocument();
      expect(screen.getByRole('button', { name: /default/i })).toBeInTheDocument();
      expect(screen.getByRole('button', { name: /comfortable/i })).toBeInTheDocument();
    });
  });
});

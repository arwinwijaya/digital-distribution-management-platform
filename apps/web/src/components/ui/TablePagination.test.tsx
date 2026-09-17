import React from 'react';
import '@testing-library/jest-dom';
import { render, screen, fireEvent } from '@testing-library/react';
import TablePagination from './TablePagination';

// NOTE: `buildCountLabel` from `@/lib/admin-table` is intentionally NOT mocked.
// These tests exercise the REAL helper so a regression in the T2 label contract
// (or a wrong argument order here) fails loudly instead of being papered over.

describe('TablePagination', () => {
  const mockOnPageChange = jest.fn();

  beforeEach(() => {
    mockOnPageChange.mockClear();
  });

  describe('Label', () => {
    it('renders correct label for page 1', () => {
      render(
        <TablePagination
          cursor={0}
          limit={15}
          total={48}
          hasMore={true}
          onPageChange={mockOnPageChange}
        />
      );
      expect(screen.getByText('Halaman 1 dari 4 · 48 data')).toBeInTheDocument();
    });

    it('renders correct label for page 2', () => {
      render(
        <TablePagination
          cursor={15}
          limit={15}
          total={48}
          hasMore={true}
          onPageChange={mockOnPageChange}
        />
      );
      expect(screen.getByText('Halaman 2 dari 4 · 48 data')).toBeInTheDocument();
    });

    it('renders correct label for last page', () => {
      render(
        <TablePagination
          cursor={45}
          limit={15}
          total={48}
          hasMore={false}
          onPageChange={mockOnPageChange}
        />
      );
      expect(screen.getByText('Halaman 4 dari 4 · 48 data')).toBeInTheDocument();
    });

    it('renders label without total (hasMore=true)', () => {
      render(
        <TablePagination
          cursor={0}
          limit={15}
          hasMore={true}
          onPageChange={mockOnPageChange}
        />
      );
      expect(screen.getByText('Halaman 1 · ada data lain')).toBeInTheDocument();
    });

    it('renders label without total (hasMore=false)', () => {
      render(
        <TablePagination
          cursor={0}
          limit={15}
          hasMore={false}
          onPageChange={mockOnPageChange}
        />
      );
      expect(screen.getByText('Halaman 1')).toBeInTheDocument();
    });
  });

  describe('Prev/Next buttons', () => {
    it('disables prev button when cursor is 0', () => {
      render(
        <TablePagination
          cursor={0}
          limit={15}
          total={48}
          hasMore={true}
          onPageChange={mockOnPageChange}
        />
      );
      const prevButton = screen.getByRole('button', { name: /sebelumnya/i });
      expect(prevButton).toBeDisabled();
    });

    it('enables prev button when cursor > 0', () => {
      render(
        <TablePagination
          cursor={15}
          limit={15}
          total={48}
          hasMore={true}
          onPageChange={mockOnPageChange}
        />
      );
      const prevButton = screen.getByRole('button', { name: /sebelumnya/i });
      expect(prevButton).toBeEnabled();
    });

    it('disables next button when hasMore is false', () => {
      render(
        <TablePagination
          cursor={0}
          limit={15}
          total={48}
          hasMore={false}
          onPageChange={mockOnPageChange}
        />
      );
      const nextButton = screen.getByRole('button', { name: /berikutnya/i });
      expect(nextButton).toBeDisabled();
    });

    it('disables next button when on last page (cursor + limit >= total)', () => {
      render(
        <TablePagination
          cursor={45}
          limit={15}
          total={48}
          hasMore={false}
          onPageChange={mockOnPageChange}
        />
      );
      const nextButton = screen.getByRole('button', { name: /berikutnya/i });
      expect(nextButton).toBeDisabled();
    });

    it('enables next button when hasMore is true', () => {
      render(
        <TablePagination
          cursor={0}
          limit={15}
          total={48}
          hasMore={true}
          onPageChange={mockOnPageChange}
        />
      );
      const nextButton = screen.getByRole('button', { name: /berikutnya/i });
      expect(nextButton).toBeEnabled();
    });
  });

  describe('Jump buttons', () => {
    it('renders page number buttons', () => {
      render(
        <TablePagination
          cursor={0}
          limit={15}
          total={48}
          hasMore={true}
          onPageChange={mockOnPageChange}
        />
      );
      expect(screen.getByRole('button', { name: '1' })).toBeInTheDocument();
      expect(screen.getByRole('button', { name: '2' })).toBeInTheDocument();
      expect(screen.getByRole('button', { name: '3' })).toBeInTheDocument();
      expect(screen.getByRole('button', { name: '4' })).toBeInTheDocument();
    });

    it('calls onPageChange with correct cursor when jump button clicked', () => {
      render(
        <TablePagination
          cursor={0}
          limit={15}
          total={48}
          hasMore={true}
          onPageChange={mockOnPageChange}
        />
      );
      fireEvent.click(screen.getByRole('button', { name: '3' }));
      expect(mockOnPageChange).toHaveBeenCalledWith(30);
    });

    it('estimates page count when total is absent and hasMore is true', () => {
      render(
        <TablePagination
          cursor={30}
          limit={15}
          hasMore={true}
          onPageChange={mockOnPageChange}
        />
      );
      // cursor=30, limit=15, hasMore=true → estimated total = 30 + 15 + 1 = 46 → pages = ceil(46/15) = 4
      expect(screen.getByRole('button', { name: '1' })).toBeInTheDocument();
      expect(screen.getByRole('button', { name: '2' })).toBeInTheDocument();
      expect(screen.getByRole('button', { name: '3' })).toBeInTheDocument();
      expect(screen.getByRole('button', { name: '4' })).toBeInTheDocument();
    });

    it('estimates page count when total is absent and hasMore is false', () => {
      render(
        <TablePagination
          cursor={30}
          limit={15}
          hasMore={false}
          onPageChange={mockOnPageChange}
        />
      );
      // cursor=30, limit=15, hasMore=false → estimated total = 30 + 15 = 45 → pages = ceil(45/15) = 3
      expect(screen.getByRole('button', { name: '1' })).toBeInTheDocument();
      expect(screen.getByRole('button', { name: '2' })).toBeInTheDocument();
      expect(screen.getByRole('button', { name: '3' })).toBeInTheDocument();
      expect(screen.queryByRole('button', { name: '4' })).not.toBeInTheDocument();
    });
  });

  describe('Beyond-total guard', () => {
    it('shows "tidak ada data lanjutan" when cursor >= total', () => {
      render(
        <TablePagination
          cursor={60}
          limit={15}
          total={48}
          hasMore={false}
          onPageChange={mockOnPageChange}
        />
      );
      expect(screen.getByText('tidak ada data lanjutan')).toBeInTheDocument();
    });

    it('disables next button when cursor >= total', () => {
      render(
        <TablePagination
          cursor={60}
          limit={15}
          total={48}
          hasMore={false}
          onPageChange={mockOnPageChange}
        />
      );
      const nextButton = screen.getByRole('button', { name: /berikutnya/i });
      expect(nextButton).toBeDisabled();
    });

    it('keeps prev button enabled when cursor >= total', () => {
      render(
        <TablePagination
          cursor={60}
          limit={15}
          total={48}
          hasMore={false}
          onPageChange={mockOnPageChange}
        />
      );
      const prevButton = screen.getByRole('button', { name: /sebelumnya/i });
      expect(prevButton).toBeEnabled();
    });

    it('clicking prev calls onPageChange with correct cursor when beyond total', () => {
      render(
        <TablePagination
          cursor={60}
          limit={15}
          total={48}
          hasMore={false}
          onPageChange={mockOnPageChange}
        />
      );
      fireEvent.click(screen.getByRole('button', { name: /sebelumnya/i }));
      expect(mockOnPageChange).toHaveBeenCalledWith(45);
    });
  });

  describe('Prev/Next click handlers', () => {
    it('clicking prev calls onPageChange with cursor - limit', () => {
      render(
        <TablePagination
          cursor={30}
          limit={15}
          total={48}
          hasMore={true}
          onPageChange={mockOnPageChange}
        />
      );
      fireEvent.click(screen.getByRole('button', { name: /sebelumnya/i }));
      expect(mockOnPageChange).toHaveBeenCalledWith(15);
    });

    it('clicking next calls onPageChange with cursor + limit', () => {
      render(
        <TablePagination
          cursor={0}
          limit={15}
          total={48}
          hasMore={true}
          onPageChange={mockOnPageChange}
        />
      );
      fireEvent.click(screen.getByRole('button', { name: /berikutnya/i }));
      expect(mockOnPageChange).toHaveBeenCalledWith(15);
    });
  });
});

/**
 * WorktreeFlow.test.tsx — Core flow component tests.
 *
 * Verifies:
 *  - 8 nodes visible (6 main stages + 2 terminals)
 *  - Correct vertical order of main stages
 *  - StatusBadge labels visible per main node
 */
import React from 'react';
import '@testing-library/jest-dom';
import { render, screen } from '@testing-library/react';
import WorktreeFlow from '@/components/WorktreeFlow';

// Helper: get all heading elements and return their text in DOM order
function getMainStageLabels(): string[] {
  return screen
    .getAllByRole('heading', { level: 3 })
    .map((el) => el.textContent?.trim() ?? '');
}

describe('WorktreeFlow — nodes visible in DOM', () => {
  it('renders all 8 node labels as h3 headings', () => {
    render(<WorktreeFlow />);

    const headings = screen.getAllByRole('heading', { level: 3 }).map((el) => el.textContent?.trim());
    expect(headings).toContain('Pemesanan');
    expect(headings).toContain('Persetujuan');
    expect(headings).toContain('Penugasan');
    expect(headings).toContain('Pengiriman');
    expect(headings).toContain('Pembayaran');
    expect(headings).toContain('Selesai');
    expect(headings).toContain('Dibatalkan');
    expect(headings).toContain('Gagal');
    expect(headings).toHaveLength(8);
  });
});

describe('WorktreeFlow — node order', () => {
  it('renders main stages in correct vertical order', () => {
    render(<WorktreeFlow />);

    const headings = getMainStageLabels();

    const idx = (label: string) => headings.indexOf(label);
    expect(idx('Pemesanan')).toBeGreaterThanOrEqual(0);
    expect(idx('Persetujuan')).toBeGreaterThan(idx('Pemesanan'));
    expect(idx('Penugasan')).toBeGreaterThan(idx('Persetujuan'));
    expect(idx('Pengiriman')).toBeGreaterThan(idx('Penugasan'));
    expect(idx('Pembayaran')).toBeGreaterThan(idx('Pengiriman'));
    expect(idx('Selesai')).toBeGreaterThan(idx('Pembayaran'));
  });
});

describe('WorktreeFlow — StatusBadge labels', () => {
  it('shows correct StatusBadge label for each main stage', () => {
    render(<WorktreeFlow />);

    // New → Baru, Confirmed → Dikonfirmasi, Assigned → Ditugaskan,
    // Delivered → Terkirim, Paid → Lunas, Completed → Selesai
    expect(screen.getByText('Baru')).toBeInTheDocument();
    expect(screen.getByText('Dikonfirmasi')).toBeInTheDocument();
    expect(screen.getByText('Ditugaskan')).toBeInTheDocument();
    expect(screen.getByText('Terkirim')).toBeInTheDocument();
    expect(screen.getByText('Lunas')).toBeInTheDocument();
    // 'Selesai' appears twice: stage heading + StatusBadge label for Completed
    expect(screen.getAllByText('Selesai').length).toBeGreaterThanOrEqual(2);
  });
});

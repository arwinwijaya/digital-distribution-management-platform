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
import { render, screen, fireEvent } from '@testing-library/react';
import WorktreeFlow from '@/components/WorktreeFlow';

jest.mock('next/link', () => {
  const React = require('react');
  return React.forwardRef(function Link(
    { children, href, ...rest }: { children: React.ReactNode; href: string } & Record<string, unknown>,
    ref: React.Ref<HTMLAnchorElement>,
  ) {
    return <a href={href} ref={ref} {...rest}>{children}</a>;
  });
});

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

// ============================================
// RED Cycle 1 — Panel detail on node click
// ============================================
describe('WorktreeFlow — panel detail on click', () => {
  it('shows panel detail when admin clicks Persetujuan', () => {
    render(<WorktreeFlow role="admin" />);

    // No panel before click
    expect(screen.queryByTestId('worktree-panel')).not.toBeInTheDocument();

    // Click Persetujuan node
    const node = screen.getByTestId('worktree-node-approval');
    fireEvent.click(node);

    // Panel should appear
    const panel = screen.getByTestId('worktree-panel');
    expect(panel).toBeInTheDocument();

    // Panel contains description
    expect(panel).toHaveTextContent(/meninjau dan menyetujui/i);

    // Panel contains StatusBadge with "Dikonfirmasi"
    expect(panel).toHaveTextContent('Dikonfirmasi');

    // Panel contains role badge "admin"
    expect(panel).toHaveTextContent('admin');

    // Panel contains "Buka menu" link with correct href
    const bukaMenu = screen.getByRole('link', { name: /buka menu/i });
    expect(bukaMenu).toHaveAttribute('href', '/admin/orders');
  });
});

// ============================================
// RED Cycle 2 — Deep link access control
// ============================================
describe('WorktreeFlow — deep link access control', () => {
  it('outlet cannot see Buka menu on Persetujuan (access denied)', () => {
    render(<WorktreeFlow role="outlet" />);

    // Click Persetujuan node
    fireEvent.click(screen.getByTestId('worktree-node-approval'));

    // Panel opens
    expect(screen.getByTestId('worktree-panel')).toBeInTheDocument();

    // Buka menu should NOT be in document — outlet not in accessRoles for /admin/orders
    expect(screen.queryByRole('link', { name: /buka menu/i })).not.toBeInTheDocument();
  });

  it('admin can see Buka menu on Persetujuan with href /admin/orders', () => {
    render(<WorktreeFlow role="admin" />);

    fireEvent.click(screen.getByTestId('worktree-node-approval'));

    const bukaMenu = screen.getByRole('link', { name: /buka menu/i });
    expect(bukaMenu).toBeInTheDocument();
    expect(bukaMenu).toHaveAttribute('href', '/admin/orders');
  });

  it('driver can see Buka menu on Pengiriman with href /delivery', () => {
    render(<WorktreeFlow role="driver" />);

    fireEvent.click(screen.getByTestId('worktree-node-delivery'));

    const bukaMenu = screen.getByRole('link', { name: /buka menu/i });
    expect(bukaMenu).toBeInTheDocument();
    expect(bukaMenu).toHaveAttribute('href', '/delivery');
  });
});

// ============================================
// RED Cycle 3 — Role-based highlight (all 7 roles)
// ============================================
const ROLE_HIGHLIGHTS: Record<string, string[]> = {
  outlet: ['order'],
  sales: ['order', 'assignment'],
  driver: ['delivery'],
  admin: ['approval', 'assignment', 'payment'],
  finance: ['payment'],
  supplier: [],
  platform_owner: [],
};

const STAGE_IDS = ['order', 'approval', 'assignment', 'delivery', 'payment', 'done'];

describe('WorktreeFlow — role-based highlight', () => {
  it.each([
    ['admin', ['approval', 'assignment', 'payment']],
    ['outlet', ['order']],
    ['sales', ['order', 'assignment']],
    ['driver', ['delivery']],
    ['finance', ['payment']],
    ['supplier', []],
    ['platform_owner', []],
  ])('role=%s highlights %j and dims the rest', (role, highlighted) => {
    render(<WorktreeFlow role={role} />);

    STAGE_IDS.forEach((id) => {
      const node = screen.getByTestId(`worktree-node-${id}`);
      // Class applied to node's grandparent: flex flex-col items-center w-full + highlight
      const card = node.parentElement;
      const cls = card?.getAttribute('class') ?? '';

      if ((highlighted as string[]).includes(id)) {
        expect(cls).toContain('bg-primary-50');
      } else if ((highlighted as string[]).length > 0) {
        expect(cls).toContain('opacity-60');
      } else {
        // No highlight at all for this role (supplier/platform_owner)
        expect(cls).not.toContain('bg-primary-50');
        expect(cls).not.toContain('opacity-60');
      }
    });
  });
});

// ============================================
// RED Cycle 4 — Panel toggle (click same node closes)
// ============================================
describe('WorktreeFlow — panel toggle', () => {
  it('closes panel when clicking the same node again', () => {
    render(<WorktreeFlow role="admin" />);

    const node = screen.getByTestId('worktree-node-approval');

    // Open
    fireEvent.click(node);
    expect(screen.getByTestId('worktree-panel')).toBeInTheDocument();

    // Close (toggle)
    fireEvent.click(node);
    expect(screen.queryByTestId('worktree-panel')).not.toBeInTheDocument();
  });
});

// ============================================
// RED Cycle 5 — Panel closes on outside click
// ============================================
describe('WorktreeFlow — outside click closes panel', () => {
  it('closes panel when clicking document body', () => {
    render(<WorktreeFlow role="admin" />);

    // Open
    fireEvent.click(screen.getByTestId('worktree-node-approval'));
    expect(screen.getByTestId('worktree-panel')).toBeInTheDocument();

    // Click outside (document body)
    fireEvent.mouseDown(document.body);

    // Panel should close
    expect(screen.queryByTestId('worktree-panel')).not.toBeInTheDocument();
  });
});

// ============================================
// RED Cycle 6 — Responsive layout classes
// ============================================
describe('WorktreeFlow — responsive layout', () => {
  it('container has flex-col lg:flex-row and panel has lg:w-80', () => {
    render(<WorktreeFlow role="admin" />);

    // Open panel
    fireEvent.click(screen.getByTestId('worktree-node-approval'));

    const container = screen.getByTestId('worktree-container');
    const panel = screen.getByTestId('worktree-panel');

    expect(container.className).toContain('flex-col');
    expect(container.className).toContain('lg:flex-row');
    expect(panel.className).toContain('lg:w-80');
    expect(panel.className).toContain('shrink-0');
  });
});

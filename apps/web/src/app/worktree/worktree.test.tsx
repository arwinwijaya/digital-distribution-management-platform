/**
 * worktree.test.tsx — Worktree page integration tests.
 *
 * Verifies (Task 4 — integration tests + final verification):
 *  - Unauthenticated access shows login prompt, no WorktreeFlow
 *  - Authenticated access renders PageHeader + WorktreeFlow (6 stages + 2 terminals)
 *  - No login prompt when authenticated
 *  - Page passes resolved role to WorktreeFlow (highlight integration)
 */
import React from 'react';
import '@testing-library/jest-dom';
import { fireEvent, render, screen, waitFor } from '@testing-library/react';

jest.mock('next/navigation', () => ({
  useRouter: () => ({ replace: jest.fn(), push: jest.fn() }),
  usePathname: () => '/worktree',
}));

jest.mock('next/link', () => {
  const React = require('react');
  return React.forwardRef(function Link(
    { children, href, ...rest }: { children: React.ReactNode; href: string } & Record<string, unknown>,
    ref: React.Ref<HTMLAnchorElement>,
  ) {
    return <a href={href} ref={ref} {...rest}>{children}</a>;
  });
});

jest.mock('@/lib/api', () => ({
  getStoredToken: jest.fn(() => null),
  apiUrl: (p: string) => `http://localhost:8000/api${p}`,
  authHeaders: (token: string) => ({ Authorization: `Bearer ${token}` }),
}));

jest.mock('@/dummy/store', () => ({
  useDummyStore: { getState: () => ({ isDummy: false }) },
}));

let mockGetStoredToken: jest.Mock;

beforeEach(() => {
  localStorage.clear();
  mockGetStoredToken = require('@/lib/api').getStoredToken;
  mockGetStoredToken.mockReturnValue(null);
});

async function renderWorktreePage() {
  const { default: WorktreePage } = await import('@/app/worktree/page');
  render(<WorktreePage />);
}

describe('Worktree page — unauthenticated', () => {
  it('shows login prompt when no token', async () => {
    await renderWorktreePage();

    await waitFor(() => {
      expect(screen.getAllByText(/Masuk/i).length).toBeGreaterThan(0);
    });
  });

  it('does not show WorktreeFlow placeholder when not authenticated', async () => {
    await renderWorktreePage();

    await waitFor(() => {
      expect(screen.getAllByText(/Masuk/i).length).toBeGreaterThan(0);
    });
    expect(screen.queryByText('Pemesanan')).not.toBeInTheDocument();
  });
});

describe('Worktree page — authenticated', () => {
  let originalFetch: typeof fetch | undefined;

  beforeEach(() => {
    mockGetStoredToken.mockReturnValue('dummy-token');
    // Mock fetch for /auth/me so usePageRole doesn't fail
    originalFetch = globalThis.fetch;
    globalThis.fetch = jest.fn(async () =>
      ({ ok: true, status: 200, json: async () => ({ data: { role: 'admin' } }) }) as Response,
    );
  });

  afterEach(() => {
    if (originalFetch !== undefined) {
      globalThis.fetch = originalFetch;
    }
  });

  it('renders PageHeader with Worktree title', async () => {
    await renderWorktreePage();

    await waitFor(() => {
      expect(screen.getByRole('heading', { name: /Worktree/i })).toBeInTheDocument();
    });
  });

  it('renders the worktree flow', async () => {
    await renderWorktreePage();

    await waitFor(() => {
      expect(screen.getByText('Pemesanan')).toBeInTheDocument();
    });
  });

  it('does not show login prompt', async () => {
    await renderWorktreePage();

    await waitFor(() => {
      expect(screen.getByText('Pemesanan')).toBeInTheDocument();
    });
    expect(screen.queryByText(/Masuk/i)).not.toBeInTheDocument();
  });

  it('renders all 6 stages plus 2 terminal nodes', async () => {
    await renderWorktreePage();

    await waitFor(() => {
      expect(screen.getByText('Pemesanan')).toBeInTheDocument();
    });

    expect(screen.getByText('Persetujuan')).toBeInTheDocument();
    expect(screen.getByText('Penugasan')).toBeInTheDocument();
    expect(screen.getByText('Pengiriman')).toBeInTheDocument();
    expect(screen.getByText('Pembayaran')).toBeInTheDocument();
    expect(screen.getAllByText('Selesai').length).toBeGreaterThanOrEqual(1);
    expect(screen.getByText('Dibatalkan')).toBeInTheDocument();
    expect(screen.getByText('Gagal')).toBeInTheDocument();
  });

  it('wires page role into WorktreeFlow: admin highlight visible in page context', async () => {
    await renderWorktreePage();

    await waitFor(() => {
      expect(screen.getByText('Persetujuan')).toBeInTheDocument();
    });

    const approvalNode = screen.getByTestId('worktree-node-approval');
    expect(approvalNode).toBeInTheDocument();
    // Admin role (from mocked /auth/me) highlights Persetujuan. The role arrives
    // asynchronously, so wait for the highlight instead of asserting immediately
    // (avoids a race when the full suite is under load).
    await waitFor(() => {
      expect(approvalNode.parentElement?.className).toMatch(/bg-primary-50/);
    });
  });

  it('opens detail panel on node click in page context', async () => {
    await renderWorktreePage();

    await waitFor(() => {
      expect(screen.getByText('Persetujuan')).toBeInTheDocument();
    });

    fireEvent.click(screen.getByTestId('worktree-node-approval'));
    expect(screen.getByTestId('worktree-panel')).toBeInTheDocument();
  });
});

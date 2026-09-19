/**
 * worktree.test.tsx — Worktree page integration tests.
 *
 * Verifies:
 *  - Unauthenticated access shows login prompt
 *  - Authenticated access renders PageHeader + placeholder for WorktreeFlow
 */
import React from 'react';
import '@testing-library/jest-dom';
import { render, screen, waitFor } from '@testing-library/react';

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
    expect(screen.queryByTestId('worktree-flow-placeholder')).not.toBeInTheDocument();
  });
});

describe('Worktree page — authenticated', () => {
  beforeEach(() => {
    mockGetStoredToken.mockReturnValue('dummy-token');
  });

  it('renders PageHeader with Worktree title', async () => {
    await renderWorktreePage();

    await waitFor(() => {
      expect(screen.getByRole('heading', { name: /Worktree/i })).toBeInTheDocument();
    });
  });

  it('shows the worktree flow placeholder', async () => {
    await renderWorktreePage();

    await waitFor(() => {
      expect(screen.getByTestId('worktree-flow-placeholder')).toBeInTheDocument();
    });
  });

  it('does not show login prompt', async () => {
    await renderWorktreePage();

    await waitFor(() => {
      expect(screen.getByTestId('worktree-flow-placeholder')).toBeInTheDocument();
    });
    expect(screen.queryByText(/Masuk/i)).not.toBeInTheDocument();
  });
});

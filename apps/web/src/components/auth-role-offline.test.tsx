/**
 * auth-role-offline.test.tsx — integration tests for dummy-mode offline role
 *
 * Cycle 1: Sidebar skips /auth/me when dummy ON, uses localStorage('ddp_role')
 * Cycle 2: LoginForm persists ddp_role to localStorage on submit
 * Cycle 3: Regression guard — Sidebar still calls /auth/me when dummy OFF
 */
import React from 'react';
import '@testing-library/jest-dom';
import { render, screen, waitFor, fireEvent, act } from '@testing-library/react';
import { useDummyStore } from '@/dummy/store';
import { setDummyGenerator } from '@/dummy/store';

/* ------------------------------------------------------------------ */
/* Mock next/navigation so usePathname returns a stable pathname.     */
/* ------------------------------------------------------------------ */
jest.mock('next/navigation', () => ({
  usePathname: () => '/dashboard',
}));

/* ------------------------------------------------------------------ */
/* Mock next/link to render a plain <a>.                              */
/* ------------------------------------------------------------------ */
jest.mock('next/link', () => {
  const React = require('react');
  return React.forwardRef(function Link(
    { children, href, ...rest }: { children: React.ReactNode; href: string } & Record<string, unknown>,
    ref: React.Ref<HTMLAnchorElement>,
  ) {
    return <a href={href} ref={ref} {...rest}>{children}</a>;
  });
});

beforeEach(() => {
  localStorage.clear();
  useDummyStore.getState().reset();
  localStorage.clear();
});

afterEach(() => {
  jest.restoreAllMocks();
});

/* ------------------------------------------------------------------ */
/* Cycle 1 — Sidebar skips /auth/me when Dummy ON                     */
/* ------------------------------------------------------------------ */
describe('Cycle 1 — Sidebar offline role when dummy ON', () => {
  it('never calls /auth/me and renders finance nav items from localStorage ddp_role', async () => {
    // Arrange: set up localStorage with token + finance role
    localStorage.setItem('ddp_token', 'test-token-123');
    localStorage.setItem('ddp_role', 'finance');

    // Arrange: turn dummy store ON
    setDummyGenerator(() => ({ stub: true }));
    useDummyStore.getState().toggle();
    expect(useDummyStore.getState().isDummy).toBe(true);

    // Arrange: mock global fetch
    const fetchCalls: string[] = [];
    const originalFetch = global.fetch;
    global.fetch = jest.fn(async (input: RequestInfo | URL) => {
      fetchCalls.push(String(input));
      return { ok: false, status: 401, json: async () => ({}) } as Response;
    });

    try {
      const { default: Sidebar } = await import('@/components/Sidebar');
      render(<Sidebar />);

      // Assert: /auth/me was NEVER called
      await waitFor(() => {
        // Wait a tick for any potential async fetch — then assert absence
        const authMeCalls = fetchCalls.filter((url) => url.includes('/auth/me'));
        expect(authMeCalls).toHaveLength(0);
      });

      // Assert: finance-flagged nav items render
      await waitFor(() => {
        expect(screen.getByText('Dasbor')).toBeInTheDocument();
        expect(screen.getByText('Invoice')).toBeInTheDocument();
        expect(screen.getByText('Pembayaran')).toBeInTheDocument();
      });

      // Assert: non-finance items do NOT render
      expect(screen.queryByText('Pesanan')).not.toBeInTheDocument();
      expect(screen.queryByText('Produk')).not.toBeInTheDocument();
      expect(screen.queryByText('Outlet')).not.toBeInTheDocument();
    } finally {
      global.fetch = originalFetch;
    }
  });
});

/* ------------------------------------------------------------------ */
/* Cycle 2 — LoginForm persists ddp_role on submit                     */
/* ------------------------------------------------------------------ */
describe('Cycle 2 — LoginForm persists ddp_role on submit', () => {
  it('writes ddp_role and ddp_token to localStorage after successful login', async () => {
    // Arrange: mock fetch to return a successful login response
    const originalFetch = global.fetch;
    global.fetch = jest.fn(async () => {
      return {
        ok: true,
        status: 200,
        json: async () => ({
          data: {
            token: 't-token',
            user: { role: 'finance' },
          },
        }),
      } as Response;
    });

    try {
      const { default: LoginForm } = await import('@/components/LoginForm');
      const onLogin = jest.fn();
      render(<LoginForm onLogin={onLogin} />);

      // Fill in email and password fields
      const emailInput = screen.getByPlaceholderText('nama@perusahaan.com');
      const passwordInput = screen.getByPlaceholderText('Masukkan kata sandi');

      fireEvent.change(emailInput, { target: { value: 'finance@test.com' } });
      fireEvent.change(passwordInput, { target: { value: 'password123' } });

      // Submit the form
      fireEvent.click(screen.getByRole('button', { name: /masuk/i }));

      // Assert: localStorage contains both token and role
      await waitFor(() => {
        expect(localStorage.getItem('ddp_token')).toBe('t-token');
        expect(localStorage.getItem('ddp_role')).toBe('finance');
      });

      // Assert: onLogin was called with token and role
      expect(onLogin).toHaveBeenCalledWith('t-token', 'finance');
    } finally {
      global.fetch = originalFetch;
    }
  });
});

/* ------------------------------------------------------------------ */
/* Cycle 3 — Regression guard: Sidebar still calls /auth/me OFF       */
/* ------------------------------------------------------------------ */
describe('Cycle 3 — Regression guard: Sidebar calls /auth/me when dummy OFF', () => {
  it('calls /auth/me and resolves role from server when dummy is OFF', async () => {
    // Arrange: token in localStorage, dummy OFF, no ddp_role stored
    localStorage.setItem('ddp_token', 'test-token-456');
    expect(useDummyStore.getState().isDummy).toBe(false);

    // Arrange: mock fetch — first call is /auth/me, second could be anything
    const fetchCalls: string[] = [];
    const originalFetch = global.fetch;
    global.fetch = jest.fn(async (input: RequestInfo | URL) => {
      fetchCalls.push(String(input));
      return {
        ok: true,
        status: 200,
        json: async () => ({
          data: { role: 'admin' },
        }),
      } as Response;
    });

    try {
      const { default: Sidebar } = await import('@/components/Sidebar');
      render(<Sidebar />);

      // Assert: /auth/me IS called
      await waitFor(() => {
        const authMeCalls = fetchCalls.filter((url) => url.includes('/auth/me'));
        expect(authMeCalls.length).toBeGreaterThanOrEqual(1);
      });
    } finally {
      global.fetch = originalFetch;
    }
  });
});

/* ------------------------------------------------------------------ */
/* Cycle 4 — Sidebar menu is empty until login                        */
/* ------------------------------------------------------------------ */
describe('Cycle 4 — Sidebar menu empty until login', () => {
  it('shows no nav items when dummy OFF and no token is stored', async () => {
    expect(useDummyStore.getState().isDummy).toBe(false);

    const { default: Sidebar } = await import('@/components/Sidebar');
    render(<Sidebar />);

    await waitFor(() => {
      expect(screen.queryByText('Dasbor')).not.toBeInTheDocument();
      expect(screen.queryByText('Data Intelligence')).not.toBeInTheDocument();
      expect(screen.queryByText('Pesanan')).not.toBeInTheDocument();
    });
  });

  it('shows no nav items when dummy ON and no token/role is stored', async () => {
    setDummyGenerator(() => ({ stub: true }));
    useDummyStore.getState().toggle();
    expect(useDummyStore.getState().isDummy).toBe(true);

    const { default: Sidebar } = await import('@/components/Sidebar');
    render(<Sidebar />);

    await waitFor(() => {
      expect(screen.queryByText('Dasbor')).not.toBeInTheDocument();
      expect(screen.queryByText('Data Intelligence')).not.toBeInTheDocument();
    });
  });

  it('reveals admin nav items after an in-page login event when dummy ON', async () => {
    setDummyGenerator(() => ({ stub: true }));
    useDummyStore.getState().toggle();
    expect(useDummyStore.getState().isDummy).toBe(true);

    const { default: Sidebar } = await import('@/components/Sidebar');
    render(<Sidebar />);

    await waitFor(() => {
      expect(screen.queryByText('Dasbor')).not.toBeInTheDocument();
    });

    // Simulate LoginForm.submit: persist token/role, then broadcast the event.
    localStorage.setItem('ddp_token', 'tok-inline');
    localStorage.setItem('ddp_role', 'admin');
    act(() => {
      window.dispatchEvent(
        new CustomEvent('ddp-auth-change', { detail: { token: 'tok-inline', role: 'admin' } }),
      );
    });

    await waitFor(() => {
      expect(screen.getByText('Data Intelligence')).toBeInTheDocument();
      expect(screen.getByText('Dasbor')).toBeInTheDocument();
    });
  });
});

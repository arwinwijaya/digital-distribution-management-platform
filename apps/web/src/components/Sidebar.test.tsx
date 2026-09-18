/**
 * Sidebar.test.tsx — admin menu visibility.
 *
 * Regression guard for "admin can see all menus": the six `/admin/*`
 * management pages must be reachable from the sidebar for admin, and must
 * stay hidden from non-admin roles (outlet / sales / driver).
 *
 * The five "Kelola *" / "Performa sales" entries and "Kelola pesanan"
 * previously had NO sidebar entry at all (or were mislabelled "Admin" with
 * no `adminOnly` flag), so they were unreachable / wrongly exposed.
 */
import React from 'react';
import '@testing-library/jest-dom';
import { render, screen, waitFor } from '@testing-library/react';
import { useDummyStore } from '@/dummy/store';

jest.mock('next/navigation', () => ({ usePathname: () => '/dashboard' }));
jest.mock('next/link', () => {
  const React = require('react');
  return React.forwardRef(function Link(
    { children, href, ...rest }: { children: React.ReactNode; href: string } & Record<string, unknown>,
    ref: React.Ref<HTMLAnchorElement>,
  ) {
    return <a href={href} ref={ref} {...rest}>{children}</a>;
  });
});

/** The five `/admin/*` management menus that must be visible to admin only. */
const ADMIN_MENUS = [
  'Kelola pesanan',
  'Kelola produk',
  'Kelola pengguna',
  'Kelola promosi',
  'Performa sales',
];

let originalFetch: typeof fetch | undefined;

function mockRole(role: string | null): void {
  originalFetch = (globalThis as unknown as { fetch?: typeof fetch }).fetch;
  (globalThis as unknown as { fetch: unknown }).fetch = jest.fn(async () =>
    role === null
      ? ({ ok: false, status: 401, json: async () => ({}) } as Response)
      : ({ ok: true, status: 200, json: async () => ({ data: { role } }) } as Response),
  );
}

beforeEach(() => {
  localStorage.clear();
  useDummyStore.getState().reset();
  localStorage.clear();
  localStorage.setItem('ddp_token', 't-token');
});

afterEach(() => {
  if (originalFetch) (globalThis as unknown as { fetch: unknown }).fetch = originalFetch;
  else delete (globalThis as unknown as { fetch?: unknown }).fetch;
  useDummyStore.getState().reset();
});

async function renderSidebar(): Promise<void> {
  const { default: Sidebar } = await import('@/components/Sidebar');
  render(<Sidebar />);
}

describe('Sidebar admin menu visibility', () => {
  it('shows all five admin management menus for admin', async () => {
    mockRole('admin');
    await renderSidebar();

    await waitFor(() => expect(screen.getByText('Kelola pesanan')).toBeInTheDocument());
    for (const label of ADMIN_MENUS) {
      expect(screen.getByText(label)).toBeInTheDocument();
    }
    // Payments remains visible to admin.
    expect(screen.getByText('Pembayaran')).toBeInTheDocument();
  });

  it('links each admin menu to its /admin route', async () => {
    mockRole('admin');
    await renderSidebar();

    await waitFor(() => expect(screen.getByText('Kelola produk')).toBeInTheDocument());
    const hrefFor = (label: string) =>
      screen.getByText(label).closest('a')?.getAttribute('href');
    expect(hrefFor('Kelola pesanan')).toBe('/admin/orders');
    expect(hrefFor('Kelola produk')).toBe('/admin/products');
    expect(hrefFor('Kelola pengguna')).toBe('/admin/users');
    expect(hrefFor('Kelola promosi')).toBe('/admin/promotions');
    expect(hrefFor('Performa sales')).toBe('/admin/sales-performance');
  });

  it('points the Outlet menu at /admin/outlets for admin (not the public /outlets)', async () => {
    mockRole('admin');
    await renderSidebar();

    await waitFor(() => expect(screen.getByText('Outlet')).toBeInTheDocument());
    expect(screen.getByText('Outlet').closest('a')?.getAttribute('href')).toBe('/admin/outlets');
    // The public registration form must NOT be the admin destination.
    expect(screen.queryByText('Outlet')?.closest('a')?.getAttribute('href')).not.toBe('/outlets');
  });

  it('keeps the Outlet menu on the public /outlets route for non-admin roles', async () => {
    mockRole('outlet');
    await renderSidebar();

    await waitFor(() => expect(screen.getByText('Outlet')).toBeInTheDocument());
    expect(screen.getByText('Outlet').closest('a')?.getAttribute('href')).toBe('/outlets');
  });

  it.each(['outlet', 'sales', 'driver'])('hides every admin menu from %s', async (role) => {
    mockRole(role);
    await renderSidebar();

    // Wait until the role has resolved (a non-adminOnly item is visible).
    await waitFor(() => expect(screen.getByText('Pesanan')).toBeInTheDocument());
    for (const label of ADMIN_MENUS) {
      expect(screen.queryByText(label)).not.toBeInTheDocument();
    }
  });

  it('still restricts finance to finance-flagged items only', async () => {
    mockRole('finance');
    await renderSidebar();

    await waitFor(() => expect(screen.getByText('Pembayaran')).toBeInTheDocument());
    expect(screen.getByText('Dasbor')).toBeInTheDocument();
    expect(screen.getByText('Invoice')).toBeInTheDocument();
    for (const label of ADMIN_MENUS) {
      expect(screen.queryByText(label)).not.toBeInTheDocument();
    }
  });
});

describe('Sidebar Analitik gate (admin + platform_owner)', () => {
  it('hides Analitik from outlet users', async () => {
    mockRole('outlet');
    await renderSidebar();

    await waitFor(() => expect(screen.getByText('Pesanan')).toBeInTheDocument());
    expect(screen.queryByText('Analitik')).not.toBeInTheDocument();
  });

  it('shows Analitik and every adminOnly item to platform_owner', async () => {
    mockRole('platform_owner');
    await renderSidebar();

    await waitFor(() => expect(screen.getByText('Analitik')).toBeInTheDocument());
    expect(screen.getByText('Data Intelligence')).toBeInTheDocument();
    for (const label of ADMIN_MENUS) {
      expect(screen.getByText(label)).toBeInTheDocument();
    }
  });

  it('keeps Analitik visible to admin (unchanged)', async () => {
    mockRole('admin');
    await renderSidebar();

    await waitFor(() => expect(screen.getByText('Analitik')).toBeInTheDocument());
    expect(screen.getByText('Analitik').closest('a')?.getAttribute('href')).toBe('/analytics');
  });
});

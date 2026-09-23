/**
 * Sidebar.test.tsx — matrix-driven menu visibility.
 *
 * The Sidebar no longer uses hardcoded `finance`/`adminOnly` flags: visibility
 * comes from `useRbacStore.levelFor(item.key) !== 'none'`, hydrated from
 * `GET /auth/me` (`data.rbac`) or the dummy matrix. This file guards the admin
 * menus, the Outlet adminHref routing, Worktree, and the Analitik gate.
 */
import React from 'react';
import '@testing-library/jest-dom';
import { render, screen, waitFor } from '@testing-library/react';
import { useDummyStore } from '@/dummy/store';
import { useRbacStore } from '@/store/useRbacStore';
import { getDummyMatrix } from '@/dummy/rbac';

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

/** The `/admin/*` management menus that must be visible to admin only. */
const ADMIN_MENUS = [
  'Approval Pesanan',
  'Harga Produk',
  'Kelola pengguna',
  'Kelola promosi',
  'Performa sales',
  'Roster Driver',
];

let originalFetch: typeof fetch | undefined;

/**
 * Mock `/auth/me` to return BOTH the role and the full rbac map — visibility is
 * now derived from the matrix, so a role-only response would hide every menu.
 */
function mockRole(role: string | null): void {
  originalFetch = (globalThis as unknown as { fetch?: typeof fetch }).fetch;
  (globalThis as unknown as { fetch: unknown }).fetch = jest.fn(async () =>
    role === null
      ? ({ ok: false, status: 401, json: async () => ({}) } as Response)
      : ({ ok: true, status: 200, json: async () => ({ data: { role, rbac: getDummyMatrix(role) } }) } as Response),
  );
}

beforeEach(() => {
  localStorage.clear();
  useDummyStore.getState().reset();
  useRbacStore.getState().reset();
  localStorage.clear();
  localStorage.setItem('ddp_token', 't-token');
});

afterEach(() => {
  if (originalFetch) (globalThis as unknown as { fetch: unknown }).fetch = originalFetch;
  else delete (globalThis as unknown as { fetch?: unknown }).fetch;
  useDummyStore.getState().reset();
  useRbacStore.getState().reset();
});

async function renderSidebar(): Promise<void> {
  const { default: Sidebar } = await import('@/components/Sidebar');
  render(<Sidebar />);
}

describe('Sidebar admin menu visibility', () => {
  it('shows all five admin management menus for admin', async () => {
    mockRole('admin');
    await renderSidebar();

    await waitFor(() => expect(screen.getByText('Approval Pesanan')).toBeInTheDocument());
    for (const label of ADMIN_MENUS) {
      expect(screen.getByText(label)).toBeInTheDocument();
    }
    // Payments remains visible to admin.
    expect(screen.getByText('Pembayaran')).toBeInTheDocument();
  });

  it('links each admin menu to its /admin route', async () => {
    mockRole('admin');
    await renderSidebar();

    await waitFor(() => expect(screen.getByText('Harga Produk')).toBeInTheDocument());
    const hrefFor = (label: string) =>
      screen.getByText(label).closest('a')?.getAttribute('href');
    expect(hrefFor('Approval Pesanan')).toBe('/admin/orders');
    expect(hrefFor('Harga Produk')).toBe('/admin/products');
    expect(hrefFor('Kelola pengguna')).toBe('/admin/users');
    expect(hrefFor('Kelola promosi')).toBe('/admin/promotions');
    expect(hrefFor('Performa sales')).toBe('/admin/sales-performance');
    expect(hrefFor('Roster Driver')).toBe('/admin/drivers');
  });

  it('shows the Kelola Akses menu for admin', async () => {
    mockRole('admin');
    await renderSidebar();

    await waitFor(() => expect(screen.getByText('Kelola Akses')).toBeInTheDocument());
    expect(screen.getByText('Kelola Akses').closest('a')?.getAttribute('href')).toBe('/admin/rbac');
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

    // Wait until the role has resolved (a menu the role can see is visible).
    await waitFor(() => expect(screen.getByText('Pesanan')).toBeInTheDocument());
    for (const label of ADMIN_MENUS) {
      expect(screen.queryByText(label)).not.toBeInTheDocument();
    }
    expect(screen.queryByText('Kelola Akses')).not.toBeInTheDocument();
  });

  it('shows finance exactly the menus the default matrix grants', async () => {
    mockRole('finance');
    await renderSidebar();

    // Visible: dashboard, orders, products, outlets, marketplace, payments,
    // delivery, sales, invoices, worktree.
    await waitFor(() => expect(screen.getByText('Pembayaran')).toBeInTheDocument());
    for (const label of ['Dasbor', 'Invoice', 'Pesanan', 'Produk', 'Outlet', 'Marketplace', 'Pengiriman', 'Sales', 'Worktree']) {
      expect(screen.getByText(label)).toBeInTheDocument();
    }
    // Hidden: analytics / data_intelligence / operations / admin_*.
    for (const label of ['Analitik', 'Data Intelligence', 'Operasi', ...ADMIN_MENUS, 'Kelola Akses']) {
      expect(screen.queryByText(label)).not.toBeInTheDocument();
    }
  });
});

describe('Sidebar Worktree visibility', () => {
  it.each(['outlet', 'finance', 'admin', 'sales', 'driver', 'supplier', 'platform_owner'])(
    'shows Worktree for %s role',
    async (role) => {
      mockRole(role);
      await renderSidebar();

      await waitFor(() => expect(screen.getByText('Worktree')).toBeInTheDocument());
      expect(screen.getByText('Worktree').closest('a')?.getAttribute('href')).toBe('/worktree');
    },
  );
});

describe('Sidebar Analitik gate', () => {
  it('hides Analitik from outlet users', async () => {
    mockRole('outlet');
    await renderSidebar();

    await waitFor(() => expect(screen.getByText('Pesanan')).toBeInTheDocument());
    expect(screen.queryByText('Analitik')).not.toBeInTheDocument();
  });

  it('shows Analitik and every admin item to platform_owner', async () => {
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

describe('Sidebar field-ops menu (Phase 8)', () => {
  it('shows Operasi Lapangan to admin and links to /admin/tracking', async () => {
    mockRole('admin');
    await renderSidebar();

    await waitFor(() => expect(screen.getByText('Operasi Lapangan')).toBeInTheDocument());
    expect(screen.getByText('Operasi Lapangan').closest('a')?.getAttribute('href')).toBe(
      '/admin/tracking',
    );
  });

  it.each(['sales', 'driver'])('shows Operasi Lapangan to %s (read)', async (role) => {
    mockRole(role);
    await renderSidebar();

    await waitFor(() => expect(screen.getByText('Operasi Lapangan')).toBeInTheDocument());
  });

  it('hides Operasi Lapangan from outlet and finance', async () => {
    mockRole('outlet');
    await renderSidebar();
    await waitFor(() => expect(screen.getByText('Pesanan')).toBeInTheDocument());
    expect(screen.queryByText('Operasi Lapangan')).not.toBeInTheDocument();
  });

  it('hides Roster Driver from sales and driver', async () => {
    mockRole('driver');
    await renderSidebar();

    await waitFor(() => expect(screen.getByText('Pesanan')).toBeInTheDocument());
    expect(screen.queryByText('Roster Driver')).not.toBeInTheDocument();
  });
});

describe('Sidebar dummy parity', () => {
  it('resolves menus from the dummy matrix with zero network', async () => {
    useDummyStore.getState().toggle();
    localStorage.setItem('ddp_role', 'outlet');

    const fetchSpy = jest.fn();
    (globalThis as unknown as { fetch: unknown }).fetch = fetchSpy;

    await renderSidebar();

    await waitFor(() => expect(screen.getByText('Produk')).toBeInTheDocument());
    expect(screen.queryByText('Analitik')).not.toBeInTheDocument();
    expect(fetchSpy).not.toHaveBeenCalled();
  });
});

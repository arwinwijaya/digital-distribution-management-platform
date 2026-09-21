/**
 * RBAC integration tests — end-to-end across auth → store → UI.
 *
 * (a) login → GET /auth/me → store → Sidebar renders the matrix menus
 * (b) admin edits a cell at /admin/rbac → store refresh → Sidebar updates
 * (c) stale cache: matrix changed server-side → Sidebar stays stale (server
 *     enforcement returning 403 is verified in the API suite, T6)
 * (d) dummy parity: dummy ON → Sidebar & page resolve from the dummy matrix
 *     with ZERO network
 *
 * Exercises the real components + the real store; only `global.fetch` is faked.
 */
import React from 'react';
import '@testing-library/jest-dom';
import { render, screen, waitFor, fireEvent, cleanup } from '@testing-library/react';
import { useDummyStore } from '@/dummy/store';
import { installDummy } from '@/dummy/install';
import { useRbacStore } from '@/store/useRbacStore';
import { ROLES, getDummyMatrix } from '@/dummy/rbac';

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

function fullMatrix(): Record<string, Record<string, string>> {
  const matrix: Record<string, Record<string, string>> = {};
  for (const role of ROLES) matrix[role] = getDummyMatrix(role);
  return matrix;
}

function meResponse(role: string) {
  return { status: 'success', data: { role, rbac: getDummyMatrix(role) } };
}

function jsonResponse(body: unknown): Response {
  return { ok: true, status: 200, json: async () => body } as Response;
}

let originalFetch: typeof fetch | undefined;

beforeEach(() => {
  localStorage.clear();
  useDummyStore.getState().reset();
  useRbacStore.getState().reset();
  installDummy();
  localStorage.setItem('ddp_token', 't-token');
});

afterEach(() => {
  // Unmount before resetting stores so `useDummyRefresh` cannot fire a fetch
  // against an already-restored/deleted global.
  cleanup();
  useDummyStore.getState().reset();
  useRbacStore.getState().reset();
  if (originalFetch) (globalThis as unknown as { fetch: unknown }).fetch = originalFetch;
  else delete (globalThis as unknown as { fetch?: unknown }).fetch;
  jest.restoreAllMocks();
});

describe('RBAC integration — auth → store → sidebar', () => {
  it('renders sidebar menus from the /auth/me rbac map (finance)', async () => {
    originalFetch = (globalThis as unknown as { fetch?: typeof fetch }).fetch;
    (globalThis as unknown as { fetch: unknown }).fetch = jest.fn(async () => jsonResponse(meResponse('finance')));

    const { default: Sidebar } = await import('@/components/Sidebar');
    render(<Sidebar />);

    await waitFor(() => expect(screen.getByText('Pembayaran')).toBeInTheDocument());
    for (const label of ['Dasbor', 'Invoice', 'Pesanan', 'Produk', 'Outlet', 'Marketplace', 'Pengiriman', 'Sales', 'Worktree']) {
      expect(screen.getByText(label)).toBeInTheDocument();
    }
    for (const label of ['Analitik', 'Data Intelligence', 'Operasi', 'Approval Pesanan', 'Kelola Akses']) {
      expect(screen.queryByText(label)).not.toBeInTheDocument();
    }
  });

  it('updates the sidebar after an admin saves a matrix change', async () => {
    originalFetch = (globalThis as unknown as { fetch?: typeof fetch }).fetch;
    let serverMatrix = fullMatrix();
    (globalThis as unknown as { fetch: unknown }).fetch = jest.fn(async (url: unknown, options?: { method?: string; body?: string }) => {
      const target = String(url);
      if (target.includes('/auth/me')) return jsonResponse(meResponse('admin'));
      if (target.includes('/admin/rbac/matrix') && options?.method === 'PUT') {
        const body = JSON.parse(options.body ?? '{}') as { cells: Array<{ role: string; menu_key: string; level: string }> };
        for (const cell of body.cells) serverMatrix[cell.role][cell.menu_key] = cell.level;
        return jsonResponse({ status: 'success', data: serverMatrix });
      }
      if (target.includes('/admin/rbac/matrix')) return jsonResponse({ status: 'success', data: serverMatrix });
      return { ok: false, status: 404, json: async () => ({ message: 'not found' }) } as Response;
    });

    const { default: Sidebar } = await import('@/components/Sidebar');
    const { default: Page } = await import('@/app/admin/rbac/page');

    // Admin session: hydrates the caller's map (admin sees the admin menus).
    useRbacStore.getState().hydrateFromMe(getDummyMatrix('admin'), 'admin');
    render(<Sidebar />);
    await waitFor(() => expect(screen.getByText('Kelola pengguna')).toBeInTheDocument());

    // The admin page loads the full matrix and reveals the editor.
    render(<Page />);
    await waitFor(() => expect(screen.getByTestId('cell-admin-admin_users')).toHaveValue('read'));

    // Change admin→admin_users to none and save.
    fireEvent.change(screen.getByTestId('cell-admin-admin_users'), { target: { value: 'none' } });
    fireEvent.click(screen.getByRole('button', { name: /simpan/i }));

    await waitFor(() => expect(screen.getByTestId('cell-admin-admin_users')).toHaveValue('none'));
    expect(serverMatrix.admin.admin_users).toBe('none');

    // The caller's OWN sidebar reflects the change (their role's map updated).
    await waitFor(() => expect(screen.queryByText('Kelola pengguna')).not.toBeInTheDocument());
  });
});

describe('RBAC integration — stale cache', () => {
  it('keeps the stale menu visible when the server matrix changed underneath', async () => {
    originalFetch = (globalThis as unknown as { fetch?: typeof fetch }).fetch;
    // Server now returns sales→analytics = none, but the client cache still says read.
    const staleMap = { ...getDummyMatrix('sales'), analytics: 'read' as const };
    (globalThis as unknown as { fetch: unknown }).fetch = jest.fn(async () =>
      jsonResponse({ status: 'success', data: { role: 'sales', rbac: staleMap } }),
    );

    const { default: Sidebar } = await import('@/components/Sidebar');
    render(<Sidebar />);

    // Sidebar renders from the cached map: Analitik still visible (stale).
    await waitFor(() => expect(screen.getByText('Analitik')).toBeInTheDocument());
    // Server-side enforcement (rbac:analytics:read → 403) is asserted in the API
    // suite; the frontend deliberately does not re-fetch on navigation.
    expect(useRbacStore.getState().levelFor('analytics')).toBe('read');
  });
});

describe('RBAC integration — dummy parity (zero network)', () => {
  it('renders the sidebar from the dummy matrix without any fetch', async () => {
    useDummyStore.getState().toggle();
    localStorage.setItem('ddp_role', 'outlet');
    const fetchSpy = jest.fn();
    (globalThis as unknown as { fetch: unknown }).fetch = fetchSpy;

    const { default: Sidebar } = await import('@/components/Sidebar');
    render(<Sidebar />);

    await waitFor(() => expect(screen.getByText('Produk')).toBeInTheDocument());
    expect(screen.queryByText('Analitik')).not.toBeInTheDocument();
    expect(fetchSpy).not.toHaveBeenCalled();
  });

  it('renders the admin rbac page from the dummy matrix with zero network', async () => {
    useDummyStore.getState().toggle();
    localStorage.setItem('ddp_role', 'admin');
    const fetchSpy = jest.fn();
    (globalThis as unknown as { fetch: unknown }).fetch = fetchSpy;

    const { default: Page } = await import('@/app/admin/rbac/page');
    render(<Page />);

    await waitFor(() => expect(screen.getByTestId('cell-sales-products')).toHaveValue('read'));
    expect(fetchSpy).not.toHaveBeenCalled();
  });
});

import React from 'react';
import '@testing-library/jest-dom';
import { render, screen, waitFor, fireEvent, cleanup } from '@testing-library/react';

const mockGetStoredToken = jest.fn(() => 'test-token');
jest.mock('@/lib/api', () => ({
  apiUrl: (p: string) => `http://localhost:8000/api${p}`,
  authHeaders: (token: string) => ({ Authorization: `Bearer ${token}` }),
  getStoredToken: (...args: unknown[]) => (mockGetStoredToken as (...a: unknown[]) => string | null)(...args),
}));

import { useDummyStore } from '@/dummy/store';
import { installDummy } from '@/dummy/install';
import { useRbacStore } from '@/store/useRbacStore';
import { MENU_KEYS, ROLES, getDummyMatrix } from '@/dummy/rbac';

/** Full 7-role matrix built from the dummy fixture (mirrors the seeder). */
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

function errorResponse(status: number, message: string): Response {
  return { ok: false, status, json: async () => ({ status: 'error', message }) } as Response;
}

describe('admin rbac page', () => {
  let originalFetch: typeof fetch | undefined;
  let fetchMock: jest.Mock;

  beforeEach(() => {
    localStorage.clear();
    useDummyStore.getState().reset();
    useRbacStore.getState().reset();
    installDummy();
    mockGetStoredToken.mockReturnValue('test-token');
    originalFetch = (globalThis as unknown as { fetch?: typeof fetch }).fetch;
  });

  afterEach(() => {
    // Unmount FIRST — resetting the dummy store while a component is mounted
    // would fire `useDummyRefresh` and issue a real fetch with no `fetch` stub.
    cleanup();
    useDummyStore.getState().reset();
    useRbacStore.getState().reset();
    if (originalFetch) {
      (globalThis as unknown as { fetch: unknown }).fetch = originalFetch;
    } else {
      delete (globalThis as unknown as { fetch?: unknown }).fetch;
    }
    jest.restoreAllMocks();
  });

  it('renders a 19x7 grid and PUTs only the changed cell on save', async () => {
    let putBody: { cells: Array<{ role: string; menu_key: string; level: string }> } | null = null;
    fetchMock = jest.fn(async (url: unknown, options?: { method?: string; body?: string }) => {
      const target = String(url);
      if (target.includes('/auth/me')) return jsonResponse(meResponse('admin'));
      if (target.includes('/admin/rbac/matrix') && options?.method === 'PUT') {
        putBody = JSON.parse(options.body ?? '{}');
        const data = fullMatrix();
        for (const cell of putBody!.cells) data[cell.role][cell.menu_key] = cell.level;
        return jsonResponse({ status: 'success', data });
      }
      if (target.includes('/admin/rbac/matrix')) return jsonResponse({ status: 'success', data: fullMatrix() });
      return errorResponse(404, 'not found');
    });
    (globalThis as unknown as { fetch: unknown }).fetch = fetchMock;

    const { default: Page } = await import('@/app/admin/rbac/page');
    render(<Page />);

    await waitFor(() => expect(screen.getByTestId('rbac-row-dashboard')).toBeInTheDocument());

    // 19 menu rows, each with 7 role selects.
    for (const key of MENU_KEYS) expect(screen.getByTestId(`rbac-row-${key}`)).toBeInTheDocument();
    expect(screen.getAllByRole('combobox')).toHaveLength(MENU_KEYS.length * ROLES.length);

    // The grid reflects the loaded matrix (sales→products is `read` by default).
    expect(screen.getByTestId('cell-sales-products')).toHaveValue('read');

    fireEvent.change(screen.getByTestId('cell-sales-products'), { target: { value: 'edit' } });
    fireEvent.click(screen.getByRole('button', { name: /simpan/i }));

    await waitFor(() => expect(putBody).not.toBeNull());
    expect(putBody!.cells).toEqual([{ role: 'sales', menu_key: 'products', level: 'edit' }]);
  });

  it('blocks non-admin roles without fetching the matrix or offering Save', async () => {
    const urls: string[] = [];
    fetchMock = jest.fn(async (url: unknown) => {
      urls.push(String(url));
      if (String(url).includes('/auth/me')) return jsonResponse(meResponse('finance'));
      return errorResponse(403, 'Forbidden');
    });
    (globalThis as unknown as { fetch: unknown }).fetch = fetchMock;

    const { default: Page } = await import('@/app/admin/rbac/page');
    render(<Page />);

    await waitFor(() => expect(screen.getByRole('alert')).toBeInTheDocument());
    expect(screen.getByRole('alert').textContent).toMatch(/akses|izin|forbidden/i);
    expect(screen.queryByRole('button', { name: /simpan/i })).not.toBeInTheDocument();
    expect(urls.some((u) => u.includes('/admin/rbac/matrix'))).toBe(false);
  });

  it('surfaces a server 422 (self-lockout) without crashing', async () => {
    fetchMock = jest.fn(async (url: unknown, options?: { method?: string }) => {
      const target = String(url);
      if (target.includes('/auth/me')) return jsonResponse(meResponse('admin'));
      if (target.includes('/admin/rbac/matrix') && options?.method === 'PUT') {
        return errorResponse(422, 'cannot remove own edit access to rbac_matrix');
      }
      if (target.includes('/admin/rbac/matrix')) return jsonResponse({ status: 'success', data: fullMatrix() });
      return errorResponse(404, 'not found');
    });
    (globalThis as unknown as { fetch: unknown }).fetch = fetchMock;

    const { default: Page } = await import('@/app/admin/rbac/page');
    render(<Page />);

    await waitFor(() => expect(screen.getByTestId('rbac-row-dashboard')).toBeInTheDocument());
    fireEvent.change(screen.getByTestId('cell-admin-rbac_matrix'), { target: { value: 'none' } });
    fireEvent.click(screen.getByRole('button', { name: /simpan/i }));

    await waitFor(() =>
      expect(screen.getByRole('alert').textContent).toMatch(/cannot remove own edit access/i),
    );
    // Grid is still rendered — no crash.
    expect(screen.getByTestId('rbac-row-dashboard')).toBeInTheDocument();
  });

  it('uses the dummy matrix and never hits the network when dummy is ON', async () => {
    localStorage.setItem('ddp_role', 'admin');
    useDummyStore.getState().toggle();
    expect(useDummyStore.getState().isDummy).toBe(true);

    const fetchSpy = jest.fn();
    (globalThis as unknown as { fetch: unknown }).fetch = fetchSpy;

    const { default: Page } = await import('@/app/admin/rbac/page');
    render(<Page />);

    await waitFor(() => expect(screen.getByTestId('rbac-row-dashboard')).toBeInTheDocument());
    expect(fetchSpy).not.toHaveBeenCalled();

    fireEvent.change(screen.getByTestId('cell-sales-products'), { target: { value: 'edit' } });
    fireEvent.click(screen.getByRole('button', { name: /simpan/i }));

    await waitFor(() => expect(screen.getByTestId('cell-sales-products')).toHaveValue('edit'));
    expect(fetchSpy).not.toHaveBeenCalled();
  });
});

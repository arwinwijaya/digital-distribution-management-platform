/**
 * useRbacStore.test.ts — RBAC Zustand store.
 *
 * Story 3/5: the store is the frontend source of truth for menu visibility.
 * It hydrates from `GET /auth/me` (data.rbac), loads the full matrix for the
 * admin page from `GET /admin/rbac/matrix`, and saves via PUT. In dummy mode
 * it must resolve everything from `getDummyMatrix` with ZERO network.
 */
import { useRbacStore } from '@/store/useRbacStore';
import { useDummyStore } from '@/dummy/store';
import { MENU_KEYS } from '@/dummy/rbac';

const originalFetch = globalThis.fetch;
let fetchMock: jest.Mock;

beforeEach(() => {
  fetchMock = jest.fn();
  (globalThis as unknown as { fetch: unknown }).fetch = fetchMock;
  useRbacStore.getState().reset();
  useDummyStore.getState().reset();
});

afterEach(() => {
  (globalThis as unknown as { fetch: unknown }).fetch = originalFetch;
  useRbacStore.getState().reset();
  useDummyStore.getState().reset();
});

describe('levelFor / canRead / canEdit', () => {
  it('resolves levels for the current role', () => {
    useRbacStore.getState().hydrateFromMe(
      { products: 'read', payments: 'edit', analytics: 'none' },
      'sales',
    );

    const store = useRbacStore.getState();
    expect(store.levelFor('products')).toBe('read');
    expect(store.canRead('products')).toBe(true);
    expect(store.canEdit('products')).toBe(false);

    expect(store.levelFor('payments')).toBe('edit');
    expect(store.canRead('payments')).toBe(true);
    expect(store.canEdit('payments')).toBe(true);

    expect(store.levelFor('analytics')).toBe('none');
    expect(store.canRead('analytics')).toBe(false);
    expect(store.canEdit('analytics')).toBe(false);
  });

  it('falls back to none for unknown keys and before hydration', () => {
    expect(useRbacStore.getState().levelFor('products')).toBe('none');
    useRbacStore.getState().hydrateFromMe({ products: 'read' }, 'sales');
    expect(useRbacStore.getState().levelFor('does_not_exist')).toBe('none');
  });
});

describe('dummy mode', () => {
  it('hydrates from getDummyMatrix without any fetch', () => {
    useDummyStore.getState().toggle();
    expect(useDummyStore.getState().isDummy).toBe(true);

    useRbacStore.getState().hydrateFromMe(null, 'outlet');

    expect(useRbacStore.getState().levelFor('products')).toBe('read');
    expect(useRbacStore.getState().levelFor('analytics')).toBe('none');
    expect(fetchMock).not.toHaveBeenCalled();
  });

  it('loads the full dummy matrix for all roles without fetch', async () => {
    useDummyStore.getState().toggle();

    await useRbacStore.getState().loadFromServer('t-token');

    const matrix = useRbacStore.getState().matrix;
    expect(matrix).not.toBeNull();
    for (const key of MENU_KEYS) {
      expect(matrix!.admin[key]).toBeDefined();
      expect(matrix!.finance[key]).toBeDefined();
    }
    expect(fetchMock).not.toHaveBeenCalled();
  });

  it('save applies cells locally without fetch', async () => {
    useDummyStore.getState().toggle();
    await useRbacStore.getState().loadFromServer('t-token');

    await useRbacStore.getState().save('t-token', [
      { role: 'sales', menu_key: 'products', level: 'edit' },
    ]);

    expect(fetchMock).not.toHaveBeenCalled();
    expect(useRbacStore.getState().matrix!.sales.products).toBe('edit');
  });
});

describe('network mode', () => {
  it('loadFromServer fills the matrix from GET /admin/rbac/matrix', async () => {
    fetchMock.mockResolvedValueOnce({
      ok: true,
      status: 200,
      json: async () => ({
        status: 'success',
        data: {
          admin: { products: 'edit', analytics: 'read' },
          finance: { products: 'read' },
        },
      }),
    } as Response);

    await useRbacStore.getState().loadFromServer('t-token');

    expect(fetchMock).toHaveBeenCalledTimes(1);
    const [url, init] = fetchMock.mock.calls[0];
    expect(String(url)).toContain('/admin/rbac/matrix');
    expect((init as RequestInit).method ?? 'GET').toBe('GET');
    expect(useRbacStore.getState().matrix!.admin.products).toBe('edit');
    expect(useRbacStore.getState().matrix!.finance.products).toBe('read');
  });

  it('save sends a PUT and refreshes the matrix from the response', async () => {
    fetchMock.mockResolvedValueOnce({
      ok: true,
      status: 200,
      json: async () => ({
        status: 'success',
        data: {
          sales: { products: 'edit' },
        },
      }),
    } as Response);

    await useRbacStore.getState().save('t-token', [
      { role: 'sales', menu_key: 'products', level: 'edit' },
    ]);

    expect(fetchMock).toHaveBeenCalledTimes(1);
    const [url, init] = fetchMock.mock.calls[0];
    expect(String(url)).toContain('/admin/rbac/matrix');
    expect((init as RequestInit).method).toBe('PUT');
    expect(JSON.parse(String((init as RequestInit).body))).toEqual({
      cells: [{ role: 'sales', menu_key: 'products', level: 'edit' }],
    });
    expect(useRbacStore.getState().matrix!.sales.products).toBe('edit');
  });

  it('save throws the server message on a non-ok response', async () => {
    fetchMock.mockResolvedValueOnce({
      ok: false,
      status: 422,
      json: async () => ({ status: 'error', message: 'You cannot remove your own access to rbac_matrix.' }),
    } as Response);

    await expect(
      useRbacStore.getState().save('t-token', [
        { role: 'admin', menu_key: 'rbac_matrix', level: 'none' },
      ]),
    ).rejects.toThrow('You cannot remove your own access to rbac_matrix.');
  });
});

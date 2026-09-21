/**
 * API surface for the `/admin/rbac` page.
 *
 * All matrix traffic is funnelled through `useRbacStore` (never a direct fetch
 * from the component): the store owns the token handling, dummy-mode branch,
 * error parsing and the post-save refresh. `fetchMe` hydrates the caller's
 * role + 19-key map so the page can gate on `canRead('rbac_matrix')`.
 */
import { apiUrl, authHeaders } from '@/lib/api';
import { useRbacStore, type RbacCell, type RbacMatrix } from '@/store/useRbacStore';
import { MENU_KEYS, ROLES, type RbacLevel } from '@/dummy/rbac';

// Canonical display order — re-exported so the page never imports the fixture
// module directly (the store remains the single RBAC seam).
export { MENU_KEYS, ROLES };

/** Human labels per menu key (mirrors MenuDefinition::CATALOG). */
export const MENU_LABELS: Record<string, string> = {
  dashboard: 'Dasbor',
  orders: 'Pesanan',
  products: 'Produk',
  outlets: 'Outlet',
  marketplace: 'Marketplace',
  payments: 'Pembayaran',
  delivery: 'Pengiriman',
  sales: 'Sales',
  invoices: 'Invoice',
  worktree: 'Worktree',
  analytics: 'Analitik',
  data_intelligence: 'Data Intelligence',
  operations: 'Operasi',
  admin_orders: 'Approval Pesanan',
  admin_products: 'Harga Produk',
  admin_users: 'Kelola Pengguna',
  admin_promotions: 'Kelola Promosi',
  admin_sales_performance: 'Performa Sales',
  rbac_matrix: 'Kelola Akses',
};

/** The three selectable levels, in ascending order. */
export const LEVELS = ['none', 'read', 'edit'] as const;

export interface Me {
  role: string | null;
  rbac: Record<string, RbacLevel> | null;
}

/** Load the caller's role + RBAC map from `GET /auth/me`. */
export async function fetchMe(token: string): Promise<Me> {
  const response = await fetch(apiUrl('/auth/me'), { headers: authHeaders(token) });
  if (!response.ok) return { role: null, rbac: null };
  const body = await response.json();
  return { role: body?.data?.role ?? null, rbac: body?.data?.rbac ?? null };
}

/** Load the full 7-role matrix into the store; returns it. */
export async function loadMatrix(token: string): Promise<RbacMatrix> {
  await useRbacStore.getState().loadFromServer(token);
  return useRbacStore.getState().matrix ?? {};
}

/** Persist cells via the store (PUT all-or-nothing); returns the refreshed matrix. */
export async function saveMatrix(token: string, cells: RbacCell[]): Promise<RbacMatrix> {
  await useRbacStore.getState().save(token, cells);
  return useRbacStore.getState().matrix ?? {};
}

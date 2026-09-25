/**
 * Dummy RBAC matrix fixture — mirrors the seeded default matrix with ZERO network.
 *
 * Source of truth: apps/api/database/seeders/RbacMatrixSeeder.php
 * ("Default Matrix"). Only non-`none` cells are listed; anything omitted is
 * `none`. Keep this in lockstep with the seeder — parity is asserted in
 * `rbac.test.ts`.
 *
 * Pure module: no `fetch`, no imports from `lib/api`, no side effects.
 */

export type RbacLevel = 'none' | 'read' | 'edit';

/** Canonical menu keys, in display order (matches MenuDefinition::CATALOG). */
export const MENU_KEYS = [
  'dashboard',
  'orders',
  'products',
  'outlets',
  'marketplace',
  'payments',
  'delivery',
  'sales',
  'invoices',
  'worktree',
  'analytics',
  'data_intelligence',
  'operations',
  'admin_orders',
  'admin_products',
  'admin_users',
  'admin_promotions',
  'admin_sales_performance',
  'rbac_matrix',
  'field_ops',
  'driver_roster',
  'ai_actions',
  'supply_chain',
] as const;

export type MenuKey = (typeof MENU_KEYS)[number];

/** Canonical role list (string-enum roles). */
export const ROLES = [
  'platform_owner',
  'admin',
  'outlet',
  'supplier',
  'sales',
  'driver',
  'finance',
] as const;

export type RbacRole = (typeof ROLES)[number];

/**
 * Raw default matrix keyed by menu → role → level. Only non-`none` cells are
 * present. Mirrors `RbacMatrixSeeder::MATRIX` verbatim.
 */
export const DUMMY_RBAC_MATRIX: Record<string, Partial<Record<RbacRole, RbacLevel>>> = {
  dashboard: {
    platform_owner: 'edit', admin: 'edit', outlet: 'edit',
    supplier: 'edit', sales: 'edit', driver: 'edit', finance: 'read',
  },
  orders: {
    platform_owner: 'edit', admin: 'edit', outlet: 'edit',
    supplier: 'read', sales: 'edit', driver: 'read', finance: 'read',
  },
  products: {
    platform_owner: 'edit', admin: 'edit', outlet: 'read',
    supplier: 'read', sales: 'read', driver: 'read', finance: 'read',
  },
  outlets: {
    platform_owner: 'edit', admin: 'edit', outlet: 'read',
    sales: 'read', finance: 'read',
  },
  marketplace: {
    platform_owner: 'edit', admin: 'edit', outlet: 'edit',
    supplier: 'edit', sales: 'edit', finance: 'read',
  },
  payments: {
    platform_owner: 'edit', admin: 'edit', outlet: 'edit',
    sales: 'read', finance: 'edit',
  },
  delivery: {
    platform_owner: 'edit', admin: 'edit', outlet: 'read',
    sales: 'read', driver: 'edit', finance: 'read',
  },
  sales: {
    platform_owner: 'edit', admin: 'edit', outlet: 'read',
    sales: 'edit', finance: 'read',
  },
  invoices: {
    platform_owner: 'edit', admin: 'edit', outlet: 'read',
    finance: 'edit',
  },
  worktree: {
    platform_owner: 'read', admin: 'read', outlet: 'read',
    supplier: 'read', sales: 'read', driver: 'read', finance: 'read',
  },
  analytics: {
    platform_owner: 'edit', admin: 'read',
  },
  data_intelligence: {
    platform_owner: 'edit', admin: 'read',
  },
  operations: {
    platform_owner: 'edit', admin: 'read',
  },
  admin_orders: {
    platform_owner: 'edit', admin: 'edit',
  },
  admin_products: {
    platform_owner: 'edit', admin: 'edit',
  },
  admin_users: {
    platform_owner: 'edit', admin: 'read',
  },
  admin_promotions: {
    platform_owner: 'edit', admin: 'edit',
  },
  admin_sales_performance: {
    platform_owner: 'edit', admin: 'edit',
  },
  rbac_matrix: {
    platform_owner: 'edit', admin: 'read',
  },
  field_ops: {
    platform_owner: 'edit', admin: 'edit',
    sales: 'read', driver: 'read',
  },
  driver_roster: {
    platform_owner: 'edit', admin: 'edit',
  },
  ai_actions: {
    platform_owner: 'edit', admin: 'edit',
  },
  supply_chain: {
    platform_owner: 'edit', admin: 'edit',
  },
};

const NONE_MAP: Record<MenuKey, RbacLevel> = MENU_KEYS.reduce(
  (acc, key) => {
    acc[key] = 'none';
    return acc;
  },
  {} as Record<MenuKey, RbacLevel>,
);

/**
 * Resolve the 23-key level map for a role. Unknown / null roles fall back to
 * an all-`none` map (matching the middleware's "missing row → none" rule).
 */
export function getDummyMatrix(role: string | null): Record<MenuKey, RbacLevel> {
  const map: Record<MenuKey, RbacLevel> = { ...NONE_MAP };
  if (!role) return map;

  for (const key of MENU_KEYS) {
    const level = DUMMY_RBAC_MATRIX[key]?.[role as RbacRole];
    if (level) map[key] = level;
  }

  return map;
}

/**
 * rbac.test.ts — dummy RBAC matrix fixture.
 *
 * Story 3 (dummy parity): dummy mode must mirror the seeded default matrix
 * with zero network. Guards the shape (23 explicit keys), the fallback for
 * unknown roles (all `none`), and spot-check parity with the API seeder
 * (apps/api/database/seeders/RbacMatrixSeeder.php).
 */
import { DUMMY_RBAC_MATRIX, MENU_KEYS, ROLES, getDummyMatrix } from '@/dummy/rbac';

/**
 * Explicit expected key list — the 21 legacy keys in their original order,
 * followed by the Phase 9 additions. Kept literal (rather than imported from
 * the API) so a reordering of `MENU_KEYS` fails this test instead of silently
 * reordering the sidebar and the RBAC matrix.
 */
const EXPECTED_MENU_KEYS = [
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

describe('DUMMY_RBAC_MATRIX shape', () => {
  it('exposes exactly 23 menu keys in the canonical order', () => {
    expect(MENU_KEYS).toHaveLength(23);
    expect(new Set(MENU_KEYS).size).toBe(23);
    expect([...MENU_KEYS]).toEqual([...EXPECTED_MENU_KEYS]);
  });

  it('appends ai_actions and supply_chain after the 21 legacy keys', () => {
    expect(MENU_KEYS.slice(0, 21)).toEqual([...EXPECTED_MENU_KEYS.slice(0, 21)]);
    expect(MENU_KEYS.slice(21)).toEqual(['ai_actions', 'supply_chain']);
  });

  it('exposes exactly 7 roles', () => {
    expect(ROLES).toHaveLength(7);
  });

  it('resolves a 23-key map for every known role', () => {
    for (const role of ROLES) {
      const map = getDummyMatrix(role);
      expect(Object.keys(map)).toHaveLength(23);
      for (const key of MENU_KEYS) {
        expect(['none', 'read', 'edit']).toContain(map[key]);
      }
    }
  });
});

describe('getDummyMatrix fallback', () => {
  it('returns all-none for an unknown role', () => {
    const map = getDummyMatrix('unknown');
    expect(Object.keys(map)).toHaveLength(23);
    for (const key of MENU_KEYS) {
      expect(map[key]).toBe('none');
    }
  });

  it('returns all-none for a null role', () => {
    const map = getDummyMatrix(null);
    for (const key of MENU_KEYS) {
      expect(map[key]).toBe('none');
    }
  });
});

describe('getDummyMatrix finance', () => {
  it('mirrors the seeded finance row', () => {
    const map = getDummyMatrix('finance');
    expect(map.products).toBe('read');
    expect(map.orders).toBe('read');
    expect(map.delivery).toBe('read');
    expect(map.sales).toBe('read');
    expect(map.outlets).toBe('read');
    expect(map.marketplace).toBe('read');
    expect(map.payments).toBe('edit');
    expect(map.invoices).toBe('edit');
    expect(map.worktree).toBe('read');
    expect(map.analytics).toBe('none');
    expect(map.data_intelligence).toBe('none');
    expect(map.operations).toBe('none');
    expect(map.rbac_matrix).toBe('none');
  });
});

describe('getDummyMatrix parity spot-checks with the API seeder', () => {
  it('matches key admin/owner cells', () => {
    const admin = getDummyMatrix('admin');
    expect(admin.analytics).toBe('read');
    expect(admin.data_intelligence).toBe('read');
    expect(admin.operations).toBe('read');
    expect(admin.admin_users).toBe('read');
    expect(admin.rbac_matrix).toBe('read');
    expect(admin.admin_orders).toBe('edit');
    expect(admin.admin_products).toBe('edit');

    const owner = getDummyMatrix('platform_owner');
    expect(owner.rbac_matrix).toBe('edit');
    expect(owner.analytics).toBe('edit');
    expect(owner.admin_users).toBe('edit');
  });

  it('grants worktree read to all 7 roles', () => {
    for (const role of ROLES) {
      expect(getDummyMatrix(role).worktree).toBe('read');
    }
  });

  it('matches outlet and driver rows', () => {
    const outlet = getDummyMatrix('outlet');
    expect(outlet.dashboard).toBe('edit');
    expect(outlet.orders).toBe('edit');
    expect(outlet.marketplace).toBe('edit');
    expect(outlet.payments).toBe('edit');
    expect(outlet.products).toBe('read');
    expect(outlet.admin_users).toBe('none');

    const driver = getDummyMatrix('driver');
    expect(driver.dashboard).toBe('edit');
    expect(driver.delivery).toBe('edit');
    expect(driver.orders).toBe('read');
    expect(driver.products).toBe('read');
    expect(driver.analytics).toBe('none');
  });

  it('matches supplier and sales rows', () => {
    const supplier = getDummyMatrix('supplier');
    expect(supplier.dashboard).toBe('edit');
    expect(supplier.marketplace).toBe('edit');
    expect(supplier.orders).toBe('read');
    expect(supplier.products).toBe('read');

    const sales = getDummyMatrix('sales');
    expect(sales.dashboard).toBe('edit');
    expect(sales.orders).toBe('edit');
    expect(sales.sales).toBe('edit');
    expect(sales.products).toBe('read');
    expect(sales.delivery).toBe('read');
  });

  it('stores only non-none cells in the raw matrix', () => {
    // Spot-check: finance must not carry a data_intelligence row.
    expect(DUMMY_RBAC_MATRIX.data_intelligence?.finance).toBeUndefined();
    // ...but admin must.
    expect(DUMMY_RBAC_MATRIX.data_intelligence?.admin).toBe('read');
  });

  it('matches field_ops + driver_roster rows from seeder', () => {
    // field_ops: platform_owner=edit, admin=edit, sales=read, driver=read
    const fieldOps = DUMMY_RBAC_MATRIX.field_ops;
    expect(fieldOps?.platform_owner).toBe('edit');
    expect(fieldOps?.admin).toBe('edit');
    expect(fieldOps?.sales).toBe('read');
    expect(fieldOps?.driver).toBe('read');
    expect(fieldOps?.outlet).toBeUndefined();
    expect(fieldOps?.supplier).toBeUndefined();
    expect(fieldOps?.finance).toBeUndefined();

    // driver_roster: platform_owner=edit, admin=edit
    const driverRoster = DUMMY_RBAC_MATRIX.driver_roster;
    expect(driverRoster?.platform_owner).toBe('edit');
    expect(driverRoster?.admin).toBe('edit');
    expect(driverRoster?.sales).toBeUndefined();
    expect(driverRoster?.driver).toBeUndefined();
    expect(driverRoster?.outlet).toBeUndefined();
    expect(driverRoster?.supplier).toBeUndefined();
    expect(driverRoster?.finance).toBeUndefined();
  });

  it('matches the Phase 9 ai_actions + supply_chain rows from seeder', () => {
    // Both new menus: platform_owner=edit, admin=edit; every other role absent.
    for (const menu of ['ai_actions', 'supply_chain'] as const) {
      expect(DUMMY_RBAC_MATRIX[menu]?.platform_owner).toBe('edit');
      expect(DUMMY_RBAC_MATRIX[menu]?.admin).toBe('edit');
      expect(DUMMY_RBAC_MATRIX[menu]?.outlet).toBeUndefined();
      expect(DUMMY_RBAC_MATRIX[menu]?.supplier).toBeUndefined();
      expect(DUMMY_RBAC_MATRIX[menu]?.sales).toBeUndefined();
      expect(DUMMY_RBAC_MATRIX[menu]?.driver).toBeUndefined();
      expect(DUMMY_RBAC_MATRIX[menu]?.finance).toBeUndefined();

      expect(getDummyMatrix('platform_owner')[menu]).toBe('edit');
      expect(getDummyMatrix('admin')[menu]).toBe('edit');
      expect(getDummyMatrix('outlet')[menu]).toBe('none');
      expect(getDummyMatrix('supplier')[menu]).toBe('none');
      expect(getDummyMatrix('sales')[menu]).toBe('none');
      expect(getDummyMatrix('driver')[menu]).toBe('none');
      expect(getDummyMatrix('finance')[menu]).toBe('none');
    }
  });
});

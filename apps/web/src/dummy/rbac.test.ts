/**
 * rbac.test.ts — dummy RBAC matrix fixture.
 *
 * Story 3 (dummy parity): dummy mode must mirror the seeded default matrix
 * with zero network. Guards the shape (19 explicit keys), the fallback for
 * unknown roles (all `none`), and spot-check parity with the API seeder
 * (apps/api/database/seeders/RbacMatrixSeeder.php).
 */
import { DUMMY_RBAC_MATRIX, MENU_KEYS, ROLES, getDummyMatrix } from '@/dummy/rbac';

describe('DUMMY_RBAC_MATRIX shape', () => {
  it('exposes exactly 19 menu keys', () => {
    expect(MENU_KEYS).toHaveLength(19);
    expect(new Set(MENU_KEYS).size).toBe(19);
  });

  it('exposes exactly 7 roles', () => {
    expect(ROLES).toHaveLength(7);
  });

  it('resolves a 19-key map for every known role', () => {
    for (const role of ROLES) {
      const map = getDummyMatrix(role);
      expect(Object.keys(map)).toHaveLength(19);
      for (const key of MENU_KEYS) {
        expect(['none', 'read', 'edit']).toContain(map[key]);
      }
    }
  });
});

describe('getDummyMatrix fallback', () => {
  it('returns all-none for an unknown role', () => {
    const map = getDummyMatrix('unknown');
    expect(Object.keys(map)).toHaveLength(19);
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
});

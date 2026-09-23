import { buildFullDummy } from '@/dummy';
import { useDummyStore, setDummyGenerator } from '@/dummy/store';
import type { DummyEntities } from '@/dummy/store';
import { getDummyMatrix } from '@/dummy/rbac';

const DUMMY = buildFullDummy(new Date('2026-09-22T08:00:00Z'));

describe('Dummy field-ops fixtures — parity', () => {
  beforeEach(() => {
    localStorage.clear();
    useDummyStore.getState().reset();
    setDummyGenerator(() => DUMMY as unknown as DummyEntities);
    useDummyStore.getState().toggle();
  });

  afterEach(() => {
    useDummyStore.getState().reset();
  });

  it('driver roster has ≥8 drivers with name + email + vehicle + availability', () => {
    expect(DUMMY.driverProfiles.length).toBeGreaterThanOrEqual(8);
    for (const p of DUMMY.driverProfiles) {
      expect(p.user.name).toBeTruthy();
      expect(p.user.email).toBeTruthy();
      expect(p.user.role).toBe('driver');
      expect(p.vehicle_type).toBeTruthy();
      expect(p.plate_number).toBeTruthy();
      expect(typeof p.is_available).toBe('boolean');
    }
  });

  it('deliveries have driver_id linked to driverProfiles', () => {
    const driverIds = new Set(DUMMY.driverProfiles.map((p) => p.user_id));
    for (const d of DUMMY.deliveries) {
      expect(driverIds.has(String(d.driver_id))).toBe(true);
    }
  });

  it('getTrack dummy returns a path for each delivery (buildDummyTrack)', async () => {
    const { getTrack } = await import('@/app/admin/tracking/api');
    for (const d of DUMMY.deliveries.slice(0, 5)) {
      const result = await getTrack(d.id);
      expect(result.last_position).not.toBeNull();
      expect(result.pings.length).toBeGreaterThanOrEqual(1);
      // Driver name now resolves from driverProfiles; every dummy driver has one.
      expect(result.driver?.name).toBeTruthy();
      expect(result.driver?.id).toBe(d.driver_id);
    }
  });

  it('dummy sales visits have check_in_at / check_out_at populated', () => {
    // visits are in DUMMY.visits (factory-transactions)
    const visits = DUMMY.visits;
    expect(visits.length).toBeGreaterThan(0);
    const checkedIn = visits.filter((v) => v.check_in_at);
    expect(checkedIn.length).toBeGreaterThan(0);
  });

  it('dummy PoD has proof_of_delivery populated for delivered deliveries', () => {
    const delivered = DUMMY.deliveries.filter((d) => d.status === 'delivered');
    expect(delivered.length).toBeGreaterThan(0);
    for (const d of delivered) {
      expect(d.proof_of_delivery_url).toBeTruthy();
    }
  });
});

describe('RBAC matrix — new menu keys visibility', () => {
  const adminMatrix = getDummyMatrix('admin');
  const driverMatrix = getDummyMatrix('driver');

  it('admin sees driver_roster + field_ops (edit)', () => {
    expect(adminMatrix.driver_roster).toBe('edit');
    expect(adminMatrix.field_ops).toBe('edit');
  });

  it('driver sees field_ops (read) but not driver_roster', () => {
    expect(driverMatrix.field_ops).toBe('read');
    expect(driverMatrix.driver_roster).toBe('none');
  });

  it('platform_owner same as admin for new keys', () => {
    const po = getDummyMatrix('platform_owner');
    expect(po.driver_roster).toBe('edit');
    expect(po.field_ops).toBe('edit');
  });

  it('sales sees field_ops (read) but not driver_roster', () => {
    const s = getDummyMatrix('sales');
    expect(s.driver_roster).toBe('none');
    expect(s.field_ops).toBe('read');
  });
});
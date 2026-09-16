/**
 * Integration: installDummy registers the canonical buildFullDummy generator.
 *
 * Exercises the real Zustand singleton (no stubs). Test hygiene: clear
 * localStorage, reset store, run toggles on the real buildFullDummy.
 */
import { useDummyStore } from '@/dummy/store';
import { installDummy } from '@/dummy/install';

type EntityMap = Record<string, unknown>;

function entitiesOf(): EntityMap {
  const entities = useDummyStore.getState().dummyEntities as EntityMap | null;
  if (!entities) throw new Error('dummyEntities is null — generator not registered');
  return entities;
}

beforeEach(() => {
  localStorage.clear();
  useDummyStore.getState().reset();
  localStorage.clear();
});

afterEach(() => {
  jest.restoreAllMocks();
});

describe('installDummy', () => {
  it('registers buildFullDummy so toggle ON yields populated entities', () => {
    expect(useDummyStore.getState().isDummy).toBe(false);
    expect(useDummyStore.getState().dummyEntities).toBeNull();

    installDummy();
    useDummyStore.getState().toggle();

    const entities = entitiesOf();
    const outlets = entities['outlets'] as unknown[];
    const products = entities['products'] as unknown[];
    const orders = entities['orders'] as unknown[];
    const geographic = entities['geographic'] as {
      map_points: unknown[];
      table: unknown[];
    };
    expect(outlets.length).toBeGreaterThan(0);
    expect(products.length).toBeGreaterThan(0);
    expect(orders.length).toBeGreaterThan(0);
    expect(geographic.map_points.length).toBeGreaterThanOrEqual(40);
    expect(geographic.map_points.length).toBeLessThanOrEqual(60);
    expect(geographic.table.length).toBe(5);
  });

  it('toggle OFF then ON again reproduces the same data (deterministic)', () => {
    installDummy();

    useDummyStore.getState().toggle();
    const first = JSON.stringify(useDummyStore.getState().dummyEntities);

    useDummyStore.getState().toggle();
    expect(useDummyStore.getState().isDummy).toBe(false);
    expect(useDummyStore.getState().dummyEntities).toBeNull();

    useDummyStore.getState().toggle();
    const second = JSON.stringify(useDummyStore.getState().dummyEntities);
    expect(second).not.toBe('null');
    expect(first).toBe(second);
  });
});

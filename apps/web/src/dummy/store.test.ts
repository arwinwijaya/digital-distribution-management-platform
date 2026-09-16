/**
 * Dummy-mode Zustand store tests.
 *
 * RED cycle 1 — toggle ON persists flag + generates once; OFF clears entities.
 * RED cycle 2 — refresh with persisted flag repopulates entities.
 * RED cycle 3 — toggle ON again regenerates a fresh rolling window.
 *
 * Exercises useDummyStore + getState() + setDummyGenerator + jsdom localStorage.
 */
import {
  useDummyStore,
  setDummyGenerator,
  selectIsDummy,
  DUMMY_FLAG_KEY,
} from '@/dummy/store';

const stubEntities: Record<string, unknown> = {
  outlets: [{ id: 'dummy-001', name: 'Toko Bogor Indah' }],
  products: [{ sku: 'SKU-1001' }],
};

let generateMock: jest.Mock<Record<string, unknown>, []>;

beforeEach(() => {
  localStorage.clear();
  useDummyStore.getState().reset();
  localStorage.clear();
  generateMock = jest.fn<Record<string, unknown>, []>(() => stubEntities);
  setDummyGenerator(generateMock);
  // generateMock must NOT have run yet: isDummy is false, no persisted flag.
});

afterEach(() => {
  jest.restoreAllMocks();
});

describe('dummy store - RED cycle 1: toggle ON/OFF', () => {
  it('toggle ON persists flag + generates once; OFF clears entities', () => {
    expect(useDummyStore.getState().isDummy).toBe(false);
    expect(useDummyStore.getState().dummyEntities).toBeNull();
    expect(localStorage.getItem(DUMMY_FLAG_KEY)).toBeNull();

    useDummyStore.getState().toggle();
    expect(useDummyStore.getState().isDummy).toBe(true);
    expect(selectIsDummy(useDummyStore.getState())).toBe(true);
    expect(localStorage.getItem(DUMMY_FLAG_KEY)).toBe('1');
    expect(generateMock).toHaveBeenCalledTimes(1);
    // determinism seam: generate called with NO args
    expect(generateMock.mock.calls[0]).toHaveLength(0);
    expect(useDummyStore.getState().dummyEntities).toEqual(stubEntities);

    // flag-only persist: no entities key anywhere
    expect(localStorage.getItem('dummy:entities')).toBeNull();
    expect(localStorage.getItem('dummy:dummyEntities')).toBeNull();
    expect(localStorage.getItem('dummy_entities')).toBeNull();

    useDummyStore.getState().toggle();
    expect(useDummyStore.getState().isDummy).toBe(false);
    expect(selectIsDummy(useDummyStore.getState())).toBe(false);
    expect(localStorage.getItem(DUMMY_FLAG_KEY)).toBeNull();
    expect(useDummyStore.getState().dummyEntities).toBeNull();
    expect(localStorage.getItem('dummy:entities')).toBeNull();
    expect(localStorage.getItem('dummy:dummyEntities')).toBeNull();
    // no additional generation on OFF
    expect(generateMock).toHaveBeenCalledTimes(1);
  });

  it('toggle takes no args: a passed date is never forwarded to the generator', () => {
    // @ts-expect-error - toggle is arity-0; a date must not leak through
    useDummyStore.getState().toggle(new Date('2026-01-01'));
    expect(generateMock).toHaveBeenCalledTimes(1);
    expect(generateMock.mock.calls[0]).toHaveLength(0);
    useDummyStore.getState().toggle(); // back OFF for isolation
  });
});

describe('dummy store - RED cycle 2: refresh with persisted flag', () => {
  const persistedStub: Record<string, unknown> = {
    outlets: [{ id: 'dummy-001', name: 'Toko Bogor Indah' }],
    orders: [{ id: 'dummy-order-1' }],
  };

  it('repopulates entities at init from the persisted flag and stays deterministic', () => {
    // Simulate a previous session that left dummy mode ON.
    localStorage.setItem(DUMMY_FLAG_KEY, '1');

    let isDummy = false;
    let entities: unknown = undefined;
    let reRegistered: unknown = undefined;

    jest.isolateModules(() => {
      const store: typeof import('@/dummy/store') = require('@/dummy/store');
      // Register the generator for this fresh instance.
      store.setDummyGenerator(() => persistedStub);
      isDummy = store.useDummyStore.getState().isDummy;
      entities = store.useDummyStore.getState().dummyEntities;
      // Re-registering the same deterministic stub yields a deep-equal graph.
      store.setDummyGenerator(() => persistedStub);
      reRegistered = store.useDummyStore.getState().dummyEntities;
    });

    expect(isDummy).toBe(true);
    expect(entities).not.toBeNull();
    expect(entities).toEqual(persistedStub);
    expect(reRegistered).toEqual(entities);
  });
});

describe('dummy store - RED cycle 3: toggle ON again regenerates fresh window', () => {
  it('produces a fresh window on re-toggle ON after re-registering generator', () => {
    let callCount = 0;

    const genT1 = (): Record<string, unknown> => {
      callCount += 1;
      return { tag: 'T1', orders: [{ id: 'o1' }] };
    };
    const genT2 = (): Record<string, unknown> => {
      callCount += 1;
      return { tag: 'T2', orders: [{ id: 'o2' }] };
    };

    // Pin T1, go ON (1st generation).
    setDummyGenerator(genT1);
    expect(useDummyStore.getState().isDummy).toBe(false);
    useDummyStore.getState().toggle();
    expect(useDummyStore.getState().isDummy).toBe(true);
    expect(useDummyStore.getState().dummyEntities).toEqual(expect.objectContaining({ tag: 'T1' }));

    // OFF clears entities.
    useDummyStore.getState().toggle();
    expect(useDummyStore.getState().isDummy).toBe(false);
    expect(useDummyStore.getState().dummyEntities).toBeNull();

    // Re-register with a T2-pinned generator. isDummy is false, so the
    // cycle-2 repopulate guard must NOT generate here.
    setDummyGenerator(genT2);
    expect(useDummyStore.getState().dummyEntities).toBeNull();

    // ON again must regenerate freshly from T2 (2nd generation).
    useDummyStore.getState().toggle();
    expect(useDummyStore.getState().isDummy).toBe(true);
    expect(callCount).toBe(2);
    expect(useDummyStore.getState().dummyEntities).toEqual(expect.objectContaining({ tag: 'T2' }));
  });
});

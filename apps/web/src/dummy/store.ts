/**
 * Dummy-mode Zustand singleton.
 *
 * Persists ONLY the `isDummy` flag to localStorage — dummy entities are
 * in-memory and are never serialized. Toggle ON generates a fresh dataset
 * exactly once (rolling window); toggle OFF drops the entities.
 *
 * The generator itself is injected through `setDummyGenerator` so this store
 * never imports the real factory (no backend calls, no eager generation at
 * import time).
 */
import { create } from 'zustand';

/** Shape of the generated relational dummy graph. */
export type DummyEntities = Record<string, unknown>;

/**
 * Generator contract. `today` is optional — production code pins it via the
 * `setDummyGenerator` closure, the store always calls it with NO arguments.
 */
export type DummyGenerator = (today?: Date) => DummyEntities;

/** localStorage key holding the flag. NEVER carries entities. */
export const DUMMY_FLAG_KEY = 'dummy:isDummy';

export interface DummyState {
  isDummy: boolean;
  dummyEntities: DummyEntities | null;
  role: string | null;
  /** Flip dummy mode. No arguments — the date seam lives in the generator. */
  toggle: () => void;
  setRole: (role: string | null) => void;
  resetEntities: () => void;
  /** Test helper: back to a pristine, flag-cleared state. */
  reset: () => void;
}

/** Module-level generator slot (not part of the persisted/serialized state). */
let dummyGenerator: DummyGenerator | null = null;

function readPersistedFlag(): boolean {
  if (typeof localStorage === 'undefined') return false;
  return localStorage.getItem(DUMMY_FLAG_KEY) === '1';
}

function persistFlag(on: boolean): void {
  if (typeof localStorage === 'undefined') return;
  if (on) localStorage.setItem(DUMMY_FLAG_KEY, '1');
  else localStorage.removeItem(DUMMY_FLAG_KEY);
}

/**
 * Register the generator. Called once at app bootstrap (module seam).
 *
 * If the store is already in dummy mode (persisted flag) but entities have
 * not been populated yet, the generator runs immediately so that refresh-
 * with-persisted-flag gives a non-null dummyEntities on first read.
 */
export function setDummyGenerator(generator: DummyGenerator): void {
  dummyGenerator = generator;
  const state = useDummyStore.getState();
  if (state.isDummy && state.dummyEntities === null) {
    useDummyStore.setState({ dummyEntities: generator() });
  }
}

export const selectIsDummy = (state: DummyState): boolean => state.isDummy;

export const useDummyStore = create<DummyState>()((set, get) => ({
  isDummy: readPersistedFlag(),
  dummyEntities: null,
  role: null,

  toggle: () => {
    if (get().isDummy) {
      persistFlag(false);
      set({ isDummy: false, dummyEntities: null });
      return;
    }
    // false -> true: regenerate once, always with NO arguments.
    const entities = dummyGenerator ? dummyGenerator() : null;
    persistFlag(true);
    set({ isDummy: true, dummyEntities: entities });
  },

  setRole: (role) => set({ role }),

  resetEntities: () => set({ dummyEntities: null }),

  reset: () => {
    persistFlag(false);
    set({ isDummy: false, dummyEntities: null, role: null });
  },
}));

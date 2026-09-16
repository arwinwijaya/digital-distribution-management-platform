/**
 * Deterministic seeded RNG for dummy-mode data generation.
 *
 * Pure functions only — no store, no Date.now(), no global state.
 * The same seed string always produces the same sequence.
 */

export interface SeededRng {
  /** Next float in [0, 1). */
  next(): number;
  /** Random integer in the inclusive range [min, max]. */
  int(min: number, max: number): number;
  /** Random element from a non-empty array. */
  pick<T>(arr: readonly T[]): T;
  /** New array with the elements of `arr` in a deterministic shuffled order. */
  shuffle<T>(arr: readonly T[]): T[];
}

/** xmur3-style string hash → 32-bit unsigned integer. */
function hashSeed(seed: string): number {
  let h = 1779033703 ^ seed.length;
  for (let i = 0; i < seed.length; i++) {
    h = Math.imul(h ^ seed.charCodeAt(i), 3432918353);
    h = (h << 13) | (h >>> 19);
  }
  return h >>> 0;
}

/**
 * Create a mulberry32-style seeded RNG. Deterministic for a fixed seed.
 */
export function createSeededRng(seed: string): SeededRng {
  let state = hashSeed(seed) >>> 0;

  function next(): number {
    state = (state + 0x6d2b79f5) >>> 0;
    let t = state;
    t = Math.imul(t ^ (t >>> 15), t | 1);
    t ^= t + Math.imul(t ^ (t >>> 7), t | 61);
    return ((t ^ (t >>> 14)) >>> 0) / 4294967296;
  }

  function int(min: number, max: number): number {
    if (max < min) {
      throw new RangeError(`int: max (${max}) must be >= min (${min})`);
    }
    return min + Math.floor(next() * (max - min + 1));
  }

  function pick<T>(arr: readonly T[]): T {
    if (arr.length === 0) {
      throw new RangeError('pick: cannot pick from an empty array');
    }
    return arr[int(0, arr.length - 1)];
  }

  function shuffle<T>(arr: readonly T[]): T[] {
    const out = arr.slice();
    for (let i = out.length - 1; i > 0; i--) {
      const j = Math.floor(next() * (i + 1));
      const tmp = out[i];
      out[i] = out[j];
      out[j] = tmp;
    }
    return out;
  }

  return { next, int, pick, shuffle };
}

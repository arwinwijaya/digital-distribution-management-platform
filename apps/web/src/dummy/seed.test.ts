/**
 * RED cycle 1: deterministic seeded RNG + rolling 60-day window + JABODETABEK seed constants.
 *
 * Exercises:
 * - createSeededRng(seed) from '@/dummy/rng'
 * - dummyWindow(today?) + daysBetween(start, end) from '@/dummy/dates'
 * - DUMMY_SEED + JABODETABEK_TERRITORIES from '@/dummy/seed'
 *
 * Pure functions only — no store. Explicit `today` is injected where needed.
 */
import { createSeededRng } from '@/dummy/rng';
import { dummyWindow, daysBetween } from '@/dummy/dates';
import { DUMMY_SEED, JABODETABEK_TERRITORIES } from '@/dummy/seed';

describe('dummy seed foundation (RED cycle 1)', () => {
  describe('DUMMY_SEED', () => {
    it('is a fixed deterministic constant (never derived from Date.now())', () => {
      expect(typeof DUMMY_SEED).toBe('string');
      expect(DUMMY_SEED).toBe('ddp-jabodetabek-v1');
      expect(DUMMY_SEED.length).toBeGreaterThan(0);
    });
  });

  describe('createSeededRng', () => {
    it('generates byte-identical sequences for the same seed', () => {
      const first = createSeededRng(DUMMY_SEED);
      const second = createSeededRng(DUMMY_SEED);
      const seqA = Array.from({ length: 100 }, () => first.next());
      const seqB = Array.from({ length: 100 }, () => second.next());
      expect(seqA).toEqual(seqB);
    });

    it('keeps int/pick/shuffle deterministic for the same seed', () => {
      const run = () => {
        const rng = createSeededRng(DUMMY_SEED);
        return {
          ints: Array.from({ length: 20 }, () => rng.int(1, 100)),
          picks: Array.from({ length: 10 }, () => rng.pick(['a', 'b', 'c'])),
          shuffled: rng.shuffle([1, 2, 3, 4, 5]),
        };
      };
      expect(run()).toEqual(run());
    });

    it('produces different sequences for different seeds', () => {
      const a = createSeededRng(DUMMY_SEED);
      const b = createSeededRng('some-other-seed');
      const seqA = Array.from({ length: 20 }, () => a.next());
      const seqB = Array.from({ length: 20 }, () => b.next());
      expect(seqA).not.toEqual(seqB);
    });
  });

  describe('JABODETABEK_TERRITORIES', () => {
    it('yields exactly the 5 JABODETABEK territories', () => {
      expect(JABODETABEK_TERRITORIES).toHaveLength(5);
      const names = JABODETABEK_TERRITORIES.map((t) => t.name);
      expect(names).toEqual(
        expect.arrayContaining(['Jakarta', 'Bogor', 'Depok', 'Tangerang', 'Bekasi']),
      );
    });

    it('keeps every anchor inside the JABODETABEK bbox', () => {
      for (const territory of JABODETABEK_TERRITORIES) {
        expect(territory.anchor.lat).toBeGreaterThanOrEqual(-6.9);
        expect(territory.anchor.lat).toBeLessThanOrEqual(-5.9);
        expect(territory.anchor.lon).toBeGreaterThanOrEqual(105.9);
        expect(territory.anchor.lon).toBeLessThanOrEqual(107.3);
      }
    });
  });

  describe('dummyWindow', () => {
    it('returns end = today (Asia/Jakarta) and start = end − 60 days', () => {
      // 2026-02-14 10:00 WIB == 2026-02-14 on the Asia/Jakarta calendar.
      const today = new Date('2026-02-14T10:00:00+07:00');
      const { start, end } = dummyWindow(today);
      expect(end).toBe('2026-02-14');
      expect(start).toBe('2025-12-16');
    });

    it('defaults to the current day without an explicit today', () => {
      const { start, end } = dummyWindow();
      expect(end).toMatch(/^\d{4}-\d{2}-\d{2}$/);
      expect(start).toMatch(/^\d{4}-\d{2}-\d{2}$/);
      const diffDays =
        (Date.parse(`${end}T00:00:00Z`) - Date.parse(`${start}T00:00:00Z`)) / 86_400_000;
      expect(diffDays).toBe(60);
      expect(daysBetween(start, end)).toHaveLength(61);
    });
  });
});

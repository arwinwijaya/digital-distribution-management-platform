/**
 * RED cycle 2: deterministic master-data factory (outlets, products, suppliers).
 *
 * Exercises:
 * - buildMasterData(rng, window) from '@/dummy/factory'
 * - createSeededRng(seed) from '@/dummy/rng'
 * - dummyWindow(today) from '@/dummy/dates'
 * - DUMMY_SEED + JABODETABEK_TERRITORIES from '@/dummy/seed'
 *
 * Pure functions only — no store. Fixed `today` + seeded RNG are injected.
 */
import { createSeededRng } from '@/dummy/rng';
import { dummyWindow } from '@/dummy/dates';
import { DUMMY_SEED, JABODETABEK_TERRITORIES } from '@/dummy/seed';
import { buildMasterData } from '@/dummy/factory';

const today = new Date('2026-02-14T10:00:00+07:00');

describe('buildMasterData (RED cycle 2)', () => {
  const window = dummyWindow(today);
  const data = buildMasterData(createSeededRng(DUMMY_SEED), window);

  it('yields 44..52 outlets (≈9–10 per territory × 5)', () => {
    expect(data.outlets.length).toBeGreaterThanOrEqual(44);
    expect(data.outlets.length).toBeLessThanOrEqual(52);
  });

  it('assigns every outlet to a JABODETABEK territory, inside bbox, valid city', () => {
    const territoryIds = JABODETABEK_TERRITORIES.map((t) => t.id);
    const cityNames = JABODETABEK_TERRITORIES.map((t) => t.name);

    for (const outlet of data.outlets) {
      expect(territoryIds).toContain(outlet.territoryId);
      expect(cityNames).toContain(outlet.city);
      expect(outlet.lat).toBeGreaterThanOrEqual(-6.9);
      expect(outlet.lat).toBeLessThanOrEqual(-5.9);
      expect(outlet.lon).toBeGreaterThanOrEqual(105.9);
      expect(outlet.lon).toBeLessThanOrEqual(107.3);
    }
  });

  it('yields 28..32 products, each with a unique SKU', () => {
    expect(data.products.length).toBeGreaterThanOrEqual(28);
    expect(data.products.length).toBeLessThanOrEqual(32);
    const skus = data.products.map((p) => p.sku);
    expect(new Set(skus).size).toBe(skus.length);
  });

  it('yields 7..9 suppliers', () => {
    expect(data.suppliers.length).toBeGreaterThanOrEqual(7);
    expect(data.suppliers.length).toBeLessThanOrEqual(9);
  });

  it('is deterministic across runs for the same seed + window', () => {
    const first = buildMasterData(createSeededRng(DUMMY_SEED), dummyWindow(today));
    const second = buildMasterData(createSeededRng(DUMMY_SEED), dummyWindow(today));
    expect(first).toEqual(second);
  });

  it('names outlet dummy-001 "Toko Bogor Indah" (spec canonical Story 2 R2)', () => {
    const outlet = data.outlets.find((o) => o.id === 'dummy-001');
    expect(outlet).toBeDefined();
    expect(outlet?.name).toBe('Toko Bogor Indah');
  });
});

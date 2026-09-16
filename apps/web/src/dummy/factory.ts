/**
 * Deterministic master-data factory for dummy mode (JABODETABEK).
 *
 * Pure functions only — no store imports, no fetch, no Date.now().
 * `rng` (from T1 `createSeededRng`) and `window` (from T1 `dummyWindow`)
 * are injected; the same inputs always produce the same output.
 */
import type { SeededRng } from './rng';
import type { DateWindow } from './dates';
import {
  JABODETABEK_TERRITORIES,
  OUTLET_NAMES,
  PRODUCT_SKUS,
  SUPPLIER_NAMES,
} from './seed';
import type { Territory } from './seed';

export type DummyTerritory = Territory;

export interface DummyOutlet {
  id: string;
  name: string;
  territoryId: string;
  city: string;
  lat: number;
  lon: number;
}

export interface DummyProduct {
  sku: string;
  name: string;
  category: string;
  price: number;
}

export interface DummySupplier {
  id: string;
  name: string;
}

export interface MasterData {
  territories: DummyTerritory[];
  outlets: DummyOutlet[];
  products: DummyProduct[];
  suppliers: DummySupplier[];
}

/** Spec canonical outlet (Story 2 R2): always `dummy-001`. */
const CANONICAL_OUTLET_NAME = 'Toko Bogor Indah';

const PRODUCT_BASE_NAMES: string[] = [
  'Beras Premium',
  'Minyak Goreng',
  'Gula Pasir',
  'Kopi Bubuk',
  'Teh Celup',
  'Susu UHT',
  'Mi Instan',
  'Kecap Manis',
  'Saus Sambal',
  'Tepung Terigu',
  'Sabun Mandi',
  'Deterjen Bubuk',
  'Shampo',
  'Pasta Gigi',
  'Air Mineral',
  'Biskuit',
];

const PRODUCT_VARIANTS: string[] = [
  'Kemasan Kecil',
  'Kemasan Sedang',
  'Kemasan Besar',
  'Paket Hemat',
  'Karton Isi 12',
  'Karton Isi 24',
];

const PRODUCT_CATEGORIES: string[] = [
  'Sembako',
  'Minuman',
  'Makanan Ringan',
  'Kebersihan',
  'Perawatan',
  'Frozen',
];

function round6(value: number): number {
  return Math.round(value * 1_000_000) / 1_000_000;
}

function sampleCoords(
  rng: SeededRng,
  territory: Territory,
): { lat: number; lon: number } {
  const { bbox } = territory;
  return {
    lat: round6(bbox.minLat + rng.next() * (bbox.maxLat - bbox.minLat)),
    lon: round6(bbox.minLon + rng.next() * (bbox.maxLon - bbox.minLon)),
  };
}

function toOutletId(n: number): string {
  return `dummy-${String(n).padStart(3, '0')}`;
}

function buildOutlets(rng: SeededRng): DummyOutlet[] {
  const outlets: DummyOutlet[] = [];
  const usedNames = new Set<string>();
  let counter = 1;

  const pushOutlet = (name: string, territory: Territory): void => {
    const { lat, lon } = sampleCoords(rng, territory);
    outlets.push({
      id: toOutletId(counter),
      name,
      territoryId: territory.id,
      city: territory.name,
      lat,
      lon,
    });
    counter += 1;
    usedNames.add(name);
  };

  const takeName = (territoryName: string, pool: string[]): string => {
    for (const base of pool) {
      if (!usedNames.has(base)) return base;
    }
    let suffix = 2;
    let candidate = `${pool[0]} ${territoryName}`;
    while (usedNames.has(candidate)) {
      candidate = `${pool[0]} ${territoryName} ${suffix}`;
      suffix += 1;
    }
    return candidate;
  };

  const bogor = JABODETABEK_TERRITORIES.find((t) => t.id === 'bogor');
  if (!bogor) {
    throw new RangeError('buildOutlets: JABODETABEK_TERRITORIES must include bogor');
  }

  // Canonical first outlet: dummy-001 is always "Toko Bogor Indah".
  pushOutlet(CANONICAL_OUTLET_NAME, bogor);

  for (const territory of JABODETABEK_TERRITORIES) {
    const pool = rng.shuffle(OUTLET_NAMES);
    // Bogor already holds the canonical outlet, so it takes one fewer here;
    // every territory still ends up with 9..10 outlets (≈48 total).
    const extras = territory.id === 'bogor' ? rng.int(8, 9) : rng.int(9, 10);
    for (let i = 0; i < extras; i++) {
      pushOutlet(takeName(territory.name, pool), territory);
    }
  }

  return outlets;
}

function buildProducts(rng: SeededRng): DummyProduct[] {
  const count = rng.int(28, 32);
  const products: DummyProduct[] = [];
  for (let i = 0; i < count; i++) {
    const base = PRODUCT_BASE_NAMES[i % PRODUCT_BASE_NAMES.length];
    const variant =
      PRODUCT_VARIANTS[Math.floor(i / PRODUCT_BASE_NAMES.length) % PRODUCT_VARIANTS.length];
    products.push({
      sku:
        i < PRODUCT_SKUS.length
          ? PRODUCT_SKUS[i]
          : `SKU-${2001 + (i - PRODUCT_SKUS.length)}`,
      name: `${base} ${variant}`,
      category: rng.pick(PRODUCT_CATEGORIES),
      price: Math.round(rng.int(4000, 180000) / 500) * 500,
    });
  }
  return products;
}

function buildSuppliers(rng: SeededRng): DummySupplier[] {
  const count = rng.int(7, 9);
  return rng
    .shuffle(SUPPLIER_NAMES)
    .slice(0, count)
    .map((name, index) => ({
      id: `dummy-sup-${String(index + 1).padStart(2, '0')}`,
      name,
    }));
}

/**
 * Build the deterministic master dataset: 5 territories, ~48 outlets,
 * ~30 products, ~8 suppliers.
 *
 * `window` is accepted for signature compatibility with the T6 composition
 * (`buildFullDummy`); master data itself carries no timestamps.
 */
export function buildMasterData(rng: SeededRng, window: DateWindow): MasterData {
  void window;
  return {
    territories: [...JABODETABEK_TERRITORIES],
    outlets: buildOutlets(rng),
    products: buildProducts(rng),
    suppliers: buildSuppliers(rng),
  };
}

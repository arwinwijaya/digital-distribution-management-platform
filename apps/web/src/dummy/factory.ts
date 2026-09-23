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

export interface DummyDriverProfile {
  id: string;
  user_id: string;
  vehicle_type: string | null;
  plate_number: string | null;
  capacity_kg: number | null;
  service_territory_id: string | null;
  shift_start: string | null;
  shift_end: string | null;
  is_available: boolean;
  user: {
    id: string;
    name: string;
    email: string;
    role: string;
    is_active: boolean;
  };
  serviceTerritory: {
    id: string;
    name: string;
    code: string;
  } | null;
}

export interface MasterData {
  territories: DummyTerritory[];
  outlets: DummyOutlet[];
  products: DummyProduct[];
  suppliers: DummySupplier[];
  driverProfiles: DummyDriverProfile[];
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

const DRIVER_FIRST_NAMES = [
  'Budi', 'Siti', 'Ahmad', 'Dewi', 'Rudi', 'Sri', 'Agus', 'Rina',
  'Joko', 'Maya', 'Hendra', 'Lina', 'Bayu', 'Nia', 'Eko', 'Wati',
];

const DRIVER_LAST_NAMES = [
  'Santoso', 'Rahayu', 'Fauzi', 'Lestari', 'Hidayat', 'Ningsih',
  'Wijaya', 'Kartika', 'Pratama', 'Sari',
];

const DRIVER_VEHICLES = [
  { type: 'Motor', capacity: 50 },
  { type: 'Mobil Pickup', capacity: 500 },
  { type: 'Truk', capacity: 2000 },
  { type: 'Van', capacity: 800 },
];

const PLATE_PREFIXES = ['B', 'D', 'F', 'T', 'Z'];

/**
 * Build the deterministic driver roster (≥8 drivers) with stable ids that
 * align 1:1 with the `driver_id` values used by the delivery factory.
 */
function buildDriverProfiles(rng: SeededRng): DummyDriverProfile[] {
  /* Fixed size so `driver_id` (rng.int(1, 12)) always maps to a roster row. */
  const count = 12;
  const profiles: DummyDriverProfile[] = [];
  for (let i = 0; i < count; i++) {
    const vehicle = rng.pick(DRIVER_VEHICLES);
    const territory = JABODETABEK_TERRITORIES[i % JABODETABEK_TERRITORIES.length];
    const first = DRIVER_FIRST_NAMES[i % DRIVER_FIRST_NAMES.length];
    const last = DRIVER_LAST_NAMES[(i * 3) % DRIVER_LAST_NAMES.length];
    const userId = String(i + 1);
    const plate = `${rng.pick(PLATE_PREFIXES)} ${1000 + rng.int(1, 8999)} ${rng.pick(['ABC', 'DEF', 'GHI', 'JKL', 'MNO', 'PQR'])}`;
    profiles.push({
      id: `dummy-driver-${userId}`,
      user_id: userId,
      vehicle_type: vehicle.type,
      plate_number: plate,
      capacity_kg: vehicle.capacity,
      service_territory_id: territory.id,
      shift_start: '08:00',
      shift_end: '17:00',
      is_available: i % 5 !== 4,
      user: {
        id: userId,
        name: `${first} ${last}`,
        email: `driver${userId}@example.com`,
        role: 'driver',
        is_active: true,
      },
      serviceTerritory: {
        id: territory.id,
        name: territory.name,
        code: territory.id.toUpperCase().slice(0, 3),
      },
    });
  }
  return profiles;
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
    driverProfiles: buildDriverProfiles(rng),
  };
}

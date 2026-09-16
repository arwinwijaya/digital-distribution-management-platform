/**
 * Seed constants for the JABODETABEK dummy dataset.
 *
 * Pure constants only — no store, no generation at import time, no Date.now().
 */

/** Fixed, deterministic seed. NEVER derived from Date.now(). */
export const DUMMY_SEED = 'ddp-jabodetabek-v1';

export interface TerritoryAnchor {
  lat: number;
  lon: number;
}

export interface TerritoryBbox {
  minLat: number;
  maxLat: number;
  minLon: number;
  maxLon: number;
}

export interface Territory {
  id: string;
  name: string;
  anchor: TerritoryAnchor;
  bbox: TerritoryBbox;
}

/**
 * The 5 JABODETABEK territories. Every anchor lies inside the aggregate
 * bounding box: lat -6.9..-5.9, lon 105.9..107.3.
 */
export const JABODETABEK_TERRITORIES: Territory[] = [
  {
    id: 'jakarta',
    name: 'Jakarta',
    anchor: { lat: -6.2088, lon: 106.8456 },
    bbox: { minLat: -6.3745, maxLat: -6.0722, minLon: 106.6894, maxLon: 106.9734 },
  },
  {
    id: 'bogor',
    name: 'Bogor',
    anchor: { lat: -6.5971, lon: 106.806 },
    bbox: { minLat: -6.6782, maxLat: -6.4846, minLon: 106.7132, maxLon: 106.9046 },
  },
  {
    id: 'depok',
    name: 'Depok',
    anchor: { lat: -6.4025, lon: 106.7942 },
    bbox: { minLat: -6.4704, maxLat: -6.3479, minLon: 106.7262, maxLon: 106.8717 },
  },
  {
    id: 'tangerang',
    name: 'Tangerang',
    anchor: { lat: -6.1783, lon: 106.6319 },
    bbox: { minLat: -6.2746, maxLat: -6.1044, minLon: 106.5362, maxLon: 106.7295 },
  },
  {
    id: 'bekasi',
    name: 'Bekasi',
    anchor: { lat: -6.2349, lon: 106.9896 },
    bbox: { minLat: -6.3858, maxLat: -6.1438, minLon: 106.9057, maxLon: 107.0869 },
  },
];

/** Dummy outlet names (referenced relationally across orders/map/etc.). */
export const OUTLET_NAMES: string[] = [
  'Toko Bogor Indah',
  'Toko Sumber Rezeki',
  'Toko Berkah Jaya',
  'Toko Mitra Sejahtera',
  'Toko Makmur Sentosa',
  'Toko Sinar Harapan',
  'Toko Bintang Timur',
  'Toko Anugerah Pangan',
  'Toko Prima Niaga',
  'Toko Citra Mandiri',
  'Toko Karya Abadi',
  'Toko Lestari Jaya',
];

/** Dummy product SKUs. */
export const PRODUCT_SKUS: string[] = [
  'SKU-1001',
  'SKU-1002',
  'SKU-1003',
  'SKU-1004',
  'SKU-1005',
  'SKU-1006',
  'SKU-1007',
  'SKU-1008',
  'SKU-1009',
  'SKU-1010',
];

/** Dummy supplier names. */
export const SUPPLIER_NAMES: string[] = [
  'PT Sumber Pangan Nusantara',
  'CV Berkah Distribusi',
  'PT Mitra Logistik Jaya',
  'CV Bintang Niaga',
  'PT Prima Sentosa',
  'CV Karya Mandiri',
  'PT Citra Distribusi',
  'CV Anugerah Pangan',
];

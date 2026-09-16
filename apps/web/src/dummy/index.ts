/**
 * Barrel exports for the dummy-mode foundation (pure functions + constants).
 */

export { createSeededRng } from './rng';
export type { SeededRng } from './rng';

export { dummyWindow, daysBetween } from './dates';
export type { DateWindow } from './dates';

export {
  DUMMY_SEED,
  JABODETABEK_TERRITORIES,
  OUTLET_NAMES,
  PRODUCT_SKUS,
  SUPPLIER_NAMES,
} from './seed';
export type { Territory, TerritoryAnchor, TerritoryBbox } from './seed';

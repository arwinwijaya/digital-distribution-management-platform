/**
 * Barrel exports for the dummy-mode foundation (pure functions + constants).
 *
 * Re-exports all sub-modules and provides the canonical `buildFullDummy`
 * composition — the sole entry point the store's `setDummyGenerator` calls.
 */

import { createSeededRng } from './rng';
import { dummyWindow } from './dates';
import type { DateWindow } from './dates';
import { DUMMY_SEED } from './seed';
import { buildMasterData } from './factory';
import { buildTransactions } from './factory-transactions';
import { buildAggregates } from './aggregates';
import type { Aggregates } from './aggregates';
import type { MasterData } from './factory';
import type { Transactions } from './factory-transactions';
// ── Sub-module re-exports ────────────────────────────────────────────────────

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

export { buildMasterData } from './factory';
export type {
  MasterData,
  DummyOutlet,
  DummyProduct,
  DummySupplier,
  DummyTerritory,
} from './factory';

export { buildTransactions } from './factory-transactions';
export type {
  Transactions,
  DummyOrder,
  DummyOrderItem,
  DummyPayment,
  DummyInvoice,
  DummyDelivery,
} from './factory-transactions';

export { buildAggregates } from './aggregates';
export type { Aggregates } from './aggregates';

// ── Composition types ────────────────────────────────────────────────────────

/**
 * The full dummy graph: master data + transactions + aggregates flattened.
 *
 * `suppliers` appears in both MasterData (DummySupplier[]) and Aggregates
 * (SupplierPerformanceData); the aggregate value wins in the flattened object,
 * so the master array form is omitted from the intersection.
 */
export type FullDummy = Omit<MasterData, 'suppliers'> &
  Transactions &
  Aggregates;

// ── Composition ──────────────────────────────────────────────────────────────

/**
 * Build the complete deterministic dummy graph for a given "today".
 *
 * Pure composition: master data → transactions → aggregates.
 * Defaults to `new Date()` when called with no arguments — this is the seam
 * the store's `setDummyGenerator` closure pins.
 *
 * Note: `Aggregates.suppliers` (SupplierPerformanceData) shadows
 * `MasterData.suppliers` (DummySupplier[]) in the spread. This is intentional:
 * downstream data-intelligence guards consume SupplierPerformanceData, and the
 * plain master supplier array is only needed internally by the factories.
 *
 * Returns the strongly-typed union; the store-level assignment is validated
 * by `registerDummyGenerator` in install.ts.
 */
export function buildFullDummy(today?: Date): FullDummy {
  const d = today ?? new Date();
  const window: DateWindow = dummyWindow(d);
  const rng = createSeededRng(DUMMY_SEED);
  const master = buildMasterData(rng, window);
  const tx = buildTransactions(master, window);
  const agg = buildAggregates(master, tx);
  return { ...master, ...tx, ...agg };
}

/**
 * Production bootstrap: registers the canonical buildFullDummy generator.
 *
 * The ONLY place that touches the store seam from the factory side. Called once
 * at app bootstrap so the toggle works in production without a test stub.
 */
import type { DummyGenerator, DummyEntities } from './store';
import { setDummyGenerator } from './store';
import { buildFullDummy } from './index';

/**
 * Register buildFullDummy as the DummyGenerator on the store singleton.
 * Idempotent: safe to call multiple times (re-registers the same generator).
 */
export function installDummy(): void {
  const generator: DummyGenerator = (today?: Date) =>
    buildFullDummy(today) as unknown as DummyEntities;
  setDummyGenerator(generator);
}

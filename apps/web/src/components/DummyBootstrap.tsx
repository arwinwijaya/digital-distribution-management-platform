'use client';

/**
 * Registers the dummy-mode generator once at client mount.
 * Safe to import in any component tree — idempotent by contract.
 */
import { installDummy } from '@/dummy/install';

// Register immediately at module evaluation (runs once during hydration).
installDummy();

/** No-op render — purely side-effectful. */
export default function DummyBootstrap() {
  return null;
}

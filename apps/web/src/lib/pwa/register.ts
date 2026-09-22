/**
 * Service-worker registration (Phase 8, T10).
 *
 * Guarded by `NEXT_PUBLIC_PWA_ENABLED` so the PWA layer can be rolled back with
 * a single env change, and idempotent so React strict-mode double mounts (or a
 * second caller) never register twice. Never touches `navigator` during SSR.
 */

/** Guard so concurrent/repeat callers share one registration promise. */
let registration: Promise<void> | null = null;

/** True when the PWA layer is switched on for this build. */
function pwaEnabled(): boolean {
  return process.env.NEXT_PUBLIC_PWA_ENABLED === 'true';
}

/**
 * Register the app-shell service worker exactly once.
 *
 * Resolves (never rejects) so callers can fire-and-forget; registration errors
 * are swallowed because the app must keep working without a service worker.
 */
export function registerServiceWorker(): Promise<void> {
  if (registration) return registration;

  if (!pwaEnabled() || typeof navigator === 'undefined' || !('serviceWorker' in navigator)) {
    registration = Promise.resolve();
    return registration;
  }

  registration = navigator.serviceWorker
    .register('/sw.js')
    .then(() => undefined)
    .catch(() => undefined);

  return registration;
}

/** Test-only: forget the memoized registration so a fresh call can run. */
export function resetServiceWorkerRegistration(): void {
  registration = null;
}

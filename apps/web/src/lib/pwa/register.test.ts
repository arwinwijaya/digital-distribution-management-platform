/**
 * T10 — service worker registration helper.
 *
 * The helper must be a no-op unless NEXT_PUBLIC_PWA_ENABLED is "true", must be
 * idempotent (a second call never re-registers), and must never touch
 * `navigator` during SSR (guarded by `typeof navigator`).
 */
import { registerServiceWorker, resetServiceWorkerRegistration } from '@/lib/pwa/register';

describe('registerServiceWorker', () => {
  const originalEnv = process.env.NEXT_PUBLIC_PWA_ENABLED;
  const originalNavigator = Object.getOwnPropertyDescriptor(globalThis, 'navigator');

  let register: jest.Mock;

  beforeEach(() => {
    resetServiceWorkerRegistration();
    register = jest.fn().mockResolvedValue({ scope: '/' });
    Object.defineProperty(globalThis, 'navigator', {
      configurable: true,
      writable: true,
      value: { serviceWorker: { register } },
    });
  });

  afterEach(() => {
    process.env.NEXT_PUBLIC_PWA_ENABLED = originalEnv;
    if (originalNavigator) {
      Object.defineProperty(globalThis, 'navigator', originalNavigator);
    } else {
      delete (globalThis as { navigator?: unknown }).navigator;
    }
  });

  it('registers /sw.js once when the PWA flag is enabled', async () => {
    process.env.NEXT_PUBLIC_PWA_ENABLED = 'true';

    await registerServiceWorker();
    await registerServiceWorker();

    expect(register).toHaveBeenCalledTimes(1);
    expect(register).toHaveBeenCalledWith('/sw.js');
  });

  it('does nothing when the PWA flag is disabled', async () => {
    process.env.NEXT_PUBLIC_PWA_ENABLED = 'false';

    await registerServiceWorker();

    expect(register).not.toHaveBeenCalled();
  });

  it('does nothing when the flag is unset', async () => {
    delete process.env.NEXT_PUBLIC_PWA_ENABLED;

    await registerServiceWorker();

    expect(register).not.toHaveBeenCalled();
  });

  it('does nothing when serviceWorker is unsupported', async () => {
    process.env.NEXT_PUBLIC_PWA_ENABLED = 'true';
    Object.defineProperty(globalThis, 'navigator', {
      configurable: true,
      writable: true,
      value: {},
    });

    await expect(registerServiceWorker()).resolves.toBeUndefined();
    expect(register).not.toHaveBeenCalled();
  });

  it('does not throw when navigator is undefined (SSR)', async () => {
    process.env.NEXT_PUBLIC_PWA_ENABLED = 'true';
    Object.defineProperty(globalThis, 'navigator', {
      configurable: true,
      writable: true,
      value: undefined,
    });

    await expect(registerServiceWorker()).resolves.toBeUndefined();
  });
});

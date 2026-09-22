'use client';

/**
 * Registers the app-shell service worker once at client mount (Phase 8, T10).
 * No-op unless `NEXT_PUBLIC_PWA_ENABLED === 'true'`; the registration helper is
 * SSR-safe and idempotent, so this is safe to include in the root layout.
 */
import { useEffect } from 'react';
import { registerServiceWorker } from '@/lib/pwa/register';

export default function PwaBootstrap() {
  useEffect(() => {
    void registerServiceWorker();
  }, []);

  return null;
}

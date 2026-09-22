'use client';

import { useEffect, useState } from 'react';

/**
 * Track the browser's connectivity (Phase 8, T10).
 *
 * Reads `navigator.onLine` once on mount (SSR-safe default `true`) and keeps it
 * in sync through the window `online`/`offline` events. Both listeners are
 * removed on unmount so a remounting Topbar never leaks handlers.
 */
export function useOnlineStatus(): boolean {
  const [online, setOnline] = useState(true);

  useEffect(() => {
    const sync = () => setOnline(typeof navigator === 'undefined' ? true : navigator.onLine);
    sync();

    window.addEventListener('online', sync);
    window.addEventListener('offline', sync);

    return () => {
      window.removeEventListener('online', sync);
      window.removeEventListener('offline', sync);
    };
  }, []);

  return online;
}

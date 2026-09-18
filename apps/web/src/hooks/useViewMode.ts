'use client';

import { useState, useEffect, useCallback } from 'react';
import type { ViewMode } from '@/lib/product-filter';

const STORAGE_KEY = 'ui:view-mode';
const VALID_MODES: readonly ViewMode[] = ['card', 'table'];

function isValidMode(value: string | null): value is ViewMode {
  return value !== null && (VALID_MODES as readonly string[]).includes(value);
}

/**
 * Persisted product view-mode preference (card ⇄ table).
 *
 * Deliberately a SINGLE global key shared by the order form product picker and
 * the product catalog — switching one switches the other ("preferensi tampilan
 * produk"). Mirrors `useTableDensity`:
 *
 * SSR-safe: the initial state is ALWAYS `'card'`. The stored value is read from
 * `localStorage` inside `useEffect` (client-only), so there is NEVER a
 * `typeof window` guard in the render path — preventing hydration mismatches.
 *
 * Storage contract:
 * - Key:  `ui:view-mode`
 * - Value: `'card' | 'table'` (invalid values are ignored on rehydrate).
 *
 * No cross-tab sync (no `storage` event listener), matching `useTableDensity`.
 */
export function useViewMode(): { viewMode: ViewMode; setViewMode: (mode: ViewMode) => void } {
  const [viewMode, setViewModeState] = useState<ViewMode>('card');

  // Client-only rehydrate from localStorage. The render path MUST NOT touch
  // `window`/`localStorage` (hydration mismatch risk).
  useEffect(() => {
    try {
      const stored = localStorage.getItem(STORAGE_KEY);
      if (isValidMode(stored)) {
        setViewModeState(stored);
      }
    } catch {
      // localStorage may be unavailable (private mode, sandboxed iframe);
      // fall through and keep the default.
    }
  }, []);

  const setViewMode = useCallback((next: ViewMode) => {
    setViewModeState(next);
    try {
      localStorage.setItem(STORAGE_KEY, next);
    } catch {
      // Storage write may fail in private mode; the in-memory state is
      // still updated so the UI stays consistent for this session.
    }
  }, []);

  return { viewMode, setViewMode };
}

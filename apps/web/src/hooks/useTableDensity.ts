'use client';

import { useState, useEffect, useCallback } from 'react';
import type { TableDensity } from '@/lib/admin-table';

const STORAGE_KEY = 'admin:table-density';
const VALID_DENSITIES: readonly TableDensity[] = ['compact', 'default', 'comfortable'];

function isValidDensity(value: string | null): value is TableDensity {
  return value !== null && (VALID_DENSITIES as readonly string[]).includes(value);
}

/**
 * Persisted table-density preference.
 *
 * SSR-safe: the initial state is ALWAYS `'default'`. The stored value is
 * read from `localStorage` inside `useEffect` (client-only), so there is
 * NEVER a `typeof window` guard in the render path — preventing hydration
 * mismatches between server and client.
 *
 * Storage contract:
 * - Key:  `admin:table-density`
 * - Value: one of `'compact' | 'default' | 'comfortable'` (invalid values
 *   are ignored on rehydrate).
 */
export function useTableDensity(): { density: TableDensity; setDensity: (d: TableDensity) => void } {
  const [density, setDensityState] = useState<TableDensity>('default');

  // Client-only rehydrate from localStorage. The render path MUST NOT touch
  // `window`/`localStorage` (hydration mismatch risk).
  useEffect(() => {
    try {
      const stored = localStorage.getItem(STORAGE_KEY);
      if (isValidDensity(stored)) {
        setDensityState(stored);
      }
    } catch {
      // localStorage may be unavailable (private mode, sandboxed iframe);
      // fall through and keep the default.
    }
  }, []);

  const setDensity = useCallback((next: TableDensity) => {
    setDensityState(next);
    try {
      localStorage.setItem(STORAGE_KEY, next);
    } catch {
      // Storage write may fail in private mode; the in-memory state is
      // still updated so the UI stays consistent for this session.
    }
  }, []);

  return { density, setDensity };
}
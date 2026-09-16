/**
 * Centralized dummy-mode guard helpers (Option A — Zustand singleton +
 * per-API-function guard).
 *
 * - `withDummyRead` short-circuits BEFORE any network when dummy mode is ON.
 * - `commitIfCurrent` is the ONLY discard point for in-flight reads
 *   (best-effort discard at the commit layer — no AbortController threading).
 * - `useDummyRefresh` subscribes to `isDummy` flips so pages re-fetch real
 *   data automatically when the toggle changes.
 */
import { useEffect, useRef } from 'react';
import { useDummyStore, selectIsDummy } from '@/dummy/store';

export async function withDummyRead<T>(
  isDummy: boolean,
  dummyValue: T,
  realFetch: () => Promise<T>,
): Promise<T> {
  if (isDummy) return dummyValue;
  return realFetch();
}

export function commitIfCurrent<T>(
  getIsDummy: () => boolean,
  expectedFlag: boolean,
  setter: (data: T) => void,
  data: T,
): boolean {
  if (getIsDummy() !== expectedFlag) return false;
  setter(data);
  return true;
}

/**
 * Subscribes to `isDummy` flips in the Zustand store and fires `onChange`
 * whenever the flag changes — ON→OFF or OFF→ON. Each flip fires exactly once.
 *
 * Safe under React.StrictMode (double-mount): the ref guard skips the
 * initial-mount run and the effect cleans up on unmount.
 */
export function useDummyRefresh(onChange: () => void): void {
  const isDummy = useDummyStore(selectIsDummy);
  const isDummyRef = useRef(isDummy);

  useEffect(() => {
    if (isDummyRef.current !== isDummy) {
      isDummyRef.current = isDummy;
      onChange();
    }
  }, [isDummy, onChange]);
}

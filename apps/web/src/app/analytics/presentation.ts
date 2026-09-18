/**
 * Pure presentation helpers for the Analitik insight payload.
 *
 * Kept framework-free so both the page and its tests can consume them without
 * a render. All copy is id-ID; money/number parsing is delegated to
 * `@/lib/format` rather than hand-rolled here.
 */
import { parseMoney } from '@/lib/format';
import type { DeltaDirection, InsightDelta } from './types';

export interface DeltaChip {
  text: string;
  type: DeltaDirection;
}

/**
 * Build the delta chip. The chip type is derived from the ROUNDED percentage so
 * a value that displays as `0,0%` can never be styled as an "up" chip, even if
 * the server's unrounded direction says otherwise.
 */
export function formatDeltaChip(delta: InsightDelta): DeltaChip {
  if (delta.delta_percent === null || delta.delta_percent === undefined) {
    return { text: 'belum ada pembanding', type: 'neutral' };
  }

  const value = parseMoney(delta.delta_percent);
  const rounded = Math.round(value * 10) / 10;
  const magnitude = Math.abs(rounded).toLocaleString('id-ID', {
    minimumFractionDigits: 1,
    maximumFractionDigits: 1,
  });

  const type: DeltaDirection =
    rounded === 0 ? 'neutral' : delta.direction === 'neutral' ? 'neutral' : delta.direction;

  const sign = rounded > 0 ? '+' : rounded < 0 ? '-' : '';

  return { text: `${sign}${magnitude}%`, type };
}

/** id-ID copy for the backend `data_sufficiency.level` values. */
const LEVEL_LABELS: Record<string, string> = {
  insufficient: 'Data belum cukup',
  limited: 'Data terbatas',
  adequate: 'Data memadai',
};

/** Map a sufficiency level to id-ID copy; unknown/absent → null (label omitted). */
export function trustLabel(level?: string | null): string | null {
  if (!level) return null;
  return LEVEL_LABELS[level] ?? null;
}

/** Surface the forecast `method` as-is; absent/empty → null. */
export function methodLabel(method?: string | null): string | null {
  if (!method) return null;
  return method;
}

/** Surface a measurement note; absent note → null (never renders "undefined"). */
export function measurementNote(
  measurement?: { measured?: boolean; note?: string } | null,
): string | null {
  if (!measurement || !measurement.note) return null;
  return measurement.note;
}

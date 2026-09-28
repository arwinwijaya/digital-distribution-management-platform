/** Pure, deterministic filtering helpers for the geographic snapshot. */

export interface SnapshotWindow {
  start: string;
  end: string;
  timezone: string;
}

export interface DateRange {
  start: string;
  end: string;
}

export type Period = 'today' | '7d' | '30d';
export type EligibleStatus = 'New' | 'Confirmed' | 'Delivered' | 'Partially Paid';

export const ELIGIBLE_STATUSES: readonly EligibleStatus[] = [
  'New',
  'Confirmed',
  'Delivered',
  'Partially Paid',
];

const DEFAULT_STATUSES: readonly EligibleStatus[] = ['New', 'Confirmed'];
const SEMUA = 'Semua';

function daysInMonth(year: number, month: number): number {
  if (month === 2) {
    return year % 4 === 0 && (year % 100 !== 0 || year % 400 === 0) ? 29 : 28;
  }
  return [4, 6, 9, 11].includes(month) ? 30 : 31;
}

/** Shift an ISO calendar date without timezone conversion or Date arithmetic. */
function shiftDate(date: string, deltaDays: number): string {
  const [yearPart, monthPart, dayPart] = date.split('-');
  let year = Number(yearPart);
  let month = Number(monthPart);
  let day = Number(dayPart);
  const direction = deltaDays < 0 ? -1 : 1;

  for (let remaining = Math.abs(deltaDays); remaining > 0; remaining -= 1) {
    day += direction;
    if (direction < 0 && day < 1) {
      month -= 1;
      if (month < 1) {
        month = 12;
        year -= 1;
      }
      day = daysInMonth(year, month);
    } else if (direction > 0 && day > daysInMonth(year, month)) {
      day = 1;
      month += 1;
      if (month > 12) {
        month = 1;
        year += 1;
      }
    }
  }

  return `${String(year).padStart(4, '0')}-${String(month).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
}

/** Map a supported period to an inclusive range inside the snapshot window. */
export function periodToRange(window: SnapshotWindow, period: Period | string): DateRange {
  switch (period) {
    case 'today':
      return { start: window.end, end: window.end };
    case '7d':
      return { start: shiftDate(window.end, -6), end: window.end };
    case '30d':
    default:
      return { start: window.start, end: window.end };
  }
}

/** Expand the Semua selection; named selections retain their input order. */
export function expandSemua(statuses: readonly string[]): string[] {
  if (statuses.includes(SEMUA)) return [...ELIGIBLE_STATUSES];
  return [...statuses];
}

/** Normalize status selections, preserving an intentional empty selection. */
export function normalizeStatuses(statuses: readonly string[]): EligibleStatus[] {
  if (statuses.length === 0) return [];

  const expanded = expandSemua(statuses);
  if (expanded.includes(SEMUA)) return [...ELIGIBLE_STATUSES];

  const recognized = expanded.filter((status): status is EligibleStatus =>
    (ELIGIBLE_STATUSES as readonly string[]).includes(status),
  );
  const unique = recognized.filter((status, index) => recognized.indexOf(status) === index);
  return unique.length > 0 ? unique : [...DEFAULT_STATUSES];
}

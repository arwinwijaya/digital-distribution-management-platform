/**
 * Date-window helpers for dummy mode.
 *
 * All calendar math is performed against the Asia/Jakarta timezone so that
 * results are identical regardless of the machine's local timezone. Pure
 * functions only — no store.
 */

export interface DateWindow {
  /** ISO date string (YYYY-MM-DD), end − 60 days. */
  start: string;
  /** ISO date string (YYYY-MM-DD), the "today" (Asia/Jakarta) of the input. */
  end: string;
}

/** Resolve the year/month/day of `date` on the Asia/Jakarta calendar. */
function jakartaParts(date: Date): { year: number; month: number; day: number } {
  const parts = new Intl.DateTimeFormat('en-US', {
    timeZone: 'Asia/Jakarta',
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
  }).formatToParts(date);

  const get = (type: string): number =>
    Number(parts.find((part) => part.type === type)?.value);

  return { year: get('year'), month: get('month'), day: get('day') };
}

function toIsoUtc(date: Date): string {
  return date.toISOString().slice(0, 10);
}

/**
 * Rolling 60-day window ending "today" (Asia/Jakarta).
 *
 * `end` is the Jakarta calendar date of `today`; `start` is 60 days earlier
 * (inclusive), so the window spans 61 dates.
 */
export function dummyWindow(today: Date = new Date()): DateWindow {
  const { year, month, day } = jakartaParts(today);
  const endDate = new Date(Date.UTC(year, month - 1, day));
  const startDate = new Date(endDate);
  startDate.setUTCDate(startDate.getUTCDate() - 60);
  return { start: toIsoUtc(startDate), end: toIsoUtc(endDate) };
}

/**
 * All ISO date strings (YYYY-MM-DD) from `start` through `end`, inclusive.
 */
export function daysBetween(start: string, end: string): string[] {
  const days: string[] = [];
  const cursor = new Date(`${start}T00:00:00Z`);
  const finish = new Date(`${end}T00:00:00Z`);
  while (cursor.getTime() <= finish.getTime()) {
    days.push(cursor.toISOString().slice(0, 10));
    cursor.setUTCDate(cursor.getUTCDate() + 1);
  }
  return days;
}

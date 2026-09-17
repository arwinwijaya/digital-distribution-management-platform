/**
 * Shared number/money formatters.
 *
 * Money arrives from the API as a fixed-2-decimal string (e.g. `"150000.00"`),
 * so parsing must strip non-numeric characters before coercing. Kept in one
 * place because the admin/sales pages previously each carried their own copy.
 */

/** Parse a monetary string-or-number (fixed 2-decimal) into a display number. */
export function parseMoney(value: string | number | null | undefined): number {
  if (value === null || value === undefined) return 0;
  const n = typeof value === 'number' ? value : Number(String(value).replace(/[^0-9.-]/g, ''));
  return Number.isFinite(n) ? n : 0;
}

/** Format a monetary string-or-number into id-ID Rupiah display (e.g. `Rp 150.000`). */
export function formatRupiah(value: string | number | null | undefined): string {
  return `Rp ${parseMoney(value).toLocaleString('id-ID')}`;
}

/** Display a percentage with 2 decimals and a `%` suffix (e.g. `85.00%`). */
export function formatPercentage(value: string | number | null | undefined): string {
  return `${parseMoney(value).toFixed(2)}%`;
}

/** Format a plain integer/quantity with id-ID thousands separators (e.g. `1.248`). */
export function formatCount(value: string | number | null | undefined): string {
  return parseMoney(value).toLocaleString('id-ID');
}

/** Format a decimal quantity with id-ID separators, at most 2 fraction digits (e.g. `1,67`). */
export function formatDecimal(value: string | number | null | undefined): string {
  return parseMoney(value).toLocaleString('id-ID', { maximumFractionDigits: 2 });
}

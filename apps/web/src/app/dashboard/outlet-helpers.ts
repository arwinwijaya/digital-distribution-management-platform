// Shared pure helpers for outlet dashboard — no fetch, no store, no React
// Implements Story 2 (status labels), Story 3 (period window), Story 5 (favorites)

export type OutletStatusResult = {
  label: string;
  hint: string;
  ctaKey: string | null;
  isFallback?: boolean;
  tooltip?: string;
};

export type PeriodWindowResult = {
  start: string; // YYYY-MM-DD in Asia/Jakarta (calendar label)
  end: string; // YYYY-MM-DD in Asia/Jakarta (calendar label)
  startInstantUTC: string; // ISO UTC instant for start at 00:00 Asia/Jakarta
  endInstantUTC: string; // ISO UTC instant for now
  label: string;
};

export type FavoriteItem = {
  product_id: number;
  displayName: string;
  totalQty: number;
};

// ---- Story 2: Status label mapping ----

function normalizeStatus(status: string): string {
  if (status === 'Canceled') return 'Cancelled';
  return status;
}

function escapeHtml(value: string): string {
  return value
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#039;');
}

function truncateWithEllipsis(value: string, maxLen: number): string {
  if (value.length <= maxLen) return value;
  return value.slice(0, maxLen) + '…';
}

export function toOutletStatus(status: string): OutletStatusResult {
  const normalized = normalizeStatus(status);
  switch (normalized) {
    case 'New':
      return { label: 'Menunggu konfirmasi admin', hint: 'Tidak ada aksi', ctaKey: null };
    case 'Confirmed':
      return { label: 'Dikonfirmasi', hint: 'Pesanan sedang diproses', ctaKey: null };
    case 'Delivered':
      return { label: 'Terkirim, periksa invoice', hint: 'Segera periksa tagihan', ctaKey: 'view_invoice' };
    case 'Partially Paid':
      return { label: 'Dibayar sebagian', hint: 'Sisa pembayaran tertunda', ctaKey: 'make_payment' };
    case 'Paid':
      return { label: 'Lunas', hint: 'Pembayaran selesai', ctaKey: null };
    case 'Cancelled':
      return { label: 'Dibatalkan', hint: 'Pesanan dibatalkan', ctaKey: null };
    default: {
      // Fallback: escape HTML, truncate to 20 chars + ellipsis, tooltip Lainnya
      const escaped = escapeHtml(status);
      const display = escaped.length === 0 ? 'Lainnya' : truncateWithEllipsis(escaped, 20);
      const label = display.length === 0 ? 'Lainnya' : display;
      return {
        label,
        hint: 'Status lain',
        ctaKey: null,
        isFallback: true,
        tooltip: 'Lainnya',
      };
    }
  }
}

// ---- Story 3: Period window N calendar dates inclusive ----

function jakartaDateStringFromDate(date: Date): string {
  return date.toLocaleDateString('sv-SE', { timeZone: 'Asia/Jakarta' });
}

export function periodWindow(days: 7 | 30 | 90, now: Date = new Date()): PeriodWindowResult {
  const jakartaTodayStr = jakartaDateStringFromDate(now);
  const [y, m, d] = jakartaTodayStr.split('-').map(Number);
  const jakartaTodayUTC = new Date(Date.UTC(y, m - 1, d));
  const startUTCDate = new Date(jakartaTodayUTC);
  startUTCDate.setUTCDate(startUTCDate.getUTCDate() - (days - 1));
  const startStr = startUTCDate.toISOString().slice(0, 10);
  const endStr = jakartaTodayStr;
  // Exact UTC instants: start at 00:00 Asia/Jakarta, end is now
  const startInstantUTC = new Date(`${startStr}T00:00:00+07:00`).toISOString();
  const endInstantUTC = now.toISOString();
  let label = `${days} hari terakhir`;
  if (days === 7) label = '7 hari terakhir';
  if (days === 30) label = '30 hari terakhir';
  if (days === 90) label = '90 hari terakhir';
  return { start: startStr, end: endStr, startInstantUTC, endInstantUTC, label };
}

// ---- Story 5: Favorite aggregation top-5 ----

type OrderLike = {
  created_at?: string;
  items?: Array<{
    product_id?: number | null;
    product_name?: string | null;
    quantity?: number | null;
  } | null> | null;
};

export function topFavorites(orders: OrderLike[], limit: number = 5): FavoriteItem[] {
  const map = new Map<number, { displayName: string; totalQty: number; latestTime: string | number }>();

  for (let orderIdx = 0; orderIdx < orders.length; orderIdx++) {
    const order = orders[orderIdx];
    if (!order) continue;
    const items = order.items ?? [];
    const orderTime: string | number = order.created_at ?? orderIdx;

    for (const rawItem of items) {
      if (!rawItem) continue;
      const pidRaw = rawItem.product_id;
      if (pidRaw === null || pidRaw === undefined) continue;
      const pid = Number(pidRaw);
      if (!Number.isFinite(pid) || pid <= 0) continue;

      const qtyRaw = rawItem.quantity;
      const qtyNum = Number(qtyRaw);
      const safeQty = Number.isFinite(qtyNum) ? Math.trunc(qtyNum) : 0;

      const rawName = rawItem.product_name;
      const hasRealName = typeof rawName === 'string' && rawName.trim() !== '';
      const fallbackName = `Produk #${pid}`;
      const effectiveName = hasRealName ? (rawName as string) : fallbackName;

      const existing = map.get(pid);
      if (!existing) {
        map.set(pid, { displayName: effectiveName, totalQty: safeQty, latestTime: orderTime });
      } else {
        existing.totalQty += safeQty;
        const isNewer = orderTime >= existing.latestTime;
        if (isNewer && hasRealName) {
          existing.displayName = effectiveName;
          existing.latestTime = orderTime;
        } else if (existing.displayName.startsWith('Produk #') && hasRealName) {
          existing.displayName = effectiveName;
        }
      }
    }
  }

  const list: FavoriteItem[] = Array.from(map.entries(), ([product_id, data]) => ({ product_id, displayName: data.displayName, totalQty: data.totalQty }));

  list.sort((a, b) => {
    if (b.totalQty !== a.totalQty) return b.totalQty - a.totalQty;
    return a.displayName.localeCompare(b.displayName, 'id-ID');
  });

  return list.slice(0, limit);
}

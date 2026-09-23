/**
 * Admin live tracking API — fetches the latest position + bounded history for a delivery.
 * Guarded with `withDummyRead` so zero network while dummy mode is ON.
 */
import { apiUrl, authHeaders, getStoredToken } from '@/lib/api';
import { withDummyRead } from '@/dummy/guards';
import { useDummyStore } from '@/dummy/store';
import type { FullDummy } from '@/dummy';

export type TrackPoint = {
  latitude: number;
  longitude: number;
  accuracy_m: number | null;
  recorded_at: string;
};

export type TrackResponse = {
  delivery_id: number;
  status: string;
  driver: { id: number; name: string } | null;
  last_position: TrackPoint | null;
  pings: TrackPoint[];
};

/** Numeric suffix of a `dummy-###` id, 0 when unparseable. */
function numericSuffix(id: string): number {
  const n = Number(String(id).replace(/^dummy-/, ''));
  return Number.isFinite(n) ? n : 0;
}

/**
 * Synthesize a deterministic tracking path for a delivery in dummy mode.
 * Resolves the driver name from `driverProfiles` and the destination from the
 * linked order's outlet, then interpolates a depot → outlet path with 5 pings.
 */
function buildDummyTrack(dummy: FullDummy, deliveryId: number): TrackResponse | null {
  const delivery = dummy.deliveries.find((d) => d.id === deliveryId);
  if (!delivery) return null;

  const profile = dummy.driverProfiles.find(
    (p) => String(p.user_id) === String(delivery.driver_id),
  );
  const driverName = profile?.user.name ?? `Driver #${delivery.driver_id}`;

  const order = dummy.orders.find((o) => o.id === delivery.order_id);
  /* `order.outlet_id` is the 1-based outlet index (see factory-transactions). */
  const outlet = order ? dummy.outlets[order.outlet_id - 1] : null;
  const outletNumeric = outlet ? numericSuffix(outlet.id) : 1;
  const destLat = -6.2 + outletNumeric * 0.003;
  const destLng = 106.816 + outletNumeric * 0.003;

  // Depot origin (Jakarta center) → destination outlet.
  const depotLat = -6.175;
  const depotLng = 106.827;

  const numPings = 5;
  const pings: TrackPoint[] = [];
  for (let i = 0; i < numPings; i++) {
    const t = i / (numPings - 1);
    const lat = depotLat + (destLat - depotLat) * t + (deliveryId + i) * 0.0001;
    const lng = depotLng + (destLng - depotLng) * t + (deliveryId + i) * 0.0001;
    pings.push({
      latitude: lat,
      longitude: lng,
      accuracy_m: 10 + i * 2,
      recorded_at: `2026-09-22T${String(8 + i).padStart(2, '0')}:00:00Z`,
    });
  }

  return {
    delivery_id: deliveryId,
    status: delivery.status,
    driver: { id: delivery.driver_id, name: driverName },
    last_position: pings[pings.length - 1],
    pings,
  };
}

/**
 * Fetch live tracking data for a delivery.
 * In dummy mode returns a synthesized path — zero network.
 */
export async function getTrack(deliveryId: number): Promise<TrackResponse> {
  const { isDummy, dummyEntities } = useDummyStore.getState();
  const dummy = dummyEntities as FullDummy | null;
  const dummyValue: TrackResponse | null = isDummy && dummy
    ? buildDummyTrack(dummy, deliveryId)
    : null;

  return withDummyRead(isDummy, dummyValue as TrackResponse, async () => {
    const token = getStoredToken() ?? '';
    const response = await fetch(apiUrl(`/admin/deliveries/${deliveryId}/track`), {
      headers: authHeaders(token),
    });
    const body = await response.json();
    if (!response.ok)
      throw new Error(body.message || 'Tracking tidak dapat dimuat.');
    return body.data as TrackResponse;
  });
}

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

/** Fallback label when dummy has no matching driver master row. */
function buildDummyTrack(dummy: FullDummy, deliveryId: number): TrackResponse | null {
  const delivery = dummy.deliveries.find((d) => d.id === deliveryId);
  if (!delivery) return null;

  /* Dummy has no locationPings — synthesize a single static point near Jakarta. */
  const lastPosition: TrackPoint = {
    latitude: -6.2 + (deliveryId % 5) * 0.01,
    longitude: 106.816_666 + (deliveryId % 5) * 0.01,
    accuracy_m: 10,
    recorded_at: new Date().toISOString(),
  };

  return {
    delivery_id: deliveryId,
    status: delivery.status,
    driver: { id: delivery.driver_id, name: `Driver #${delivery.driver_id}` },
    last_position: lastPosition,
    pings: [lastPosition],
  };
}

/**
 * Fetch live tracking data for a delivery.
 * In dummy mode returns a static fixture — zero network.
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

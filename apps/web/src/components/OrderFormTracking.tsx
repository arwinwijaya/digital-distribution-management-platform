'use client';

import { FormEvent, useState } from 'react';
import { Input, Button, Card, StatusBadge } from '@/components/ui';
import { trackOrder } from '@/components/OrderForm';
import type { Order } from '@/components/OrderForm';

interface Props {
  token: string;
  trackingId: string;
  setTrackingId: (value: string) => void;
  onError: (message: string) => void;
}

/** Track order form + history display, self-contained. */
export default function OrderFormTracking({ token, trackingId, setTrackingId, onError }: Props) {
  const [tracked, setTracked] = useState<Order | null>(null);

  async function handleTrackOrder(event: FormEvent) {
    event.preventDefault();
    onError('');
    if (!trackingId.trim()) return;
    try {
      const result = await trackOrder(token, trackingId);
      setTracked(result);
    } catch (reason) {
      onError(reason instanceof Error ? reason.message : 'Pesanan tidak ditemukan.');
    }
  }

  return (
    <Card className="p-5">
      <h2 className="text-base font-semibold text-gray-900">Lacak pesanan</h2>
      <form onSubmit={handleTrackOrder} className="mt-3 flex gap-2">
        <Input
          aria-label="Nomor pesanan"
          value={trackingId}
          onChange={(event) => setTrackingId(event.target.value)}
          placeholder="Masukkan ID pesanan"
          data-testid="order-track-input"
        />
        <Button type="submit" variant="secondary" data-testid="order-track-submit">
          Lacak
        </Button>
      </form>
      {tracked && (
        <div className="mt-4 border-t border-gray-100 pt-4">
          <div className="flex items-center gap-2">
            <strong>{tracked.order_id}</strong>
            <StatusBadge status={tracked.status} />
          </div>
          <ol className="mt-3 space-y-2 border-l-2 border-primary-100 pl-4 text-sm text-gray-600">
            {(tracked.status_history || []).map((history) => (
              <li key={`${history.status}-${history.created_at}`}>
                <span className="font-medium text-gray-800">{history.status}</span> · {new Date(history.created_at).toLocaleString('id-ID')}
              </li>
            ))}
          </ol>
        </div>
      )}
    </Card>
  );
}
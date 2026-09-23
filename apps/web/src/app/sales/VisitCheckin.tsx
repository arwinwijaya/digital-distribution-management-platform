'use client';

import { useState } from 'react';
import { Button } from '@/components/ui';
import { checkInVisit, checkOutVisit, type Visit, type CheckInCoords } from '@/app/sales/api';

const GEO_OPTIONS: PositionOptions = { enableHighAccuracy: true, timeout: 10000, maximumAge: 0 };

function getCurrentPosition(): Promise<CheckInCoords> {
  return new Promise((resolve, reject) => {
    if (typeof navigator === 'undefined' || !navigator.geolocation) {
      reject(new Error('Geolokasi tidak didukung perangkat ini.'));
      return;
    }
    navigator.geolocation.getCurrentPosition(
      (position) => resolve({
        latitude: position.coords.latitude,
        longitude: position.coords.longitude,
        accuracy_m: Math.round(position.coords.accuracy),
      }),
      (error) => reject(new Error(geoErrorMessage(error))),
      GEO_OPTIONS,
    );
  });
}

function geoErrorMessage(error: GeolocationPositionError): string {
  if (error.code === 1) return 'Izin lokasi ditolak. Aktifkan izin geolokasi untuk melanjutkan.';
  if (error.code === 3) return 'Waktu permintaan lokasi habis. Coba lagi.';
  return 'Lokasi tidak dapat dibaca. Pastikan GPS aktif.';
}

function formatDateTime(iso: string): string {
  const d = new Date(iso);
  return d.toLocaleString('id-ID', {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  });
}

interface VisitCheckinProps {
  visit: Visit;
  onUpdated: (updatedVisit: Visit) => void;
}

export default function VisitCheckin({ visit, onUpdated }: VisitCheckinProps) {
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState('');

  const handleAction = async (action: 'check-in' | 'check-out') => {
    setError('');
    setLoading(true);
    try {
      const coords = await getCurrentPosition();
      const updated = action === 'check-in'
        ? await checkInVisit(visit.id, coords)
        : await checkOutVisit(visit.id, coords);
      onUpdated(updated);
    } catch (reason) {
      setError(reason instanceof Error ? reason.message : 'Aksi kunjungan gagal.');
    } finally {
      setLoading(false);
    }
  };

  const checkedIn = Boolean(visit.check_in_at);
  const completed = Boolean(visit.check_out_at);

  if (completed) {
    return (
      <div className="space-y-2">
        <p className="text-sm text-gray-600">
          <span className="font-medium text-success-700">Selesai</span>
        </p>
        <p className="text-xs text-gray-500">
          Check-in: {formatDateTime(visit.check_in_at!)}<br />
          Check-out: {formatDateTime(visit.check_out_at!)}
        </p>
      </div>
    );
  }

  if (checkedIn) {
    return (
      <div className="space-y-2">
        <Button
          type="button"
          variant="outline"
          disabled={loading}
          onClick={() => void handleAction('check-out')}
        >
          {loading ? 'Memproses...' : 'Check-out'}
        </Button>
        <p className="text-xs text-gray-500">
          Sudah check-in: {formatDateTime(visit.check_in_at!)}
        </p>
        {error && <p className="text-sm text-danger-700">{error}</p>}
      </div>
    );
  }

  return (
    <div className="space-y-2">
      <Button
        type="button"
        disabled={loading}
        onClick={() => void handleAction('check-in')}
      >
        {loading ? 'Memproses...' : 'Check-in'}
      </Button>
      {error && <p className="text-sm text-danger-700">{error}</p>}
    </div>
  );
}
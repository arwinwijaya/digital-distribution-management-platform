'use client';

import { useEffect, useMemo, useState } from 'react';
import LoginForm from '@/components/LoginForm';
import { apiUrl, authHeaders, getStoredToken } from '@/lib/api';
import { loadDeliveries, type Delivery } from '@/app/delivery/api';
import { filterDeliveries } from '@/app/delivery/filters';
import { useDummyRefresh } from '@/dummy/guards';
import { useDummyStore } from '@/dummy/store';
import { updateDummyDelivery } from '@/dummy/mutations';
import { Button, Card, EmptyState, Input, Modal, PageHeader, Select, StatusBadge } from '@/components/ui';
import PodCapture from '@/components/delivery/PodCapture';
import { formatDateTime } from '@/lib/admin-table';
import { formatRupiah } from '@/lib/format';

type Proof = { recipient: string; url: string };

export type DeliveryStatusInput = { status: string; recipient_name?: string; proof_of_delivery_url?: string };

export async function updateDeliveryStatus(token: string, deliveryId: number, payload: DeliveryStatusInput) {
  if (useDummyStore.getState().isDummy) {
    return updateDummyDelivery(deliveryId, payload.status, {
      recipient_name: payload.recipient_name,
      proof_of_delivery_url: payload.proof_of_delivery_url,
    });
  }
  const response = await fetch(apiUrl(`/deliveries/${deliveryId}/status`), { method: 'PATCH', headers: authHeaders(token), body: JSON.stringify(payload) });
  const body = await response.json();
  if (!response.ok) throw new Error(body.message || 'Status pengiriman gagal diperbarui.');
  return body.data as Delivery;
}

/** One row of the delivery timeline; renders an em dash when the stamp is absent. */
function TimelineRow({ label, value }: { label: string; value: string | null }) {
  return (
    <div className="flex items-baseline justify-between gap-4 py-1">
      <span className="text-xs text-gray-500">{label}</span>
      <span className="text-xs font-medium text-gray-800">{formatDateTime(value)}</span>
    </div>
  );
}

/** Enriched, read-only detail block revealed by the row toggle. */
function DeliveryDetail({ delivery }: { delivery: Delivery }) {
  const order = delivery.order;
  const outlet = order?.outlet ?? null;
  const destination = outlet ? [outlet.address, outlet.city].filter(Boolean).join(', ') || '—' : '—';

  return (
    <div className="mt-4 grid gap-5 rounded-lg border border-gray-100 bg-gray-50 p-4 lg:grid-cols-3">
      <div>
        <h4 className="mb-2 text-xs font-semibold tracking-wide text-gray-500 uppercase">Tujuan</h4>
        <p className="text-sm font-medium text-gray-900">{outlet?.name ?? 'Outlet tidak diketahui'}</p>
        <p className="mt-0.5 text-xs text-gray-600">{destination}</p>
        <dl className="mt-3 space-y-0.5">
          <div className="flex justify-between gap-4">
            <dt className="text-xs text-gray-500">Driver</dt>
            <dd className="text-xs font-medium text-gray-800">{delivery.driver?.name ?? `Driver #${delivery.driver_id}`}</dd>
          </div>
          <div className="flex justify-between gap-4">
            <dt className="text-xs text-gray-500">Sales</dt>
            <dd className="text-xs font-medium text-gray-800">{order?.sales?.name ?? delivery.assigned_by?.name ?? '—'}</dd>
          </div>
          <div className="flex justify-between gap-4">
            <dt className="text-xs text-gray-500">Penerima</dt>
            <dd className="text-xs font-medium text-gray-800">{delivery.recipient_name ?? '—'}</dd>
          </div>
        </dl>
      </div>

      <div>
        <h4 className="mb-2 text-xs font-semibold tracking-wide text-gray-500 uppercase">Linimasa</h4>
        <TimelineRow label="Ditugaskan" value={delivery.assigned_at} />
        <TimelineRow label="Mulai antar" value={delivery.started_at} />
        <TimelineRow label="Terkirim" value={delivery.delivered_at} />
        {(delivery.failure_reason || delivery.notes) && (
          <div className="mt-3 border-t border-gray-200 pt-2">
            {delivery.failure_reason && <p className="text-xs text-danger-700">Kendala: {delivery.failure_reason}</p>}
            {delivery.notes && <p className="mt-1 text-xs text-gray-600">Catatan: {delivery.notes}</p>}
          </div>
        )}
      </div>

      <div>
        <h4 className="mb-2 text-xs font-semibold tracking-wide text-gray-500 uppercase">Rincian pesanan</h4>
        {order ? (
          <>
            <ul className="space-y-1.5">
              {order.items.map((item, index) => (
                <li key={`${item.product_id}-${index}`} className="flex justify-between gap-3 text-xs">
                  <span className="text-gray-700">
                    {item.product_name ?? `Produk #${item.product_id}`}
                    <span className="text-gray-400"> × {item.quantity}</span>
                  </span>
                  <span className="font-medium text-gray-800">{formatRupiah(item.subtotal)}</span>
                </li>
              ))}
              {order.items.length === 0 && <li className="text-xs text-gray-500">Tidak ada rincian item.</li>}
            </ul>
            <div className="mt-3 flex justify-between border-t border-gray-200 pt-2 text-sm">
              <span className="font-medium text-gray-700">Total</span>
              <span className="font-bold text-gray-900">{formatRupiah(order.total_amount)}</span>
            </div>
          </>
        ) : (
          <p className="text-xs text-gray-500">Detail pesanan tidak tersedia.</p>
        )}
      </div>
    </div>
  );
}

export default function DeliveryPage() {
  const [token, setToken] = useState<string | null>(null);
  const [deliveries, setDeliveries] = useState<Delivery[]>([]);
  const [error, setError] = useState('');
  const [loading, setLoading] = useState(false);
  const [proof, setProof] = useState<Record<number, Proof>>({});
  const [completing, setCompleting] = useState<number | null>(null);
  const [expanded, setExpanded] = useState<Record<number, boolean>>({});
  // PodCapture modal state
  const [capturingPod, setCapturingPod] = useState<number | null>(null);
  // Filter state (applied client-side over the loaded rows).
  const [search, setSearch] = useState('');
  const [statusFilter, setStatusFilter] = useState('');
  const [dateFrom, setDateFrom] = useState('');
  const [dateTo, setDateTo] = useState('');

  const load = async (nextToken: string) => { setLoading(true); try { setDeliveries(await loadDeliveries(nextToken)); setError(''); } catch (reason) { setError(reason instanceof Error ? reason.message : 'Pengiriman tidak dapat dimuat.'); } finally { setLoading(false); } };
  useEffect(() => { const stored = getStoredToken(); setToken(stored); if (stored) load(stored); }, []);
  useDummyRefresh(() => { if (token) void load(token); });

  // Status options come from the loaded rows, so both the real vocabulary
  // (assigned/in_progress) and the dummy one (pending/in_transit) are filterable.
  const statusOptions = useMemo(() => Array.from(new Set(deliveries.map((delivery) => delivery.status))).sort(), [deliveries]);
  const filtered = useMemo(() => filterDeliveries(deliveries, { search, status: statusFilter, dateFrom, dateTo }), [deliveries, search, statusFilter, dateFrom, dateTo]);
  const filtersActive = Boolean(search.trim() || statusFilter || dateFrom || dateTo);
  const resetFilters = () => { setSearch(''); setStatusFilter(''); setDateFrom(''); setDateTo(''); };

  const updateStatus = async (delivery: Delivery, status: string) => { if (!token) return; const details = proof[delivery.id]; if (status === 'delivered' && (!details?.recipient.trim() || !details.url.trim())) { setError('Nama penerima dan URL bukti wajib diisi.'); return; } setCompleting(delivery.id); try { const payload = status === 'delivered' ? { status, recipient_name: details.recipient.trim(), proof_of_delivery_url: details.url.trim() } : { status }; await updateDeliveryStatus(token, delivery.id, payload); setError(''); await load(token); } catch (reason) { setError(reason instanceof Error ? reason.message : 'Status pengiriman gagal diperbarui.'); } finally { setCompleting(null); } };

  if (!token) return <div className="mx-auto max-w-6xl"><PageHeader title="Pengiriman" description="Kelola tugas dan bukti pengiriman." /><LoginForm expectedRole="driver" onLogin={(nextToken) => { setToken(nextToken); load(nextToken); }} /></div>;

  return (
    <div className="mx-auto max-w-6xl">
      <PageHeader title="Pengiriman" description="Cari, saring, dan perbarui status pengiriman beserta bukti serah terima." />

      <Card className="mb-5 p-4">
        <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-5 lg:items-end">
          <Input label="Cari" placeholder="No. pesanan, outlet, penerima, driver" value={search} onChange={(event) => setSearch(event.target.value)} />
          <Select label="Status" value={statusFilter} onChange={(event) => setStatusFilter(event.target.value)}>
            <option value="">Semua status</option>
            {statusOptions.map((status) => <option key={status} value={status}>{status}</option>)}
          </Select>
          <Input label="Dari tanggal" type="date" value={dateFrom} onChange={(event) => setDateFrom(event.target.value)} />
          <Input label="Sampai tanggal" type="date" value={dateTo} onChange={(event) => setDateTo(event.target.value)} />
          <Button variant="secondary" onClick={resetFilters} disabled={!filtersActive}>Reset filter</Button>
        </div>
        <p className="mt-3 text-xs text-gray-500" role="status">
          Menampilkan {filtered.length} dari {deliveries.length} pengiriman{filtersActive ? ' (difilter)' : ''}.
        </p>
      </Card>

      {error && <p role="alert" className="mb-5 rounded-lg border border-danger-200 bg-danger-50 p-3 text-sm text-danger-700">{error}</p>}

      {loading ? (
        <p className="text-sm text-gray-500">Memuat pengiriman...</p>
      ) : (
        <Card>
          {deliveries.length === 0 ? (
            <EmptyState icon={<span>🚚</span>} title="Tidak ada tugas pengiriman" description="Tugas yang ditugaskan kepada Anda akan tampil di sini." />
          ) : filtered.length === 0 ? (
            <EmptyState icon={<span>🔍</span>} title="Tidak ada hasil" description="Tidak ada pengiriman yang cocok dengan filter. Coba ubah atau reset filter." />
          ) : (
            <div className="divide-y divide-gray-100">
              {filtered.map((delivery) => {
                const details = proof[delivery.id] || { recipient: '', url: '' };
                const isOpen = Boolean(expanded[delivery.id]);
                return (
                  <div key={delivery.id} className="p-5">
                    <div className="flex flex-wrap items-center justify-between gap-3">
                      <button
                        type="button"
                        onClick={() => setExpanded({ ...expanded, [delivery.id]: !isOpen })}
                        aria-expanded={isOpen}
                        className="flex items-center gap-2 text-left"
                      >
                        <span aria-hidden className={`text-gray-400 transition-transform ${isOpen ? 'rotate-90' : ''}`}>▶</span>
                        <span>
                          <span className="font-semibold text-gray-900">Pesanan #{delivery.order_id}</span>
                          <span className="ml-2 text-xs text-gray-500">{delivery.order?.outlet?.name ?? `Driver #${delivery.driver_id}`}</span>
                        </span>
                      </button>
                      <div className="flex items-center gap-3">
                        <StatusBadge status={delivery.status} />
                        {delivery.status === 'assigned' && <Button size="sm" onClick={() => updateStatus(delivery, 'in_progress')}>Mulai antar</Button>}
                      </div>
                    </div>

                    {isOpen && <DeliveryDetail delivery={delivery} />}

                    {delivery.status === 'in_progress' && (
                      <div className="mt-4 space-y-3 rounded-lg bg-gray-50 p-4">
                        <div className="flex flex-wrap items-center justify-between gap-2">
                          <p className="text-sm font-medium text-gray-700">Bukti serah terima</p>
                          <Button size="sm" onClick={() => setCapturingPod(delivery.id)}>
                            📷 Ambil bukti (foto + tanda tangan)
                          </Button>
                        </div>
                        <div className="grid gap-3 sm:grid-cols-[1fr_1fr_auto] sm:items-end">
                          <Input label="Nama penerima" value={details.recipient} onChange={(event) => setProof({ ...proof, [delivery.id]: { ...details, recipient: event.target.value } })} placeholder="Nama penerima" />
                          <Input label="URL bukti pengiriman" type="url" value={details.url} onChange={(event) => setProof({ ...proof, [delivery.id]: { ...details, url: event.target.value } })} placeholder="https://..." />
                          <Button disabled={completing === delivery.id} onClick={() => updateStatus(delivery, 'delivered')}>{completing === delivery.id ? 'Menyimpan...' : 'Tandai terkirim'}</Button>
                        </div>
                        <p className="text-xs text-gray-500">Gunakan tombol kamera untuk menangkap foto + tanda tangan, atau isi URL bukti secara manual.</p>
                      </div>
                    )}
                  </div>
                );
              })}
            </div>
          )}
        </Card>
      )}

      <Modal
        open={capturingPod !== null}
        onClose={() => setCapturingPod(null)}
        title="Bukti serah terima (PoD)"
      >
        {capturingPod !== null && (
          <PodCapture
            deliveryId={capturingPod}
            onSuccess={() => {
              setCapturingPod(null);
              if (token) void load(token);
            }}
          />
        )}
      </Modal>
    </div>
  );
}

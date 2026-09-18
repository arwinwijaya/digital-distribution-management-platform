'use client';

import { useCallback, useEffect, useRef, useState } from 'react';
import LoginForm from '@/components/LoginForm';
import { apiUrl, authHeaders, getStoredToken } from '@/lib/api';
import type { Order } from '@/components/OrderForm';
import { Button, Card, EmptyState, PageHeader, StatusBadge, Table, TableSummary } from '@/components/ui';
import { formatDateTime, type ColumnSort } from '@/lib/admin-table';

/** Local row type: the shared `Order` shape plus the list-only timestamps. */
type AdminOrder = Order & { created_at?: string | null; updated_at?: string | null };

const PAGE_LIMIT = 100;

export default function AdminOrdersPage() {
  const [token, setToken] = useState<string | null>(null);
  const [orders, setOrders] = useState<AdminOrder[]>([]);
  const [selected, setSelected] = useState<Order | null>(null);
  const [ready, setReady] = useState(false);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);
  // Table state
  const [sort, setSort] = useState<ColumnSort>({ column: 'created_at', order: 'desc' });
  const [cursor, setCursor] = useState(0);
  const [total, setTotal] = useState<number>();
  const [hasMore, setHasMore] = useState(false);

  // Latest sort/cursor readable inside `loadOrders` WITHOUT adding them to its
  // dependency list (which would otherwise re-run the mount effect on change).
  const sortRef = useRef(sort);
  sortRef.current = sort;
  const cursorRef = useRef(cursor);
  cursorRef.current = cursor;

  const loadOrders = useCallback(async (authToken: string, opts?: { resetCursor?: boolean; cursor?: number; sort?: ColumnSort }) => {
    setLoading(true); setError(null);
    try {
      const nextSort = opts?.sort ?? sortRef.current;
      const nextCursor = opts?.cursor ?? (opts?.resetCursor ? 0 : cursorRef.current);
      const query = new URLSearchParams({
        limit: String(PAGE_LIMIT),
        cursor: String(nextCursor),
        sort: nextSort.column,
        order: nextSort.order,
      });
      const response = await fetch(apiUrl(`/admin/orders?${query.toString()}`), { headers: authHeaders(authToken) });
      const data = await response.json();
      if (!response.ok) throw new Error(data.message || 'Pesanan tidak dapat dimuat.');
      const meta = data.meta as { has_more?: boolean; cursor?: number; total?: number } | undefined;
      setOrders(data.data);
      setHasMore(Boolean(meta?.has_more));
      setCursor(meta?.cursor ?? nextCursor);
      if (meta?.total !== undefined) setTotal(Number(meta.total));
    } catch (reason) {
      setError(reason instanceof Error ? reason.message : 'Pesanan tidak dapat dimuat.');
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => { const stored = getStoredToken(); setToken(stored); setReady(true); if (stored) loadOrders(stored, { resetCursor: true }); }, [loadOrders]);
  async function showOrder(id: number) { if (!token) return; const response = await fetch(apiUrl(`/admin/orders/${id}`), { headers: authHeaders(token) }); const data = await response.json(); if (response.ok) setSelected(data.data); else setError(data.message || 'Detail pesanan tidak dapat dimuat.'); }
  async function approve(id: number) { if (!token) return; const response = await fetch(apiUrl(`/orders/${id}/approve`), { method: 'PUT', headers: authHeaders(token) }); const data = await response.json(); if (!response.ok) { setError(data.message || 'Pesanan tidak dapat disetujui.'); return; } setSelected(data.data); await loadOrders(token); }

  if (!ready) return <p className="text-sm text-gray-500">Memuat...</p>;
  if (!token) return <div className="mx-auto max-w-6xl"><PageHeader title="Kelola pesanan" description="Tinjau dan setujui pesanan dari outlet." /><div className="mb-5 rounded-lg border border-warning-200 bg-warning-50 p-3 text-sm text-warning-700">Masuk sebagai administrator untuk mengelola pesanan.</div><LoginForm expectedRole="admin" onLogin={(nextToken) => { setToken(nextToken); loadOrders(nextToken); }} /></div>;
  const columns = [
    { key: 'order_id', header: 'Pesanan', render: (order: AdminOrder) => <button onClick={() => showOrder(order.id)} className="font-semibold text-primary-700 hover:underline">{order.order_id}</button> },
    { key: 'status', header: 'Status', render: (order: AdminOrder) => <StatusBadge status={order.status} /> },
    { key: 'total_amount', header: 'Total', render: (order: AdminOrder) => <span className="font-medium">Rp {Number(order.total_amount).toLocaleString('id-ID')}</span> },
    { key: 'created_at', header: 'Dibuat', render: (order: AdminOrder) => <span className="text-xs text-gray-600">{formatDateTime(order.created_at ?? null)}</span> },
    { key: 'updated_at', header: 'Diperbarui', render: (order: AdminOrder) => <span className="text-xs text-gray-600">{formatDateTime(order.updated_at ?? null)}</span> },
    { key: 'action', header: 'Aksi', render: (order: AdminOrder) => <Button size="sm" onClick={() => approve(order.id)} disabled={order.status.toLowerCase() !== 'new'}>Setujui</Button> },
  ];
  return <div className="mx-auto max-w-6xl"><PageHeader title="Kelola pesanan" description="Tinjau, setujui, dan pantau pesanan outlet." />{error && <p role="alert" className="mb-5 rounded-lg border border-danger-200 bg-danger-50 p-3 text-sm text-danger-700">{error}</p>}<div className="grid gap-5 lg:grid-cols-[1.35fr_1fr]"><Card className="overflow-hidden"><div className="flex flex-wrap items-center justify-between gap-3 border-b border-gray-100 px-5 py-3"><TableSummary total={total ?? orders.length} noun="pesanan" /></div>{loading ? <p className="p-8 text-sm text-gray-500">Memuat pesanan...</p> : <Table columns={columns} rows={orders} rowKey={(order) => order.id} empty={<EmptyState icon={<span>🛒</span>} title="Belum ada pesanan" description="Pesanan baru dari outlet akan muncul di sini." />} />}</Card><Card className="p-5">{selected ? <><div className="flex items-center justify-between"><h2 className="text-base font-semibold text-gray-900">{selected.order_id}</h2><StatusBadge status={selected.status} /></div><p className="mt-3 text-lg font-bold text-gray-900">Rp {Number(selected.total_amount).toLocaleString('id-ID')}</p><h3 className="mt-6 border-b border-gray-100 pb-2 text-sm font-semibold text-gray-900">Riwayat status</h3><ol className="mt-3 space-y-3 border-l-2 border-primary-100 pl-4 text-sm text-gray-600">{(selected.status_history || []).map((history) => <li key={`${history.status}-${history.created_at}`}><span className="font-medium text-gray-800">{history.status}</span><br /><span className="text-xs">{new Date(history.created_at).toLocaleString('id-ID')}</span></li>)}</ol></> : <EmptyState icon={<span>👆</span>} title="Pilih pesanan" description="Pilih nomor pesanan di tabel untuk melihat detail." />}</Card></div></div>;
}

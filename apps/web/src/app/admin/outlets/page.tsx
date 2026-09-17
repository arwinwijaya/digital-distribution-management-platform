'use client';

import { useCallback, useEffect, useState } from 'react';
import LoginForm from '@/components/LoginForm';
import { getStoredToken } from '@/lib/api';
import { fetchAdminOutlets, fetchOutletOrders, fetchOutletSummary, updateOutlet, type AdminOutlet, type OutletOrder, type OutletSummary } from './api';
import { useDummyRefresh } from '@/dummy/guards';
import { Button, Card, EmptyState, Input, PageHeader, Select, Table } from '@/components/ui';

const CATEGORY_OPTIONS = ['', 'warung', 'minimarket', 'supermarket', 'grosir', 'restoran', 'kafe', 'toko_kelontong', 'lainnya'] as const;

export default function AdminOutletsPage() {
  const [token, setToken] = useState<string | null>(null);
  const [outlets, setOutlets] = useState<AdminOutlet[]>([]);
  const [ready, setReady] = useState(false);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [actionError, setActionError] = useState<string | null>(null);
  const [actionSuccess, setActionSuccess] = useState<string | null>(null);
  const [hasMore, setHasMore] = useState(false);
  const [search, setSearch] = useState('');
  const [category, setCategory] = useState('');
  const [territoryId, setTerritoryId] = useState('');
  const [isActive, setIsActive] = useState('');
  const [editingOutlet, setEditingOutlet] = useState<AdminOutlet | null>(null);
  const [editName, setEditName] = useState('');
  const [editCategory, setEditCategory] = useState('');
  const [editAddress, setEditAddress] = useState('');
  const [editCity, setEditCity] = useState('');
  const [editDistrict, setEditDistrict] = useState('');
  const [savingEdit, setSavingEdit] = useState(false);
  const [selectedOutletId, setSelectedOutletId] = useState<number | null>(null);
  const [summary, setSummary] = useState<OutletSummary | null>(null);
  const [orders, setOrders] = useState<OutletOrder[]>([]);
  const [detailLoading, setDetailLoading] = useState(false);

  const loadOutlets = useCallback(async (authToken: string) => {
    setLoading(true); setError(null);
    try {
      const result = await fetchAdminOutlets(authToken, { search: search || undefined, category: category || undefined, territory_id: territoryId || undefined, is_active: isActive || undefined, limit: 15, cursor: 0 });
      setOutlets(result.outlets); setHasMore(result.hasMore);
    } catch (reason) { setError(reason instanceof Error ? reason.message : 'Daftar outlet tidak dapat dimuat.'); } finally { setLoading(false); }
  }, [search, category, territoryId, isActive]);

  const loadOutletDetail = useCallback(async (authToken: string, outletId: number) => {
    setDetailLoading(true); setActionError(null);
    try {
      const [s, o] = await Promise.all([fetchOutletSummary(authToken, outletId), fetchOutletOrders(authToken, outletId, { limit: 10 })]);
      setSummary(s); setOrders(o.orders);
    } catch (reason) { setActionError(reason instanceof Error ? reason.message : 'Detail outlet tidak dapat dimuat.'); } finally { setDetailLoading(false); }
  }, []);

  useEffect(() => {
    const stored = getStoredToken(); setToken(stored); setReady(true);
    if (stored) loadOutlets(stored);
  }, [loadOutlets]);

  useDummyRefresh(() => { if (token) void loadOutlets(token); });

  function startEdit(outlet: AdminOutlet) {
    setEditingOutlet(outlet); setEditName(outlet.name || ''); setEditCategory(outlet.category || 'lainnya'); setEditAddress(outlet.address || ''); setEditCity(outlet.city || ''); setEditDistrict(outlet.district || ''); setActionError(null); setActionSuccess(null);
  }

  async function handleSaveEdit() {
    if (!token || !editingOutlet) return;
    setSavingEdit(true); setActionError(null); setActionSuccess(null);
    try {
      const updated = await updateOutlet(token, editingOutlet.id, { name: editName, category: editCategory || undefined, address: editAddress || undefined, city: editCity || undefined, district: editDistrict || undefined });
      setOutlets((prev) => prev.map((o) => (o.id === editingOutlet.id ? { ...o, ...updated } : o)));
      setEditingOutlet(null); setActionSuccess('Outlet diperbarui.');
    } catch (reason) { setActionError(reason instanceof Error ? reason.message : 'Outlet tidak dapat diperbarui.'); } finally { setSavingEdit(false); }
  }

  function handleSelectOutlet(outletId: number) {
    if (!token) return; setSelectedOutletId(outletId); loadOutletDetail(token, outletId);
  }

  if (!ready) return <p className="text-sm text-gray-500">Memuat...</p>;
  if (!token) return <div className="mx-auto max-w-6xl"><PageHeader title="Kelola outlet" description="Kelola outlet, lihat skor, dan riwayat pembelian." /><div className="mb-5 rounded-lg border border-warning-200 bg-warning-50 p-3 text-sm text-warning-700">Masuk sebagai administrator untuk mengelola outlet.</div><LoginForm expectedRole="admin" onLogin={(nextToken) => { setToken(nextToken); loadOutlets(nextToken); }} /></div>;

  const columns = [
    { key: 'name', header: 'Nama Outlet', render: (o: AdminOutlet) => <button onClick={() => handleSelectOutlet(o.id)} className="font-medium text-primary-700 hover:underline text-left">{o.name}</button> },
    {
      key: 'category',
      header: 'Kategori',
      render: (o: AdminOutlet) => o.category ? <span className="inline-flex items-center rounded-full border border-gray-200 bg-gray-50 px-2.5 py-0.5 text-xs font-medium text-gray-700">{o.category}</span> : <span className="text-xs text-gray-400">—</span>,
    },
    {
      key: 'score',
      header: 'Skor',
      render: (o: AdminOutlet) => (typeof o.score === 'number' ? <span className="font-medium">{o.score}</span> : <span className="text-xs text-gray-400">—</span>),
    },
    {
      key: 'territory',
      header: 'Wilayah',
      render: (o: AdminOutlet) => <span className="text-sm text-gray-600">{o.territory?.name ?? (o.territory_id ?? '—')}</span>,
    },
    {
      key: 'status',
      header: 'Status',
      render: (o: AdminOutlet) => (
        <span className={`inline-flex items-center rounded-full border px-2.5 py-0.5 text-xs font-medium ${o.is_active ? 'bg-success-50 text-success-700 border-success-200' : 'bg-gray-50 text-gray-600 border-gray-200'}`}>
          {o.is_active ? 'Aktif' : 'Nonaktif'}
        </span>
      ),
    },
    {
      key: 'action',
      header: 'Aksi',
      render: (o: AdminOutlet) => <Button size="sm" variant="secondary" onClick={() => startEdit(o)}>Ubah</Button>,
    },
  ];

  return (
    <div className="mx-auto max-w-6xl">
      <PageHeader title="Kelola outlet" description="Lihat, filter, ubah outlet, serta ringkasan pembelian." />
      {error && <p role="alert" className="mb-5 rounded-lg border border-danger-200 bg-danger-50 p-3 text-sm text-danger-700">{error}</p>}
      {actionError && <p role="alert" className="mb-3 rounded-lg border border-danger-200 bg-danger-50 p-3 text-sm text-danger-700">{actionError}</p>}
      {actionSuccess && <p className="mb-3 rounded-lg border border-success-200 bg-success-50 p-3 text-sm text-success-700">{actionSuccess}</p>}
      <Card className="mb-5 p-5">
        <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
          <Input label="Cari nama" placeholder="Nama outlet" value={search} onChange={(e) => setSearch(e.target.value)} />
          <Select label="Kategori" value={category} onChange={(e) => setCategory(e.target.value)}>
            <option value="">Semua kategori</option>
            {CATEGORY_OPTIONS.filter(Boolean).map((c) => (<option key={c} value={c}>{c}</option>))}
          </Select>
          <Input label="Wilayah (ID)" placeholder="mis. 1" value={territoryId} onChange={(e) => setTerritoryId(e.target.value)} />
          <Select label="Status" value={isActive} onChange={(e) => setIsActive(e.target.value)}>
            <option value="">Semua status</option>
            <option value="true">Aktif</option>
            <option value="false">Nonaktif</option>
          </Select>
          <div className="flex items-end"><Button onClick={() => token && loadOutlets(token)} disabled={loading} className="w-full">Terapkan filter</Button></div>
        </div>
        {hasMore && <p className="mt-3 text-xs text-gray-500">Ada outlet lebih lanjut — sesuaikan filter jika diperlukan.</p>}
      </Card>
      <div className="grid gap-5 lg:grid-cols-[1.7fr_1fr]">
        <Card className="overflow-hidden">
          {loading ? <p className="p-8 text-sm text-gray-500">Memuat outlet...</p> : <Table columns={columns} rows={outlets} rowKey={(o) => o.id} empty={<EmptyState icon={<span>🏪</span>} title="Belum ada outlet" description="Outlet akan muncul di sini." />} />}
        </Card>
        <div className="space-y-5">
          {editingOutlet && (
            <Card className="p-5">
              <h3 className="text-sm font-semibold text-gray-900">Ubah outlet — {editingOutlet.name}</h3>
              <div className="mt-4 space-y-3">
                <Input label="Nama" value={editName} onChange={(e) => setEditName(e.target.value)} />
                <Select label="Kategori" value={editCategory} onChange={(e) => setEditCategory(e.target.value)}>
                  {CATEGORY_OPTIONS.filter(Boolean).map((c) => (<option key={c} value={c}>{c}</option>))}
                </Select>
                <Input label="Alamat" value={editAddress} onChange={(e) => setEditAddress(e.target.value)} />
                <Input label="Kota" value={editCity} onChange={(e) => setEditCity(e.target.value)} />
                <Input label="Kecamatan" value={editDistrict} onChange={(e) => setEditDistrict(e.target.value)} />
                <div className="flex gap-2">
                  <Button onClick={handleSaveEdit} disabled={savingEdit}>{savingEdit ? 'Menyimpan...' : 'Simpan'}</Button>
                  <Button variant="ghost" onClick={() => setEditingOutlet(null)}>Batal</Button>
                </div>
              </div>
            </Card>
          )}
          <Card className="p-5">
            {selectedOutletId === null ? (
              <EmptyState icon={<span>👆</span>} title="Pilih outlet" description="Klik nama outlet untuk melihat ringkasan dan riwayat pesanan." />
            ) : detailLoading ? (
              <p className="text-sm text-gray-500">Memuat detail...</p>
            ) : (
              <>
                {summary && (
                  <>
                    <h3 className="text-sm font-semibold text-gray-900">Ringkasan — {summary.outlet_name}</h3>
                    <dl className="mt-3 grid grid-cols-2 gap-3 text-sm">
                      <div><dt className="text-xs text-gray-500">Total pesanan</dt><dd className="font-medium text-gray-900">{summary.total_orders}</dd></div>
                      <div><dt className="text-xs text-gray-500">Total belanja</dt><dd className="font-medium text-gray-900">Rp {Number(summary.total_amount ?? summary.total_spend ?? 0).toLocaleString('id-ID')}</dd></div>
                      <div className="col-span-2"><dt className="text-xs text-gray-500">Pesanan terakhir</dt><dd className="text-xs text-gray-600">{summary.last_order_date ? new Date(summary.last_order_date).toLocaleString('id-ID') : '—'}</dd></div>
                    </dl>
                  </>
                )}
                <h4 className="mt-5 border-t border-gray-100 pt-4 text-sm font-semibold text-gray-900">Riwayat pesanan</h4>
                {orders.length === 0 ? (
                  <p className="mt-2 text-sm text-gray-500">Belum ada pesanan untuk outlet ini.</p>
                ) : (
                  <ul className="mt-3 space-y-2 text-sm text-gray-600">
                    {orders.map((o) => (
                      <li key={o.id} className="flex items-center justify-between rounded-lg border border-gray-100 px-3 py-2">
                        <span className="font-medium text-gray-800">{o.order_id}</span>
                        <span className="text-xs text-gray-500">{o.status}</span>
                        <span className="font-medium">Rp {Number(o.total_amount).toLocaleString('id-ID')}</span>
                      </li>
                    ))}
                  </ul>
                )}
              </>
            )}
          </Card>
        </div>
      </div>
    </div>
  );
}

'use client';

import Link from 'next/link';
import { useCallback, useEffect, useRef, useState } from 'react';
import LoginForm from '@/components/LoginForm';
import { getStoredToken } from '@/lib/api';
import { fetchAdminOutlets, fetchOutletOrders, fetchOutletSummary, createOutlet, updateOutlet, type AdminOutlet, type OutletOrder, type OutletSummary } from './api';
import { useDummyRefresh } from '@/dummy/guards';
import { Button, Card, EmptyState, Input, Modal, PageHeader, Select, Table, TableSummary, TablePagination, TableDensityToggle } from '@/components/ui';
import { useTableDensity } from '@/hooks/useTableDensity';
import { toggleSort, formatDateTime, type ColumnSort } from '@/lib/admin-table';

const CATEGORY_OPTIONS = ['warung', 'minimarket', 'supermarket', 'grosir', 'restoran', 'kafe', 'toko_kelontong', 'lainnya'] as const;

interface OutletForm {
  name: string;
  phone: string;
  category: string;
  address: string;
  city: string;
  district: string;
  territory_id: string;
  is_active: boolean;
}

const EMPTY_FORM: OutletForm = {
  name: '',
  phone: '',
  category: 'lainnya',
  address: '',
  city: '',
  district: '',
  territory_id: '',
  is_active: true,
};

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
  const [formOpen, setFormOpen] = useState(false);
  const [editing, setEditing] = useState<AdminOutlet | null>(null);
  const [form, setForm] = useState<OutletForm>(EMPTY_FORM);
  const [saving, setSaving] = useState(false);
  const [confirmDeactivate, setConfirmDeactivate] = useState<AdminOutlet | null>(null);
  const [selectedOutletId, setSelectedOutletId] = useState<number | null>(null);
  const [summary, setSummary] = useState<OutletSummary | null>(null);
  const [orders, setOrders] = useState<OutletOrder[]>([]);
  const [detailLoading, setDetailLoading] = useState(false);
  // Table state
  const [sort, setSort] = useState<ColumnSort>({ column: 'created_at', order: 'desc' });
  const [cursor, setCursor] = useState(0);
  const [total, setTotal] = useState<number>();
  const [tableSummary, setTableSummary] = useState<{ active: number; inactive: number }>();
  const { density, setDensity } = useTableDensity();

  // Latest sort/cursor readable inside `loadOutlets` WITHOUT adding them to its
  // dependency list (which would otherwise re-run the mount effect and reset the
  // page on every sort/page change).
  const sortRef = useRef(sort);
  sortRef.current = sort;
  const cursorRef = useRef(cursor);
  cursorRef.current = cursor;

  const loadOutlets = useCallback(async (authToken: string, opts?: { resetCursor?: boolean; cursor?: number; sort?: ColumnSort }) => {
    setLoading(true); setError(null);
    try {
      const nextSort = opts?.sort ?? sortRef.current;
      const nextCursor = opts?.cursor ?? (opts?.resetCursor ? 0 : cursorRef.current);
      const result = await fetchAdminOutlets(authToken, {
        search: search || undefined,
        category: category || undefined,
        territory_id: territoryId || undefined,
        is_active: isActive || undefined,
        limit: 15,
        cursor: nextCursor,
        sort: nextSort.column,
        order: nextSort.order,
      });
      setOutlets(result.outlets);
      setHasMore(result.hasMore);
      setCursor(nextCursor);
      if (result.total !== undefined) setTotal(result.total);
      if (result.summary) setTableSummary(result.summary);
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
    if (stored) loadOutlets(stored, { resetCursor: true });
  }, [loadOutlets]);

  useDummyRefresh(() => { if (token) void loadOutlets(token, { resetCursor: true }); });

  function openCreateForm() {
    setEditing(null); setForm(EMPTY_FORM); setFormOpen(true); setActionError(null); setActionSuccess(null);
  }

  function openEditForm(outlet: AdminOutlet) {
    setEditing(outlet);
    setForm({
      name: outlet.name ?? '',
      phone: outlet.phone ?? '',
      category: outlet.category ?? 'lainnya',
      address: outlet.address ?? '',
      city: outlet.city ?? '',
      district: outlet.district ?? '',
      territory_id: outlet.territory_id != null ? String(outlet.territory_id) : '',
      is_active: outlet.is_active ?? true,
    });
    setFormOpen(true); setActionError(null); setActionSuccess(null);
  }

  function buildPayload() {
    return {
      name: form.name.trim(),
      phone: form.phone.trim(),
      category: form.category || 'lainnya',
      address: form.address.trim(),
      city: form.city.trim(),
      district: form.district.trim(),
      territory_id: form.territory_id !== '' ? Number(form.territory_id) : null,
      is_active: form.is_active,
    };
  }

  async function handleSubmit() {
    if (!token) return;
    if (!form.name.trim()) { setActionError('Nama wajib diisi.'); return; }
    if (!form.phone.trim()) { setActionError('Nomor telepon wajib diisi.'); return; }
    if (!form.address.trim() || !form.city.trim() || !form.district.trim()) { setActionError('Alamat, kota, dan kecamatan wajib diisi.'); return; }
    setSaving(true); setActionError(null); setActionSuccess(null);
    try {
      const payload = buildPayload();
      if (editing) {
        const updated = await updateOutlet(token, editing.id, payload);
        setOutlets((prev) => prev.map((o) => (o.id === editing.id ? { ...o, ...updated } : o)));
        setActionSuccess('Outlet diperbarui.');
      } else {
        const created = await createOutlet(token, payload);
        setOutlets((prev) => [created, ...prev]);
        setActionSuccess('Outlet dibuat.');
      }
      setFormOpen(false); setEditing(null);
    } catch (reason) { setActionError(reason instanceof Error ? reason.message : 'Outlet tidak dapat disimpan.'); } finally { setSaving(false); }
  }

  async function handleDeactivate() {
    if (!token || !confirmDeactivate) return;
    setActionError(null); setActionSuccess(null);
    try {
      const updated = await updateOutlet(token, confirmDeactivate.id, { is_active: false });
      setOutlets((prev) => prev.map((o) => (o.id === confirmDeactivate.id ? { ...o, ...updated, is_active: false } : o)));
      setConfirmDeactivate(null); setActionSuccess('Outlet dinonaktifkan.');
    } catch (reason) { setActionError(reason instanceof Error ? reason.message : 'Outlet tidak dapat dinonaktifkan.'); }
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
      key: 'created_at',
      header: 'Dibuat',
      render: (o: AdminOutlet) => <span className="text-xs text-gray-600">{formatDateTime(o.created_at ?? null)}</span>,
    },
    {
      key: 'updated_at',
      header: 'Diperbarui',
      render: (o: AdminOutlet) => <span className="text-xs text-gray-600">{formatDateTime(o.updated_at ?? null)}</span>,
    },
    {
      key: 'action',
      header: 'Aksi',
      render: (o: AdminOutlet) => (
        <span className="flex flex-wrap gap-1.5">
          <Button size="sm" variant="secondary" onClick={() => openEditForm(o)}>Ubah</Button>
          {o.is_active && <Button size="sm" variant="danger" onClick={() => setConfirmDeactivate(o)}>Nonaktifkan</Button>}
        </span>
      ),
    },
  ];

  return (
    <div className="mx-auto max-w-6xl">
      <PageHeader
        title="Kelola outlet"
        description="Tambah, ubah, nonaktifkan outlet, serta lihat ringkasan pembelian."
        action={
          <div className="flex items-center gap-2">
            <Link href="/outlets" className="inline-flex items-center gap-1 rounded-lg border border-gray-200 bg-white px-3 py-2 text-sm font-medium text-gray-700 shadow-sm hover:bg-gray-50">Daftar outlet ↗</Link>
            <Button onClick={openCreateForm}>+ Tambah outlet</Button>
          </div>
        }
      />
      {error && <p role="alert" className="mb-5 rounded-lg border border-danger-200 bg-danger-50 p-3 text-sm text-danger-700">{error}</p>}
      {actionError && <p role="alert" className="mb-3 rounded-lg border border-danger-200 bg-danger-50 p-3 text-sm text-danger-700">{actionError}</p>}
      {actionSuccess && <p className="mb-3 rounded-lg border border-success-200 bg-success-50 p-3 text-sm text-success-700">{actionSuccess}</p>}
      <Card className="mb-5 p-5">
        <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
          <Input label="Cari nama" placeholder="Nama outlet" value={search} onChange={(e) => setSearch(e.target.value)} />
          <Select label="Kategori" value={category} onChange={(e) => setCategory(e.target.value)}>
            <option value="">Semua kategori</option>
            {CATEGORY_OPTIONS.map((c) => (<option key={c} value={c}>{c}</option>))}
          </Select>
          <Input label="Wilayah (ID)" placeholder="mis. 1" value={territoryId} onChange={(e) => setTerritoryId(e.target.value)} />
          <Select label="Status" value={isActive} onChange={(e) => setIsActive(e.target.value)}>
            <option value="">Semua status</option>
            <option value="true">Aktif</option>
            <option value="false">Nonaktif</option>
          </Select>
          <div className="flex items-end"><Button onClick={() => token && loadOutlets(token, { resetCursor: true })} disabled={loading} className="w-full">Terapkan filter</Button></div>
        </div>
        {hasMore && <p className="mt-3 text-xs text-gray-500">Ada outlet lebih lanjut — sesuaikan filter jika diperlukan.</p>}
      </Card>
      <div className="grid gap-5 lg:grid-cols-[1.7fr_1fr]">
        <Card className="overflow-hidden">
          <div className="flex flex-wrap items-center justify-between gap-3 border-b border-gray-100 px-5 py-3">
            <TableSummary
              total={total ?? outlets.length}
              breakdown={tableSummary ? [{ label: 'aktif', value: tableSummary.active }, { label: 'nonaktif', value: tableSummary.inactive }] : undefined}
              noun="outlet"
            />
            <TableDensityToggle value={density} onChange={setDensity} />
          </div>
          {loading ? (
            <p className="p-8 text-sm text-gray-500">Memuat outlet...</p>
          ) : (
            <Table
              columns={columns}
              rows={outlets}
              rowKey={(o) => o.id}
              density={density}
              sortableColumns={['name', 'category', 'score', 'created_at', 'updated_at']}
              sort={sort}
              onSort={(column) => {
                const next = toggleSort(sortRef.current, column);
                setSort(next);
                if (token) void loadOutlets(token, { resetCursor: true, sort: next });
              }}
              empty={<EmptyState icon={<span>🏪</span>} title="Belum ada outlet" description="Outlet akan muncul di sini." />}
            />
          )}
          <TablePagination
            cursor={cursor}
            limit={15}
            total={total}
            hasMore={hasMore}
            onPageChange={(nextCursor) => {
              setCursor(nextCursor);
              if (token) void loadOutlets(token, { cursor: nextCursor });
            }}
          />
        </Card>
        <div className="space-y-5">
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

      <Modal
        open={formOpen}
        onClose={() => setFormOpen(false)}
        title={editing ? 'Ubah outlet' : 'Tambah outlet'}
        footer={<><Button variant="ghost" onClick={() => setFormOpen(false)}>Batal</Button><Button onClick={handleSubmit} disabled={saving}>{saving ? 'Menyimpan...' : 'Simpan'}</Button></>}
      >
        <div className="space-y-3">
          <Input label="Nama" value={form.name} onChange={(e) => setForm((f) => ({ ...f, name: e.target.value }))} />
          <Input label="Telepon" value={form.phone} onChange={(e) => setForm((f) => ({ ...f, phone: e.target.value }))} />
          <Select label="Kategori" value={form.category} onChange={(e) => setForm((f) => ({ ...f, category: e.target.value }))}>
            {CATEGORY_OPTIONS.map((c) => (<option key={c} value={c}>{c}</option>))}
          </Select>
          <Input label="Alamat" value={form.address} onChange={(e) => setForm((f) => ({ ...f, address: e.target.value }))} />
          <div className="grid grid-cols-2 gap-3">
            <Input label="Kota" value={form.city} onChange={(e) => setForm((f) => ({ ...f, city: e.target.value }))} />
            <Input label="Kecamatan" value={form.district} onChange={(e) => setForm((f) => ({ ...f, district: e.target.value }))} />
          </div>
          <Input label="Wilayah (ID)" type="number" min="0" placeholder="mis. 1" value={form.territory_id} onChange={(e) => setForm((f) => ({ ...f, territory_id: e.target.value }))} />
          <Select label="Status" value={form.is_active ? 'true' : 'false'} onChange={(e) => setForm((f) => ({ ...f, is_active: e.target.value === 'true' }))}>
            <option value="true">Aktif</option>
            <option value="false">Nonaktif</option>
          </Select>
        </div>
      </Modal>

      <Modal
        open={confirmDeactivate !== null}
        onClose={() => setConfirmDeactivate(null)}
        title="Nonaktifkan outlet"
        footer={<><Button variant="ghost" onClick={() => setConfirmDeactivate(null)}>Batal</Button><Button variant="danger" onClick={handleDeactivate}>Nonaktifkan</Button></>}
      >
        <p>Yakin ingin menonaktifkan outlet <span className="font-medium text-gray-900">{confirmDeactivate?.name}</span>? Outlet tidak akan dihapus dan riwayat pesanan tetap tersimpan.</p>
      </Modal>
    </div>
  );
}

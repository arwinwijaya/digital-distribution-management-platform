'use client';

import { useCallback, useEffect, useRef, useState } from 'react';
import LoginForm from '@/components/LoginForm';
import { getStoredToken } from '@/lib/api';
import { fetchPromotions, createPromotion, updatePromotion, deletePromotion, broadcastPromotion, type AdminPromotion } from './api';
import { useDummyRefresh } from '@/dummy/guards';
import { Button, Card, EmptyState, Input, Modal, PageHeader, Select, StatusBadge, Table, TableSummary, TablePagination, TableDensityToggle } from '@/components/ui';
import { useTableDensity } from '@/hooks/useTableDensity';
import { toggleSort, formatDateTime, type ColumnSort } from '@/lib/admin-table';

interface PromoForm {
  name: string;
  description: string;
  discount_type: 'percentage' | 'fixed';
  discount_value: string;
  max_discount: string;
  product_id: string;
  min_order: string;
  start_date: string;
  end_date: string;
  is_active: boolean;
}

const EMPTY_FORM: PromoForm = {
  name: '',
  description: '',
  discount_type: 'percentage',
  discount_value: '',
  max_discount: '',
  product_id: '',
  min_order: '0',
  start_date: '',
  end_date: '',
  is_active: true,
};

export default function AdminPromotionsPage() {
  const [token, setToken] = useState<string | null>(null);
  const [promotions, setPromotions] = useState<AdminPromotion[]>([]);
  const [ready, setReady] = useState(false);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [actionError, setActionError] = useState<string | null>(null);
  const [actionSuccess, setActionSuccess] = useState<string | null>(null);
  const [hasMore, setHasMore] = useState(false);
  const [formOpen, setFormOpen] = useState(false);
  const [editing, setEditing] = useState<AdminPromotion | null>(null);
  const [form, setForm] = useState<PromoForm>(EMPTY_FORM);
  const [saving, setSaving] = useState(false);
  const [confirmDelete, setConfirmDelete] = useState<AdminPromotion | null>(null);
  // Table state
  const [sort, setSort] = useState<ColumnSort>({ column: 'created_at', order: 'desc' });
  const [cursor, setCursor] = useState(0);
  const [total, setTotal] = useState<number>();
  const [tableSummary, setTableSummary] = useState<{ total: number; active: number; scheduled: number; ended: number }>();
  const { density, setDensity } = useTableDensity();

  // Latest sort/cursor readable inside `loadPromotions` WITHOUT adding them to
  // its dependency list (which would otherwise re-run the mount effect and
  // reset the page on every sort/page change).
  const sortRef = useRef(sort);
  sortRef.current = sort;
  const cursorRef = useRef(cursor);
  cursorRef.current = cursor;

  const loadPromotions = useCallback(async (authToken: string, opts?: { resetCursor?: boolean; cursor?: number; sort?: ColumnSort }) => {
    setLoading(true); setError(null);
    try {
      const nextSort = opts?.sort ?? sortRef.current;
      const nextCursor = opts?.cursor ?? (opts?.resetCursor ? 0 : cursorRef.current);
      const result = await fetchPromotions(authToken, { limit: 15, cursor: nextCursor, sort: nextSort.column, order: nextSort.order });
      setPromotions(result.promotions);
      setHasMore(result.hasMore);
      setCursor(nextCursor);
      if (result.total !== undefined) setTotal(result.total);
      if (result.summary) setTableSummary(result.summary);
    } catch (reason) { setError(reason instanceof Error ? reason.message : 'Daftar promosi tidak dapat dimuat.'); } finally { setLoading(false); }
  }, []);

  useEffect(() => {
    const stored = getStoredToken(); setToken(stored); setReady(true);
    if (stored) loadPromotions(stored, { resetCursor: true });
  }, [loadPromotions]);

  useDummyRefresh(() => { if (token) void loadPromotions(token, { resetCursor: true }); });

  function openCreateForm() {
    setEditing(null); setForm(EMPTY_FORM); setFormOpen(true); setActionError(null); setActionSuccess(null);
  }

  function openEditForm(promo: AdminPromotion) {
    setEditing(promo);
    setForm({
      name: promo.name ?? '',
      description: promo.description ?? '',
      discount_type: promo.discount_type === 'fixed' ? 'fixed' : 'percentage',
      discount_value: String(promo.discount_value ?? ''),
      max_discount: promo.max_discount != null ? String(promo.max_discount) : '',
      product_id: promo.product_id != null ? String(promo.product_id) : '',
      min_order: promo.min_order != null ? String(promo.min_order) : '0',
      start_date: typeof promo.start_date === 'string' ? promo.start_date.slice(0, 10) : '',
      end_date: typeof promo.end_date === 'string' ? promo.end_date.slice(0, 10) : '',
      is_active: Boolean(promo.is_active),
    });
    setFormOpen(true); setActionError(null); setActionSuccess(null);
  }

  function buildPayload() {
    return {
      name: form.name,
      description: form.description || null,
      discount_type: form.discount_type,
      discount_value: Number(form.discount_value),
      max_discount: form.max_discount !== '' ? Number(form.max_discount) : undefined,
      product_id: form.product_id !== '' ? Number(form.product_id) : null,
      min_order: Number(form.min_order),
      start_date: form.start_date,
      end_date: form.end_date,
      is_active: form.is_active,
    };
  }

  async function handleSubmit() {
    if (!token) return;
    if (!form.name.trim()) { setActionError('Nama wajib diisi.'); return; }
    if (!form.discount_value || !Number.isFinite(Number(form.discount_value)) || Number(form.discount_value) < 0) { setActionError('Nilai diskon wajib berupa angka ≥ 0.'); return; }
    setSaving(true); setActionError(null); setActionSuccess(null);
    try {
      const payload = buildPayload();
      if (editing) {
        const updated = await updatePromotion(token, editing.id, payload);
        setPromotions((prev) => prev.map((p) => (p.id === editing.id ? { ...p, ...updated } : p)));
        setActionSuccess('Promosi diperbarui.');
      } else {
        const created = await createPromotion(token, payload);
        setPromotions((prev) => [created, ...prev]);
        setActionSuccess('Promosi dibuat.');
      }
      setFormOpen(false); setEditing(null);
    } catch (reason) { setActionError(reason instanceof Error ? reason.message : 'Tidak dapat menyimpan promosi.'); } finally { setSaving(false); }
  }

  async function handleDelete() {
    if (!token || !confirmDelete) return;
    setActionError(null); setActionSuccess(null);
    try {
      await deletePromotion(token, confirmDelete.id);
      setPromotions((prev) => prev.filter((p) => p.id !== confirmDelete.id));
      setConfirmDelete(null); setActionSuccess('Promosi dihapus.');
    } catch (reason) { setActionError(reason instanceof Error ? reason.message : 'Promosi tidak dapat dihapus.'); }
  }

  async function handleBroadcast(promo: AdminPromotion) {
    if (!token) return;
    setActionError(null); setActionSuccess(null);
    try {
      const result = await broadcastPromotion(token, promo.id);
      if (result.status === 'unavailable') {
        setActionError('Broadcast endpoint belum tersedia');
      } else {
        setActionSuccess(result.message ?? 'Berhasil menyiarkan promosi.');
      }
    } catch (reason) { setActionError(reason instanceof Error ? reason.message : 'Broadcast gagal.'); }
  }

  if (!ready) return <p className="text-sm text-gray-500">Memuat...</p>;
  if (!token) return <div className="mx-auto max-w-6xl"><PageHeader title="Kelola promosi" description="Buat, ubah, hapus, dan siarkan promosi." /><div className="mb-5 rounded-lg border border-warning-200 bg-warning-50 p-3 text-sm text-warning-700">Masuk sebagai administrator untuk mengelola promosi.</div><LoginForm expectedRole="admin" onLogin={(nextToken) => { setToken(nextToken); loadPromotions(nextToken, { resetCursor: true }); }} /></div>;

  const columns = [
    {
      key: 'name',
      header: 'Nama',
      render: (p: AdminPromotion) => (
        <span>
          <span className="font-medium text-gray-900">{p.name}</span>
          {p.product?.name && <div className="text-xs text-gray-500">{p.product.name}</div>}
        </span>
      ),
    },
    {
      key: 'discount',
      header: 'Diskon',
      render: (p: AdminPromotion) => (
        <span className="font-medium">{p.discount_type === 'fixed' ? `Rp ${Number(p.discount_value).toLocaleString('id-ID')}` : `${p.discount_value}%`}</span>
      ),
    },
    {
      key: 'start_date',
      header: 'Mulai',
      render: (p: AdminPromotion) => (
        <span className="text-xs text-gray-600">{typeof p.start_date === 'string' ? p.start_date.slice(0, 10) : p.start_date}</span>
      ),
    },
    {
      key: 'end_date',
      header: 'Selesai',
      render: (p: AdminPromotion) => (
        <span className="text-xs text-gray-600">{typeof p.end_date === 'string' ? p.end_date.slice(0, 10) : p.end_date}</span>
      ),
    },
    {
      key: 'status',
      header: 'Status',
      render: (p: AdminPromotion) => <StatusBadge status={p.broadcast_at ? 'completed' : p.is_active ? 'active' : 'inactive'} />,
    },
    {
      key: 'created_at',
      header: 'Dibuat',
      render: (p: AdminPromotion) => <span className="text-xs text-gray-600">{formatDateTime(p.created_at ?? null)}</span>,
    },
    {
      key: 'updated_at',
      header: 'Diperbarui',
      render: (p: AdminPromotion) => <span className="text-xs text-gray-600">{formatDateTime(p.updated_at ?? null)}</span>,
    },
    {
      key: 'action',
      header: 'Aksi',
      render: (p: AdminPromotion) => (
        <span className="flex flex-wrap gap-1.5">
          <Button size="sm" variant="secondary" onClick={() => openEditForm(p)} disabled={!!p.broadcast_at}>Ubah</Button>
          <Button size="sm" variant="danger" onClick={() => setConfirmDelete(p)} disabled={!!p.broadcast_at}>Hapus</Button>
          <Button size="sm" onClick={() => handleBroadcast(p)}>Siarkan</Button>
        </span>
      ),
    },
  ];

  return (
    <div className="mx-auto max-w-6xl">
      <PageHeader title="Kelola promosi" description="Buat, ubah, hapus, dan siarkan promosi ke outlet." action={<Button onClick={openCreateForm}>+ Buat promosi</Button>} />
      {error && <p role="alert" className="mb-5 rounded-lg border border-danger-200 bg-danger-50 p-3 text-sm text-danger-700">{error}</p>}
      {actionError && <p role="alert" className="mb-3 rounded-lg border border-danger-200 bg-danger-50 p-3 text-sm text-danger-700">{actionError}</p>}
      {actionSuccess && <p className="mb-3 rounded-lg border border-success-200 bg-success-50 p-3 text-sm text-success-700">{actionSuccess}</p>}
      <Card className="overflow-hidden">
        <div className="flex flex-wrap items-center justify-between gap-3 border-b border-gray-100 px-5 py-3">
          <TableSummary
            total={total ?? promotions.length}
            breakdown={tableSummary ? [
              { label: 'aktif', value: tableSummary.active },
              { label: 'terjadwal', value: tableSummary.scheduled },
              { label: 'berakhir', value: tableSummary.ended },
            ] : undefined}
            noun="promosi"
          />
          <TableDensityToggle value={density} onChange={setDensity} />
        </div>
        {loading ? <p className="p-8 text-sm text-gray-500">Memuat promosi...</p> : (
          <Table
            columns={columns}
            rows={promotions}
            rowKey={(p) => p.id}
            density={density}
            sortableColumns={['start_date', 'end_date', 'created_at', 'updated_at']}
            sort={sort}
            onSort={(column) => {
              const next = toggleSort(sortRef.current, column);
              setSort(next);
              if (token) void loadPromotions(token, { resetCursor: true, sort: next });
            }}
            empty={<EmptyState icon={<span>🏷️</span>} title="Belum ada promosi" description="Buat promosi pertama Anda dengan tombol di atas." />}
          />
        )}
        <TablePagination
          cursor={cursor}
          limit={15}
          total={total}
          hasMore={hasMore}
          onPageChange={(nextCursor) => {
            setCursor(nextCursor);
            if (token) void loadPromotions(token, { cursor: nextCursor });
          }}
        />
      </Card>
      <Modal open={formOpen} onClose={() => setFormOpen(false)} title={editing ? 'Ubah promosi' : 'Buat promosi'} footer={<><Button variant="ghost" onClick={() => setFormOpen(false)}>Batal</Button><Button onClick={handleSubmit} disabled={saving}>{saving ? 'Menyimpan...' : 'Simpan'}</Button></>}>
        <div className="space-y-3">
          <Input label="Nama" value={form.name} onChange={(e) => setForm((f) => ({ ...f, name: e.target.value }))} />
          <Input label="Deskripsi" value={form.description} onChange={(e) => setForm((f) => ({ ...f, description: e.target.value }))} />
          <div className="grid grid-cols-2 gap-3">
            <Select label="Jenis diskon" value={form.discount_type} onChange={(e) => setForm((f) => ({ ...f, discount_type: e.target.value as 'percentage' | 'fixed' }))}>
              <option value="percentage">Persentase (%)</option>
              <option value="fixed">Tetap (Rp)</option>
            </Select>
            <Input label="Nilai diskon" type="number" min="0" value={form.discount_value} onChange={(e) => setForm((f) => ({ ...f, discount_value: e.target.value }))} />
          </div>
          <div className="grid grid-cols-2 gap-3">
            <Input label="Maks. diskon" type="number" min="0" value={form.max_discount} onChange={(e) => setForm((f) => ({ ...f, max_discount: e.target.value }))} />
            <Input label="Minimal belanja" type="number" min="0" value={form.min_order} onChange={(e) => setForm((f) => ({ ...f, min_order: e.target.value }))} />
          </div>
          <Input label="Produk (ID, opsional)" placeholder="Kosongkan untuk semua produk" value={form.product_id} onChange={(e) => setForm((f) => ({ ...f, product_id: e.target.value }))} />
          <div className="grid grid-cols-2 gap-3">
            <Input label="Mulai" type="date" value={form.start_date} onChange={(e) => setForm((f) => ({ ...f, start_date: e.target.value }))} />
            <Input label="Selesai" type="date" value={form.end_date} onChange={(e) => setForm((f) => ({ ...f, end_date: e.target.value }))} />
          </div>
          <Select label="Aktif" value={form.is_active ? 'true' : 'false'} onChange={(e) => setForm((f) => ({ ...f, is_active: e.target.value === 'true' }))}>
            <option value="true">Ya</option>
            <option value="false">Tidak</option>
          </Select>
        </div>
      </Modal>
      <Modal open={confirmDelete !== null} onClose={() => setConfirmDelete(null)} title="Hapus promosi" footer={<><Button variant="ghost" onClick={() => setConfirmDelete(null)}>Batal</Button><Button variant="danger" onClick={handleDelete}>Hapus</Button></>}>
        <p>Yakin ingin menghapus promosi <span className="font-medium text-gray-900">{confirmDelete?.name}</span>? Tindakan ini tidak dapat dibatalkan.</p>
      </Modal>
    </div>
  );
}

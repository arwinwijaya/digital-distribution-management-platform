'use client';

import { useCallback, useEffect, useRef, useState } from 'react';
import LoginForm from '@/components/LoginForm';
import { getStoredToken } from '@/lib/api';
import { fetchAdminProducts, updateProductPrice, fetchPriceHistory, type AdminProduct, type PriceHistoryEntry } from './api';
import { useDummyRefresh } from '@/dummy/guards';
import { Button, Card, EmptyState, Input, PageHeader, Table, TableSummary, TablePagination } from '@/components/ui';
import { toggleSort, formatDateTime, type ColumnSort } from '@/lib/admin-table';

export default function AdminProductsPage() {
  const [token, setToken] = useState<string | null>(null);
  const [products, setProducts] = useState<AdminProduct[]>([]);
  const [ready, setReady] = useState(false);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [actionError, setActionError] = useState<string | null>(null);
  const [actionSuccess, setActionSuccess] = useState<string | null>(null);
  const [search, setSearch] = useState('');
  const [editingProduct, setEditingProduct] = useState<AdminProduct | null>(null);
  const [newPrice, setNewPrice] = useState('');
  const [saving, setSaving] = useState(false);
  const [selectedProduct, setSelectedProduct] = useState<AdminProduct | null>(null);
  const [priceHistory, setPriceHistory] = useState<PriceHistoryEntry[]>([]);
  const [historyLoading, setHistoryLoading] = useState(false);
  // Table state
  const [hasMore, setHasMore] = useState(false);
  const [sort, setSort] = useState<ColumnSort>({ column: 'created_at', order: 'desc' });
  const [cursor, setCursor] = useState(0);
  const [total, setTotal] = useState<number>();
  const [tableSummary, setTableSummary] = useState<{ total: number; out_of_stock: number }>();

  // Latest sort/cursor readable inside `loadProducts` WITHOUT adding them to its
  // dependency list (which would otherwise re-run the mount effect and reset the
  // page on every sort/page change).
  const sortRef = useRef(sort);
  sortRef.current = sort;
  const cursorRef = useRef(cursor);
  cursorRef.current = cursor;

  const loadProducts = useCallback(async (authToken: string, opts?: { resetCursor?: boolean; cursor?: number; sort?: ColumnSort }) => {
    setLoading(true); setError(null);
    try {
      const nextSort = opts?.sort ?? sortRef.current;
      const nextCursor = opts?.cursor ?? (opts?.resetCursor ? 0 : cursorRef.current);
      const result = await fetchAdminProducts(authToken, {
        search: search || undefined,
        limit: 15,
        cursor: nextCursor,
        sort: nextSort.column,
        order: nextSort.order,
      });
      setProducts(result.products);
      setHasMore(result.hasMore);
      setCursor(nextCursor);
      if (result.total !== undefined) setTotal(result.total);
      if (result.summary) setTableSummary(result.summary);
    } catch (reason) { setError(reason instanceof Error ? reason.message : 'Daftar produk tidak dapat dimuat.'); } finally { setLoading(false); }
  }, [search]);

  const loadHistory = useCallback(async (authToken: string, productId: number) => {
    setHistoryLoading(true); setActionError(null);
    try {
      const result = await fetchPriceHistory(authToken, productId, { limit: 20 });
      setPriceHistory(result.data);
    } catch (reason) { setActionError(reason instanceof Error ? reason.message : 'Riwayat harga tidak dapat dimuat.'); } finally { setHistoryLoading(false); }
  }, []);

  useEffect(() => {
    const stored = getStoredToken(); setToken(stored); setReady(true);
    if (stored) loadProducts(stored, { resetCursor: true });
  }, [loadProducts]);

  useDummyRefresh(() => { if (token) void loadProducts(token, { resetCursor: true }); });

  function startEdit(product: AdminProduct) {
    setEditingProduct(product); setNewPrice(String(product.price ?? '')); setActionError(null); setActionSuccess(null);
  }

  function selectProduct(product: AdminProduct) {
    setSelectedProduct(product);
    if (token) loadHistory(token, product.id);
  }

  async function handleSavePrice() {
    if (!token || !editingProduct) return;
    const parsed = Number(newPrice);
    if (!Number.isFinite(parsed) || parsed < 0) { setActionError('Harga harus angka ≥ 0.'); return; }
    setSaving(true); setActionError(null); setActionSuccess(null);
    try {
      const updated = await updateProductPrice(token, editingProduct.id, parsed);
      setProducts((prev) => prev.map((p) => (p.id === editingProduct.id ? { ...p, ...updated } : p)));
      setEditingProduct(null); setActionSuccess('Harga produk diperbarui.');
    } catch (reason) { setActionError(reason instanceof Error ? reason.message : 'Harga tidak dapat diperbarui.'); } finally { setSaving(false); }
  }

  if (!ready) return <p className="text-sm text-gray-500">Memuat...</p>;
  if (!token) return <div className="mx-auto max-w-6xl"><PageHeader title="Kelola produk" description="Lihat daftar produk, ubah harga, dan lihat riwayat perubahan harga." /><div className="mb-5 rounded-lg border border-warning-200 bg-warning-50 p-3 text-sm text-warning-700">Masuk sebagai administrator untuk mengelola produk.</div><LoginForm expectedRole="admin" onLogin={(nextToken) => { setToken(nextToken); loadProducts(nextToken, { resetCursor: true }); }} /></div>;

  const columns = [
    {
      key: 'name',
      header: 'Nama Produk',
      render: (p: AdminProduct) => <button onClick={() => selectProduct(p)} className="font-medium text-primary-700 hover:underline text-left">{p.name}</button>,
    },
    { key: 'sku', header: 'SKU', render: (p: AdminProduct) => <span className="text-sm text-gray-600">{p.sku ?? '—'}</span> },
    {
      key: 'price',
      header: 'Harga',
      render: (p: AdminProduct) => (
        <span className="font-medium">
          Rp {Number(p.price ?? 0).toLocaleString('id-ID')}
        </span>
      ),
    },
    {
      key: 'stock_quantity',
      header: 'Stok',
      render: (p: AdminProduct) => (
        <span className={`font-medium ${typeof p.stock_quantity === 'number' && p.stock_quantity <= 0 ? 'text-danger-600' : 'text-gray-800'}`}>
          {typeof p.stock_quantity === 'number' ? p.stock_quantity : '—'}
        </span>
      ),
    },
    {
      key: 'created_at',
      header: 'Dibuat',
      render: (p: AdminProduct) => <span className="text-xs text-gray-600">{formatDateTime(p.created_at ?? null)}</span>,
    },
    {
      key: 'updated_at',
      header: 'Diperbarui',
      render: (p: AdminProduct) => <span className="text-xs text-gray-600">{formatDateTime(p.updated_at ?? null)}</span>,
    },
    {
      key: 'action',
      header: 'Aksi',
      render: (p: AdminProduct) => <Button size="sm" variant="secondary" onClick={() => startEdit(p)}>Ubah harga</Button>,
    },
  ];

  return (
    <div className="mx-auto max-w-6xl">
      <PageHeader title="Kelola produk" description="Perbarui harga produk dan pantau riwayat perubahan." />
      {error && <p role="alert" className="mb-5 rounded-lg border border-danger-200 bg-danger-50 p-3 text-sm text-danger-700">{error}</p>}
      {actionError && <p role="alert" className="mb-3 rounded-lg border border-danger-200 bg-danger-50 p-3 text-sm text-danger-700">{actionError}</p>}
      {actionSuccess && <p className="mb-3 rounded-lg border border-success-200 bg-success-50 p-3 text-sm text-success-700">{actionSuccess}</p>}
      <Card className="mb-5 p-5">
        <div className="grid gap-3 sm:grid-cols-3">
          <Input label="Cari produk" placeholder="Nama atau SKU" value={search} onChange={(e) => setSearch(e.target.value)} />
          <div className="flex items-end"><Button onClick={() => token && loadProducts(token, { resetCursor: true })} disabled={loading} className="w-full">Cari</Button></div>
          <div className="hidden sm:block" />
        </div>
      </Card>
      <div className="grid gap-5 lg:grid-cols-[1.7fr_1fr]">
        <Card className="overflow-hidden">
          <div className="flex flex-wrap items-center justify-between gap-3 border-b border-gray-100 px-5 py-3">
            <TableSummary
              total={total ?? products.length}
              breakdown={tableSummary ? [{ label: 'stok habis', value: tableSummary.out_of_stock }] : undefined}
              noun="produk"
            />
          </div>
          {loading ? (
            <p className="p-8 text-sm text-gray-500">Memuat produk...</p>
          ) : (
            <Table
              columns={columns}
              rows={products}
              rowKey={(p) => p.id}
              sortableColumns={['name', 'sku', 'price', 'stock_quantity', 'created_at', 'updated_at']}
              sort={sort}
              onSort={(column) => {
                const next = toggleSort(sortRef.current, column);
                setSort(next);
                if (token) void loadProducts(token, { resetCursor: true, sort: next });
              }}
              empty={<EmptyState icon={<span>📦</span>} title="Belum ada produk" description="Produk akan muncul di sini." />}
            />
          )}
          <TablePagination
            cursor={cursor}
            limit={15}
            total={total}
            hasMore={hasMore}
            onPageChange={(nextCursor) => {
              setCursor(nextCursor);
              if (token) void loadProducts(token, { cursor: nextCursor });
            }}
          />
        </Card>
        <div className="space-y-5">
          {editingProduct && (
            <Card className="p-5">
              <h3 className="text-sm font-semibold text-gray-900">Ubah harga — {editingProduct.name}</h3>
              <div className="mt-4 space-y-3">
                <Input label="Harga baru (Rp)" type="number" min="0" step="100" value={newPrice} onChange={(e) => setNewPrice(e.target.value)} />
                <div className="flex gap-2">
                  <Button onClick={handleSavePrice} disabled={saving}>{saving ? 'Menyimpan...' : 'Simpan harga'}</Button>
                  <Button variant="ghost" onClick={() => setEditingProduct(null)}>Batal</Button>
                </div>
              </div>
            </Card>
          )}
          <Card className="p-5">
            {selectedProduct === null ? (
              <EmptyState icon={<span>👆</span>} title="Pilih produk" description="Klik nama produk untuk melihat riwayat harga." />
            ) : historyLoading ? (
              <p className="text-sm text-gray-500">Memuat riwayat harga...</p>
            ) : (
              <>
                <h3 className="text-sm font-semibold text-gray-900">Riwayat harga — {selectedProduct.name}</h3>
                {priceHistory.length === 0 ? (
                  <p className="mt-2 text-sm text-gray-500">Belum ada riwayat perubahan harga.</p>
                ) : (
                  <ul className="mt-3 space-y-2 text-sm text-gray-600">
                    {priceHistory.map((entry) => (
                      <li key={entry.id} className="rounded-lg border border-gray-100 px-3 py-2">
                        <div className="flex items-center justify-between">
                          <span className="font-medium text-gray-800">Rp {Number(entry.old_price).toLocaleString('id-ID')} → Rp {Number(entry.new_price).toLocaleString('id-ID')}</span>
                          <span className="text-xs text-gray-500">{entry.changed_at ? new Date(entry.changed_at).toLocaleString('id-ID') : '—'}</span>
                        </div>
                        {entry.changed_by !== undefined && entry.changed_by !== null && <span className="text-xs text-gray-400">oleh ID {entry.changed_by}</span>}
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

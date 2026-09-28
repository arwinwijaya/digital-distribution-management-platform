'use client';

import { useCallback, useEffect, useRef, useState } from 'react';
import LoginForm from '@/components/LoginForm';
import { getStoredToken } from '@/lib/api';
import { fetchAdminProducts, updateProductPrice, NO_CATEGORY_FILTER, type AdminProduct } from './api';
import { useDummyRefresh } from '@/dummy/guards';
import { Button, Badge, Card, EmptyState, Input, PageHeader, Table, TableSummary, TablePagination, TableDensityToggle, Select } from '@/components/ui';
import { useTableDensity } from '@/hooks/useTableDensity';
import { toggleSort, formatDateTime, type ColumnSort } from '@/lib/admin-table';
import {
  STOCK_UNIT_LABEL,
  categoryDisplay,
  classifyStockHealth,
  deriveDisplayStatus,
  normalizeStock,
  stockHealthLabel,
  type DisplayStatus,
} from './product-clarity';
import ProductRowDetail, { PRICE_TOOLTIP, ProductRowPriceAction, ProductRowTrigger } from './ProductRowDetail';

/** Badge colour per derived display status (single badge, explicit precedence). */
const STATUS_BADGE_VARIANT: Record<DisplayStatus, 'green' | 'yellow' | 'gray'> = {
  Aktif: 'green',
  'Tidak bisa dibeli': 'yellow',
  Nonaktif: 'gray',
};

export default function AdminProductsPage() {
  const [token, setToken] = useState<string | null>(null);
  const [products, setProducts] = useState<AdminProduct[]>([]);
  const [ready, setReady] = useState(false);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [actionError, setActionError] = useState<string | null>(null);
  const [actionSuccess, setActionSuccess] = useState<string | null>(null);
  const [search, setSearch] = useState('');
  const [category, setCategory] = useState('');
  const [status, setStatus] = useState('');
  const [stockHealth, setStockHealth] = useState('');
  const [categories, setCategories] = useState<string[]>([]);
  const [editingProduct, setEditingProduct] = useState<AdminProduct | null>(null);
  const [newPrice, setNewPrice] = useState('');
  const [saving, setSaving] = useState(false);
  const [expandedProductId, setExpandedProductId] = useState<number | null>(null);
  // Table state
  const [hasMore, setHasMore] = useState(false);
  const [sort, setSort] = useState<ColumnSort>({ column: 'created_at', order: 'desc' });
  const [cursor, setCursor] = useState(0);
  const [total, setTotal] = useState<number>();
  const [tableSummary, setTableSummary] = useState<{ total: number; out_of_stock: number }>();
  const { density, setDensity } = useTableDensity();

  // Latest sort/cursor readable inside `loadProducts` WITHOUT adding them to its
  // dependency list (which would otherwise re-run the mount effect and reset the
  // page on every sort/page change).
  const sortRef = useRef(sort);
  sortRef.current = sort;
  const cursorRef = useRef(cursor);
  cursorRef.current = cursor;
  const filtersRef = useRef({ category, status, stockHealth });
  filtersRef.current = { category, status, stockHealth };

  const loadProducts = useCallback(async (authToken: string, opts?: { resetCursor?: boolean; cursor?: number; sort?: ColumnSort; filters?: { category?: string; status?: string; stockHealth?: string } }) => {
    setLoading(true); setError(null);
    try {
      const nextSort = opts?.sort ?? sortRef.current;
      const nextCursor = opts?.cursor ?? (opts?.resetCursor ? 0 : cursorRef.current);
      const selectedFilters = opts?.filters ?? filtersRef.current;
      const result = await fetchAdminProducts(authToken, {
        search: search || undefined,
        category: selectedFilters.category || undefined,
        status: selectedFilters.status || undefined,
        stockHealth: selectedFilters.stockHealth || undefined,
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
      setCategories(result.categories ?? []);
    } catch (reason) { setError(reason instanceof Error ? reason.message : 'Daftar produk tidak dapat dimuat.'); } finally { setLoading(false); }
  }, [search]);

  useEffect(() => {
    const stored = getStoredToken(); setToken(stored); setReady(true);
    if (stored) loadProducts(stored, { resetCursor: true });
  }, [loadProducts]);

  useDummyRefresh(() => { if (token) void loadProducts(token, { resetCursor: true }); });

  function startEdit(product: AdminProduct) {
    setEditingProduct(product); setNewPrice(String(product.price ?? '')); setActionError(null); setActionSuccess(null);
  }

  function toggleExpanded(product: AdminProduct) {
    setExpandedProductId((current) => current === product.id ? null : product.id);
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
      header: 'Nama',
      render: (p: AdminProduct) => (
        <ProductRowTrigger
          product={p}
          detailId={`product-detail-${p.id}`}
          expanded={expandedProductId === p.id}
          onToggle={() => toggleExpanded(p)}
        />
      ),
    },
    { key: 'sku', header: 'SKU', render: (p: AdminProduct) => <span className="text-sm text-gray-600">{p.sku ?? '—'}</span> },
    {
      key: 'category',
      header: 'Kategori',
      render: (p: AdminProduct) => <span className="text-sm text-gray-600">{categoryDisplay(p.category)}</span>,
    },
    {
      key: 'status',
      header: 'Status',
      render: (p: AdminProduct) => {
        const status = deriveDisplayStatus({ is_active: p.is_active, supplier: p.supplier ?? undefined });
        return <Badge variant={STATUS_BADGE_VARIANT[status]}>{status}</Badge>;
      },
    },
    {
      key: 'price',
      header: 'Harga Jual',
      render: (p: AdminProduct) => (
        <span className="font-medium" title={PRICE_TOOLTIP}>
          Rp {Number(p.price ?? 0).toLocaleString('id-ID')}
        </span>
      ),
    },
    {
      key: 'stock_quantity',
      header: STOCK_UNIT_LABEL,
      render: (p: AdminProduct) => {
        const stock = normalizeStock(p.stock_quantity);
        const health = classifyStockHealth(p.stock_quantity);
        const tone = health === 'out' ? 'text-danger-600' : health === 'low' ? 'text-warning-700' : 'text-success-700';
        return (
          <span className="inline-flex items-baseline gap-2">
            <span className={`font-medium ${tone}`}>{stock}</span>
            <span className="text-xs">{stockHealthLabel(health)}</span>
          </span>
        );
      },
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
      render: (p: AdminProduct) => <ProductRowPriceAction product={p} onPrice={startEdit} />,
    },
  ];

  return (
    <div className="mx-auto max-w-6xl">
      <PageHeader title="Kelola produk" description="Perbarui harga produk dan pantau riwayat perubahan." />
      {error && <p role="alert" className="mb-5 rounded-lg border border-danger-200 bg-danger-50 p-3 text-sm text-danger-700">{error}</p>}
      {actionError && <p role="alert" className="mb-3 rounded-lg border border-danger-200 bg-danger-50 p-3 text-sm text-danger-700">{actionError}</p>}
      {actionSuccess && <p className="mb-3 rounded-lg border border-success-200 bg-success-50 p-3 text-sm text-success-700">{actionSuccess}</p>}
      <Card className="mb-5 p-5">
        <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
          <Input label="Cari produk" placeholder="Nama atau SKU" value={search} onChange={(e) => setSearch(e.target.value)} />
          <Select label="Kategori" value={category} onChange={(e) => {
            const next = e.target.value === NO_CATEGORY_FILTER || categories.includes(e.target.value) ? e.target.value : '';
            setCategory(next); setExpandedProductId(null);
            if (token) void loadProducts(token, { resetCursor: true, filters: { category: next, status, stockHealth } });
          }}>
            <option value="">Semua kategori</option>
            {categories.map((value) => <option key={value} value={value}>{value}</option>)}
            <option value={NO_CATEGORY_FILTER}>Tanpa kategori</option>
          </Select>
          <Select label="Status" value={status} onChange={(e) => {
            const next = ['active', 'inactive', 'unpurchasable'].includes(e.target.value) ? e.target.value : '';
            setStatus(next); setExpandedProductId(null);
            if (token) void loadProducts(token, { resetCursor: true, filters: { category, status: next, stockHealth } });
          }}>
            <option value="">Semua</option>
            <option value="active">Aktif</option>
            <option value="inactive">Nonaktif</option>
            <option value="unpurchasable">Tidak bisa dibeli</option>
          </Select>
          <Select label="Kesehatan stok" value={stockHealth} onChange={(e) => {
            const next = ['out', 'low', 'ok'].includes(e.target.value) ? e.target.value : '';
            setStockHealth(next); setExpandedProductId(null);
            if (token) void loadProducts(token, { resetCursor: true, filters: { category, status, stockHealth: next } });
          }}>
            <option value="">Semua</option>
            <option value="out">Habis</option>
            <option value="low">Rendah</option>
            <option value="ok">Aman</option>
          </Select>
          <div className="flex items-end"><Button onClick={() => token && loadProducts(token, { resetCursor: true })} disabled={loading} className="w-full">Cari</Button></div>
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
            <TableDensityToggle value={density} onChange={setDensity} />
          </div>
          {loading ? (
            <p className="p-8 text-sm text-gray-500">Memuat produk...</p>
          ) : (
            <>
              <Table
                columns={columns}
                rows={products}
                rowKey={(p) => p.id}
                density={density}
                sortableColumns={['name', 'sku', 'category', 'status', 'price', 'stock_quantity', 'created_at', 'updated_at']}
                sort={sort}
                onSort={(column) => {
                  const next = toggleSort(sortRef.current, column);
                  setSort(next);
                  setExpandedProductId(null);
                  if (token) void loadProducts(token, { resetCursor: true, sort: next });
                }}
                empty={<EmptyState icon={<span>📦</span>} title="Belum ada produk" description="Produk akan muncul di sini." />}
              />
              {expandedProductId !== null && (() => {
                const expandedProduct = products.find((product) => product.id === expandedProductId);
                return expandedProduct ? <ProductRowDetail product={expandedProduct} detailId={`product-detail-${expandedProduct.id}`} /> : null;
              })()}
            </>
          )}
          <TablePagination
            cursor={cursor}
            limit={15}
            total={total}
            hasMore={hasMore}
            onPageChange={(nextCursor) => {
              setCursor(nextCursor);
              if (token) void loadProducts(token, { cursor: nextCursor, filters: filtersRef.current });
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

        </div>
      </div>
    </div>
  );
}

'use client';

import { FormEvent, useCallback, useEffect, useMemo, useState } from 'react';
import LoginForm from '@/components/LoginForm';
import { getStoredToken } from '@/lib/api';
import {
  createSalesOrder,
  fetchCatalogProducts,
  fetchSalesOutlets,
  formatRupiah,
  parseMoney,
  type CatalogProduct,
  type CreatedSalesOrder,
  type SalesOutlet,
} from './api';
import { Button, Card, EmptyState, Input, PageHeader, Select, Table } from '@/components/ui';

export default function SalesOrdersPage() {
  const [token, setToken] = useState<string | null>(null);
  const [ready, setReady] = useState(false);
  const [outlets, setOutlets] = useState<SalesOutlet[]>([]);
  const [products, setProducts] = useState<CatalogProduct[]>([]);
  const [loading, setLoading] = useState(false);
  const [loadError, setLoadError] = useState<string | null>(null);
  const [territoryError, setTerritoryError] = useState<string | null>(null);
  const [submitError, setSubmitError] = useState<string | null>(null);
  const [submitting, setSubmitting] = useState(false);
  const [selectedOutletId, setSelectedOutletId] = useState('');
  const [quantities, setQuantities] = useState<Record<number, number>>({});
  const [created, setCreated] = useState<CreatedSalesOrder | null>(null);

  const load = useCallback(async (authToken: string) => {
    setLoading(true);
    setLoadError(null);
    setTerritoryError(null);
    try {
      const [outletList, productList] = await Promise.all([
        fetchSalesOutlets(authToken),
        fetchCatalogProducts(authToken),
      ]);
      setOutlets(outletList);
      setProducts(productList);
    } catch (reason) {
      const message = reason instanceof Error ? reason.message : 'Data pesanan tidak dapat dimuat.';
      // Territory unassigned → backend returns 403 with a specific message.
      if (/territory/i.test(message)) setTerritoryError(message);
      else setLoadError(message);
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    const stored = getStoredToken();
    setToken(stored);
    setReady(true);
    if (stored) load(stored);
  }, [load]);

  const selectedOutlet = useMemo(
    () => outlets.find((o) => String(o.id) === selectedOutletId) ?? null,
    [outlets, selectedOutletId],
  );

  const lineItems = useMemo(
    () =>
      products
        .map((product) => ({ product, quantity: quantities[product.id] ?? 0 }))
        .filter((line) => line.quantity > 0),
    [products, quantities],
  );

  const estimatedTotal = useMemo(
    () => lineItems.reduce((sum, line) => sum + parseMoney(line.product.price) * line.quantity, 0),
    [lineItems],
  );

  function setQuantity(productId: number, raw: string) {
    const parsed = Math.max(0, Math.floor(Number(raw) || 0));
    setQuantities((prev) => ({ ...prev, [productId]: parsed }));
  }

  async function submit(event: FormEvent) {
    event.preventDefault();
    if (!token) return;
    setSubmitError(null);
    setCreated(null);
    if (!selectedOutletId) {
      setSubmitError('Pilih outlet terlebih dahulu.');
      return;
    }
    if (lineItems.length === 0) {
      setSubmitError('Tambahkan minimal satu produk dengan jumlah lebih dari 0.');
      return;
    }
    setSubmitting(true);
    try {
      const order = await createSalesOrder(token, {
        outlet_id: Number(selectedOutletId),
        items: lineItems.map((line) => ({ product_id: line.product.id, quantity: line.quantity })),
      });
      setCreated(order);
      setQuantities({});
    } catch (reason) {
      setSubmitError(reason instanceof Error ? reason.message : 'Pesanan tidak dapat dibuat.');
    } finally {
      setSubmitting(false);
    }
  }

  if (!ready) return <p className="text-sm text-gray-500">Memuat...</p>;
  if (!token)
    return (
      <div className="mx-auto max-w-6xl">
        <PageHeader title="Buat pesanan sales" description="Buat pesanan untuk outlet di wilayah Anda." />
        <LoginForm
          expectedRole="sales"
          onLogin={(nextToken) => {
            setToken(nextToken);
            load(nextToken);
          }}
        />
      </div>
    );

  const columns = [
    { key: 'name', header: 'Produk', render: (p: CatalogProduct) => <span className="font-medium text-gray-900">{p.name}</span> },
    { key: 'price', header: 'Harga', render: (p: CatalogProduct) => <span className="text-gray-600">{formatRupiah(p.price)}</span> },
    { key: 'stock', header: 'Stok', render: (p: CatalogProduct) => <span className="text-gray-600">{p.stock_quantity ?? '—'}</span> },
    {
      key: 'quantity',
      header: 'Jumlah',
      render: (p: CatalogProduct) => (
        <input
          type="number"
          min={0}
          aria-label={`Jumlah ${p.name}`}
          value={quantities[p.id] ?? 0}
          onChange={(event) => setQuantity(p.id, event.target.value)}
          className="w-24 rounded-lg border border-gray-200 px-3 py-1.5 text-sm focus:border-primary-500 focus:ring-2 focus:ring-primary-500/20 focus:outline-none"
        />
      ),
    },
    {
      key: 'subtotal',
      header: 'Subtotal',
      render: (p: CatalogProduct) => (
        <span className="font-medium text-gray-900">{formatRupiah(parseMoney(p.price) * (quantities[p.id] ?? 0))}</span>
      ),
    },
  ];

  return (
    <div className="mx-auto max-w-6xl">
      <PageHeader title="Buat pesanan sales" description="Buat pesanan untuk outlet di wilayah Anda." />

      {territoryError && (
        <p role="alert" className="mb-5 rounded-lg border border-warning-200 bg-warning-50 p-3 text-sm text-warning-700">
          {territoryError} Hubungi administrator untuk menetapkan wilayah kerja Anda.
        </p>
      )}
      {loadError && (
        <p role="alert" className="mb-5 rounded-lg border border-danger-200 bg-danger-50 p-3 text-sm text-danger-700">
          {loadError}
        </p>
      )}
      {submitError && (
        <p role="alert" className="mb-5 rounded-lg border border-danger-200 bg-danger-50 p-3 text-sm text-danger-700">
          {submitError}
        </p>
      )}
      {created && (
        <p role="status" className="mb-5 rounded-lg border border-success-200 bg-success-50 p-3 text-sm text-success-700">
          Pesanan {created.order_id} berhasil dibuat untuk {selectedOutlet?.name ?? 'outlet'} — total {formatRupiah(created.total_amount)}.
        </p>
      )}

      <form onSubmit={submit}>
        <Card className="mb-6 p-5">
          <h2 className="mb-4 text-base font-semibold text-gray-900">Outlet tujuan</h2>
          <Select
            label="Pilih outlet (wilayah Anda)"
            value={selectedOutletId}
            onChange={(event) => setSelectedOutletId(event.target.value)}
            disabled={loading || outlets.length === 0}
          >
            <option value="">{outlets.length === 0 ? 'Tidak ada outlet di wilayah Anda' : 'Pilih outlet'}</option>
            {outlets.map((outlet) => (
              <option key={outlet.id} value={outlet.id}>
                {`${outlet.name}${outlet.city ? ` — ${outlet.city}` : ''}`}
              </option>
            ))}
          </Select>
          {selectedOutlet && (
            <p className="mt-3 text-xs text-gray-500">
              {selectedOutlet.category ?? 'lainnya'}
              {selectedOutlet.district ? ` · ${selectedOutlet.district}` : ''}
              {selectedOutlet.city ? ` · ${selectedOutlet.city}` : ''}
            </p>
          )}
        </Card>

        <Card className="mb-6 overflow-hidden">
          <div className="flex items-center justify-between border-b border-gray-100 px-5 py-4">
            <h2 className="font-semibold text-gray-900">Produk</h2>
            <span className="text-sm text-gray-500">Perkiraan total: <span className="font-semibold text-gray-900">{formatRupiah(estimatedTotal)}</span></span>
          </div>
          {loading ? (
            <p className="p-8 text-sm text-gray-500">Memuat produk...</p>
          ) : (
            <Table
              columns={columns}
              rows={products}
              rowKey={(p) => p.id}
              empty={<EmptyState icon={<span>📦</span>} title="Belum ada produk" description="Katalog produk akan tampil di sini." />}
            />
          )}
        </Card>

        <div className="flex items-center justify-end gap-3">
          <span className="text-sm text-gray-500">{lineItems.length} baris produk dipilih</span>
          <Button type="submit" disabled={submitting || loading || Boolean(territoryError)}>
            {submitting ? 'Mengirim...' : 'Buat pesanan'}
          </Button>
        </div>
      </form>
    </div>
  );
}

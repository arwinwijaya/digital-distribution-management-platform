'use client';

import { useMemo, useState } from 'react';
import type { Dispatch, SetStateAction } from 'react';
import type { Product } from '@/components/OrderForm';
import { Card, EmptyState, Input, StatusBadge, Table, ViewModeToggle, useViewMode } from '@/components/ui';
import { filterProducts } from '@/lib/product-filter';

interface Props {
  products: Product[];
  cart: Record<number, number>;
  setCart: Dispatch<SetStateAction<Record<number, number>>>;
}

/** Catalog controls and product selection, independent of order submission and recommendations. */
export default function OrderFormCatalog({ products, cart, setCart }: Props) {
  const [search, setSearch] = useState(''); const [minPrice, setMinPrice] = useState(''); const [maxPrice, setMaxPrice] = useState('');
  const { viewMode, setViewMode } = useViewMode();
  const filteredProducts = useMemo(() => filterProducts(products, { name: search, minPrice, maxPrice }), [products, search, minPrice, maxPrice]);
  const changeQuantity = (id: number, quantity: number) => setCart((current) => ({ ...current, [id]: Math.max(0, quantity) }));
  const columns = [
    { key: 'name', header: 'Produk', render: (product: Product) => <span className="font-medium text-gray-900">{product.name}</span> },
    { key: 'price', header: 'Harga', render: (product: Product) => <span className="font-bold text-primary-700">Rp {Number(product.price).toLocaleString('id-ID')}</span> },
    { key: 'stock', header: 'Stok', render: (product: Product) => <span className="text-gray-600">{product.stock_quantity}</span> },
    { key: 'quantity', header: 'Jumlah', render: (product: Product) => { const available = product.is_active && product.stock_quantity > 0; return <input aria-label={`Jumlah ${product.name}`} type="number" min="0" max={product.stock_quantity} disabled={!available} value={cart[product.id] || 0} onChange={(event) => changeQuantity(product.id, Number(event.target.value))} className="w-20 rounded-lg border border-gray-200 px-2 py-1.5 text-center text-sm focus:border-primary-500 focus:outline-none disabled:bg-gray-100" />; } },
  ];

  return <>
    <div className="mb-3 flex flex-wrap items-center justify-between gap-3">
      <div>
        <h2 className="text-base font-semibold text-gray-900">Pilih produk</h2>
        <span className="text-sm text-gray-500">{filteredProducts.length} dari {products.length} produk</span>
      </div>
      <ViewModeToggle value={viewMode} onChange={setViewMode} />
    </div>
    <div className="mb-4 grid gap-3 sm:grid-cols-3">
      <Input aria-label="Cari produk" value={search} onChange={(event) => setSearch(event.target.value)} placeholder="Cari nama produk..." />
      <Input aria-label="Harga minimum" type="number" min="0" value={minPrice} onChange={(event) => setMinPrice(event.target.value)} placeholder="Harga min" />
      <Input aria-label="Harga maksimum" type="number" min="0" value={maxPrice} onChange={(event) => setMaxPrice(event.target.value)} placeholder="Harga maks" />
    </div>
    {products.length === 0
      ? <Card><EmptyState icon={<span>📦</span>} title="Belum ada produk" description="Produk yang tersedia akan muncul di sini." /></Card>
      : filteredProducts.length === 0
        ? <Card><EmptyState icon={<span>🔍</span>} title="Produk tidak ditemukan" description="Tidak ada produk yang cocok dengan pencarian atau rentang harga Anda." /></Card>
        : viewMode === 'table'
          ? <Card className="overflow-hidden"><Table columns={columns} rows={filteredProducts} rowKey={(product) => product.id} /></Card>
          : <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">{filteredProducts.map((product) => { const available = product.is_active && product.stock_quantity > 0; return <Card key={product.id} className="p-4"><div className="flex items-start justify-between gap-3"><div className="flex h-10 w-10 items-center justify-center rounded-lg bg-primary-50">📦</div><StatusBadge status={available ? 'active' : 'inactive'} /></div><h3 className="mt-3 font-semibold text-gray-900">{product.name}</h3><p className="mt-1 text-sm font-bold text-primary-700">Rp {Number(product.price).toLocaleString('id-ID')}</p><div className="mt-3 flex items-center justify-between"><span className="text-xs text-gray-500">Stok {product.stock_quantity}</span><input aria-label={`Jumlah ${product.name}`} type="number" min="0" max={product.stock_quantity} disabled={!available} value={cart[product.id] || 0} onChange={(event) => changeQuantity(product.id, Number(event.target.value))} className="w-20 rounded-lg border border-gray-200 px-2 py-1.5 text-center text-sm focus:border-primary-500 focus:outline-none disabled:bg-gray-100" /></div></Card>; })}</div>}
  </>;
}

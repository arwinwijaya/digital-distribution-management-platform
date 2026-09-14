interface StockItem {
  product_id: number;
  product_name?: string;
  sku: string;
  status: string;
  available_stock: number;
  average_daily_demand: number;
  reorder_quantity?: number;
  suggested_reorder_quantity?: number | null;
}

interface Props {
  items: StockItem[];
  loading?: boolean;
}

export default function StockPlanningTable({ items, loading }: Props) {
  if (loading) return <p className="text-sm text-gray-500">Memuat...</p>;
  if (!items.length) {
    return <p data-testid="empty-stock" className="py-6 text-center text-sm text-gray-400">Belum ada data stok.</p>;
  }
  return (
    <div className="overflow-x-auto" data-testid="stock-table">
      <table className="w-full text-sm">
        <thead>
          <tr>
            <th className="px-3 py-2 text-left">Produk</th>
            <th className="px-3 py-2 text-left">SKU</th>
            <th className="px-3 py-2 text-center">Status</th>
            <th className="px-3 py-2 text-right">Stok</th>
            <th className="px-3 py-2 text-right">Permintaan / hari</th>
            <th className="px-3 py-2 text-right">Reorder</th>
          </tr>
        </thead>
        <tbody>
          {items.map((p) => (
            <tr key={p.product_id}>
              <td className="px-3 py-2">{p.product_name ?? p.sku}</td>
              <td className="px-3 py-2">{p.sku}</td>
              <td className="px-3 py-2 text-center">{p.status}</td>
              <td className="px-3 py-2 text-right">{p.available_stock}</td>
              <td className="px-3 py-2 text-right">{p.average_daily_demand}</td>
              <td className="px-3 py-2 text-right">{p.suggested_reorder_quantity ?? p.reorder_quantity ?? 0}</td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );
}

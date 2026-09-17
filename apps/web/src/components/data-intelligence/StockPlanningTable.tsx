import { EmptyState, StatusBadge, Table } from '@/components/ui';
import { formatCount, formatDecimal } from '@/lib/format';
import type { StockPlanRecord } from '@/lib/data-intelligence-api';

interface Props {
  items: StockPlanRecord[];
  loading?: boolean;
}

export default function StockPlanningTable({ items, loading }: Props) {
  if (loading) return <p className="py-6 text-center text-sm text-gray-500">Memuat…</p>;
  if (!items.length) {
    return (
      <p data-testid="empty-stock" className="py-6 text-center text-sm text-gray-400">
        Belum ada data stok.
      </p>
    );
  }

  const columns = [
    {
      key: 'product',
      header: 'Produk',
      render: (item: StockPlanRecord) => (
        <span className="font-medium text-gray-900">{item.product_name ?? item.sku}</span>
      ),
    },
    {
      key: 'sku',
      header: 'SKU',
      render: (item: StockPlanRecord) => <span className="font-mono text-xs text-gray-500">{item.sku}</span>,
    },
    {
      key: 'status',
      header: 'Status',
      render: (item: StockPlanRecord) => <StatusBadge status={item.status} />,
    },
    {
      key: 'available',
      header: 'Stok',
      className: 'text-right',
      render: (item: StockPlanRecord) => (
        <span className="tabular-nums text-gray-700">{formatCount(item.available_stock)}</span>
      ),
    },
    {
      key: 'demand',
      header: 'Permintaan / hari',
      className: 'text-right',
      render: (item: StockPlanRecord) => (
        <span className="tabular-nums text-gray-700">{formatDecimal(item.average_daily_demand)}</span>
      ),
    },
    {
      key: 'reorder',
      header: 'Reorder',
      className: 'text-right',
      render: (item: StockPlanRecord) => {
        const quantity = item.reorder_quantity ?? 0;
        return quantity > 0 ? (
          <span className="font-medium tabular-nums text-primary-700">{formatCount(quantity)}</span>
        ) : (
          <span className="tabular-nums text-gray-400">0</span>
        );
      },
    },
  ];

  return (
    <div data-testid="stock-table">
      <Table
        columns={columns}
        rows={items}
        rowKey={(item) => item.product_id}
        empty={
          <EmptyState
            icon={<span>📦</span>}
            title="Belum ada data stok"
            description="Rencana stok akan muncul setelah snapshot tersedia."
          />
        }
      />
    </div>
  );
}

import { EmptyState, Table } from '@/components/ui';
import { formatCount, formatRupiah } from '@/lib/format';
import type { GeographicTableRow } from '@/lib/data-intelligence-api';

interface Props {
  territories: GeographicTableRow[];
  loading: boolean;
}

export default function TerritoryTable({ territories, loading }: Props) {
  if (loading && territories.length === 0) {
    return <p className="py-6 text-center text-sm text-gray-500">Memuat data wilayah…</p>;
  }

  const columns = [
    {
      key: 'territory',
      header: 'Wilayah',
      render: (row: GeographicTableRow) => (
        <span className="font-medium text-gray-900">{row.territory}</span>
      ),
    },
    {
      key: 'sales',
      header: 'Penjualan',
      className: 'text-right',
      render: (row: GeographicTableRow) => (
        <span className="font-medium tabular-nums text-gray-900">{formatRupiah(row.sales)}</span>
      ),
    },
    {
      key: 'orders',
      header: 'Pesanan',
      className: 'text-right',
      render: (row: GeographicTableRow) => (
        <span className="tabular-nums text-gray-700">{formatCount(row.orders)}</span>
      ),
    },
    {
      key: 'outlets',
      header: 'Outlet',
      className: 'text-right',
      render: (row: GeographicTableRow) => (
        <span className="tabular-nums text-gray-700">{formatCount(row.outlets)}</span>
      ),
    },
  ];

  return (
    <div data-testid="territory-table">
      <Table
        columns={columns}
        rows={territories}
        rowKey={(row) => row.territory}
        empty={
          <EmptyState
            icon={<span>🗺️</span>}
            title="Tidak ada data wilayah"
            description="Data wilayah akan muncul setelah snapshot tersedia."
          />
        }
      />
    </div>
  );
}

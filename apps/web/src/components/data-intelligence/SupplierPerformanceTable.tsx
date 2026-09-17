import { Badge, EmptyState, StatusBadge, Table } from '@/components/ui';
import { formatPercentage } from '@/lib/format';
import type { SupplierRecord } from '@/lib/data-intelligence-api';

interface Props {
  suppliers: SupplierRecord[];
  loading: boolean;
}

/** Render one coverage metric as `numerator/denominator` plus a ratio badge. */
function CoverageCell({ coverage }: { coverage: { numerator: number; denominator: number; ratio: number | null } }) {
  return (
    <div className="flex items-center justify-end gap-2">
      <span className="tabular-nums text-gray-500">
        {coverage.numerator}/{coverage.denominator}
      </span>
      {coverage.ratio === null ? (
        <span className="text-gray-400">—</span>
      ) : (
        <Badge variant={coverage.ratio >= 0.9 ? 'green' : coverage.ratio >= 0.7 ? 'yellow' : 'red'}>
          {formatPercentage(coverage.ratio)}
        </Badge>
      )}
    </div>
  );
}

export default function SupplierPerformanceTable({ suppliers, loading }: Props) {
  if (loading && suppliers.length === 0) {
    return <p className="py-6 text-center text-sm text-gray-500">Memuat data supplier…</p>;
  }

  const columns = [
    {
      key: 'supplier',
      header: 'Supplier',
      render: (supplier: SupplierRecord) => (
        <span className="font-medium text-gray-900">{supplier.supplier_name}</span>
      ),
    },
    {
      key: 'status',
      header: 'Status',
      render: (supplier: SupplierRecord) => <StatusBadge status={supplier.status} />,
    },
    {
      key: 'fulfillment',
      header: 'Fulfilmen',
      className: 'text-right',
      render: (supplier: SupplierRecord) => <CoverageCell coverage={supplier.fulfillment} />,
    },
    {
      key: 'on_time',
      header: 'Tepat Waktu',
      className: 'text-right',
      render: (supplier: SupplierRecord) => <CoverageCell coverage={supplier.on_time} />,
    },
    {
      key: 'catalog',
      header: 'Katalog',
      className: 'text-right',
      render: (supplier: SupplierRecord) => <CoverageCell coverage={supplier.catalog} />,
    },
    {
      key: 'score',
      header: 'Skor',
      className: 'text-right',
      render: (supplier: SupplierRecord) =>
        supplier.weighted_score === null ? (
          <span className="text-gray-400">—</span>
        ) : (
          <Badge variant={supplier.weighted_score >= 0.9 ? 'green' : supplier.weighted_score >= 0.7 ? 'yellow' : 'red'}>
            {supplier.weighted_score}
          </Badge>
        ),
    },
  ];

  return (
    <div data-testid="supplier-table">
      <Table
        columns={columns}
        rows={suppliers}
        rowKey={(supplier) => supplier.supplier_id}
        empty={
          <EmptyState
            icon={<span>🏢</span>}
            title="Tidak ada data supplier"
            description="Kinerja supplier akan muncul setelah snapshot tersedia."
          />
        }
      />
    </div>
  );
}

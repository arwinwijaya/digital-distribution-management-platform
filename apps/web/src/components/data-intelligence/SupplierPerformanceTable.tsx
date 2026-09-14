import type { SupplierRecord } from '@/lib/data-intelligence-api';

interface Props {
  suppliers: SupplierRecord[];
  loading: boolean;
}

export default function SupplierPerformanceTable({ suppliers, loading }: Props) {
  if (loading && suppliers.length === 0) {
    return <p>Memuat data supplier…</p>;
  }
  if (suppliers.length === 0) {
    return <p>Tidak ada data supplier.</p>;
  }
  return (
    <table data-testid="supplier-table">
      <thead>
        <tr>
          <th>Supplier</th>
          <th>Status</th>
          <th>Fulfilmen</th>
          <th>Tepat Waktu</th>
          <th>Katalog</th>
          <th>Skor</th>
        </tr>
      </thead>
      <tbody>
        {suppliers.map((supplier) => (
          <tr key={supplier.supplier_id}>
            <td>{supplier.supplier_name}</td>
            <td>{supplier.status}</td>
            <td>Cakupan {supplier.fulfillment.denominator} · rasio {supplier.fulfillment.ratio ?? '—'}</td>
            <td>Cakupan {supplier.on_time.denominator} · rasio {supplier.on_time.ratio ?? '—'}</td>
            <td>Cakupan {supplier.catalog.denominator} · rasio {supplier.catalog.ratio ?? '—'}</td>
            <td>{supplier.weighted_score ?? '—'}</td>
          </tr>
        ))}
      </tbody>
    </table>
  );
}

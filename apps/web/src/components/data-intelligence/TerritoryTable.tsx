import type { GeographicTableRow } from '@/lib/data-intelligence-api';

interface Props {
  territories: GeographicTableRow[];
  loading: boolean;
}

export default function TerritoryTable({ territories, loading }: Props) {
  if (loading && territories.length === 0) {
    return <p>Memuat data wilayah…</p>;
  }
  if (territories.length === 0) {
    return <p>Tidak ada data wilayah.</p>;
  }
  return (
    <table data-testid="territory-table">
      <thead>
        <tr>
          <th>Wilayah</th>
          <th>Penjualan</th>
          <th>Pesanan</th>
          <th>Outlet</th>
        </tr>
      </thead>
      <tbody>
        {territories.map((row) => (
          <tr key={row.territory}>
            <td>{row.territory}</td>
            <td>{row.sales}</td>
            <td>{row.orders}</td>
            <td>{row.outlets}</td>
          </tr>
        ))}
      </tbody>
    </table>
  );
}

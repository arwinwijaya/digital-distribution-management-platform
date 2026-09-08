'use client';

export interface TrendPoint {
  period: string;
  orders_total: number;
  sales_total: string;
  payments_total: string;
}

export interface OutletPoint {
  rank: number;
  outlet_id: number;
  outlet_name: string;
  orders_total: number;
  sales_total: string;
}

function maxValue(values: number[]): number {
  return Math.max(...values, 1);
}

export function SalesTrendChart({ points }: { points: TrendPoint[] }) {
  if (points.length === 0) return <p className="text-sm text-gray-500">No sales in this period.</p>;
  const maximum = maxValue(points.map((point) => Number(point.sales_total)));

  return (
    <div className="space-y-3" aria-label="Sales trend chart">
      {points.map((point) => (
        <div key={point.period} className="grid grid-cols-[7rem_1fr_6rem] items-center gap-3 text-sm">
          <time dateTime={point.period} className="text-gray-600">{point.period}</time>
          <div className="h-4 rounded bg-gray-100" role="img" aria-label={`${point.period}: Rp ${point.sales_total}`}>
            <div className="h-4 rounded bg-blue-600" style={{ width: `${(Number(point.sales_total) / maximum) * 100}%` }} />
          </div>
          <span className="text-right font-medium">Rp {Number(point.sales_total).toLocaleString('id-ID')}</span>
        </div>
      ))}
    </div>
  );
}

export function OutletPerformanceChart({ outlets }: { outlets: OutletPoint[] }) {
  if (outlets.length === 0) return <p className="text-sm text-gray-500">No outlet sales in this period.</p>;
  const maximum = maxValue(outlets.map((outlet) => Number(outlet.sales_total)));

  return (
    <ol className="space-y-3" aria-label="Outlet performance ranking">
      {outlets.map((outlet) => (
        <li key={outlet.outlet_id} className="grid grid-cols-[2rem_9rem_1fr_6rem] items-center gap-3 text-sm">
          <span className="font-semibold text-gray-500">#{outlet.rank}</span>
          <span className="truncate" title={outlet.outlet_name}>{outlet.outlet_name}</span>
          <div className="h-4 rounded bg-gray-100" role="img" aria-label={`${outlet.outlet_name}: Rp ${outlet.sales_total}`}>
            <div className="h-4 rounded bg-emerald-600" style={{ width: `${(Number(outlet.sales_total) / maximum) * 100}%` }} />
          </div>
          <span className="text-right font-medium">Rp {Number(outlet.sales_total).toLocaleString('id-ID')}</span>
        </li>
      ))}
    </ol>
  );
}

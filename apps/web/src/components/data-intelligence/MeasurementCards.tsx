import { StatCard } from '@/components/ui';
import { formatCount } from '@/lib/format';

interface FunnelStep {
  step: string;
  count: number;
}

interface ForecastSnapshot {
  status: string;
  wape: string | null;
  accuracy_percent: number | null;
}

interface MeasurementCardsProps {
  funnel: FunnelStep[];
  forecast: ForecastSnapshot | null;
  loading: boolean;
}

/** Indonesian label + emoji for each canonical funnel step. */
const STEP_META: Record<string, { label: string; icon: string }> = {
  displayed: { label: 'Tampil', icon: '👁️' },
  clicked: { label: 'Klik', icon: '🖱️' },
  cart: { label: 'Keranjang', icon: '🛒' },
  purchased: { label: 'Dibeli', icon: '✅' },
};

/** Human label for the raw forecast status token. */
const FORECAST_STATUS_LABEL: Record<string, string> = {
  target_achieved: 'Target tercapai',
  pending: 'Menunggu data',
  'insufficient-data': 'Data belum cukup',
};

/** Step-to-step conversion shown as a pill, e.g. `25.0%` from the previous step. */
function stepRate(funnel: FunnelStep[], index: number): { text: string; type: 'up' | 'down' | 'neutral' } | undefined {
  if (index === 0) return undefined;
  const previous = funnel[index - 1]?.count ?? 0;
  const current = funnel[index]?.count ?? 0;
  if (previous <= 0) return undefined;
  const percent = (current / previous) * 100;
  return { text: `${percent.toFixed(1)}% dari sebelumnya`, type: percent >= 50 ? 'up' : 'neutral' };
}

export default function MeasurementCards({ funnel, forecast, loading }: MeasurementCardsProps) {
  if (loading && funnel.length === 0) {
    return <p className="py-6 text-center text-sm text-gray-500">Memuat pengukuran…</p>;
  }

  return (
    <div data-testid="measurement-cards" className="space-y-5">
      <div>
        <h4 className="mb-3 text-sm font-semibold text-gray-700">Funnel Rekomendasi</h4>
        {funnel.length === 0 ? (
          <p className="py-6 text-center text-sm text-gray-400">Tidak ada data funnel.</p>
        ) : (
          <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            {funnel.map((step, index) => {
              const meta = STEP_META[step.step] ?? { label: step.step, icon: '•' };
              const rate = stepRate(funnel, index);
              return (
                <StatCard
                  key={step.step}
                  label={meta.label}
                  value={formatCount(step.count)}
                  icon={<span>{meta.icon}</span>}
                  change={rate?.text}
                  changeType={rate?.type}
                />
              );
            })}
          </div>
        )}
      </div>

      {forecast && (
        <div className="rounded-lg border border-gray-200 bg-gray-50/60 p-5">
          <h4 className="mb-3 text-sm font-semibold text-gray-700">Pengukuran Prakiraan</h4>
          <dl className="grid gap-4 sm:grid-cols-3">
            <div>
              <dt className="text-xs font-medium uppercase tracking-wide text-gray-500">Status</dt>
              <dd className="mt-1 text-sm font-medium text-gray-900">
                {FORECAST_STATUS_LABEL[forecast.status] ?? forecast.status}
              </dd>
            </div>
            <div>
              <dt className="text-xs font-medium uppercase tracking-wide text-gray-500">WAPE</dt>
              <dd className="mt-1 text-sm font-medium tabular-nums text-gray-900">{forecast.wape ?? '—'}</dd>
            </div>
            <div>
              <dt className="text-xs font-medium uppercase tracking-wide text-gray-500">Akurasi</dt>
              <dd className="mt-1 text-sm font-medium tabular-nums text-gray-900">
                {forecast.accuracy_percent != null ? `${forecast.accuracy_percent}%` : '—'}
              </dd>
            </div>
          </dl>
        </div>
      )}
    </div>
  );
}

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

export default function MeasurementCards({ funnel, forecast, loading }: MeasurementCardsProps) {
  if (loading && funnel.length === 0) {
    return <p>Memuat pengukuran…</p>;
  }

  return (
    <div data-testid="measurement-cards">
      <div>
        <h4>Funnel Rekomendasi</h4>
        {funnel.length === 0 ? (
          <p>Tidak ada data funnel.</p>
        ) : (
          <ul>
            {funnel.map((step) => (
              <li key={step.step}>
                {step.step}: {step.count}
              </li>
            ))}
          </ul>
        )}
      </div>
      {forecast && (
        <div>
          <h4>Pengukuran Prakiraan</h4>
          <ul>
            <li>Status: {forecast.status}</li>
            <li>WAPE: {forecast.wape ?? '—'}</li>
            <li>Akurasi: {forecast.accuracy_percent != null ? `${forecast.accuracy_percent}%` : '—'}</li>
          </ul>
        </div>
      )}
    </div>
  );
}

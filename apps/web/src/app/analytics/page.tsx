'use client';

import { useCallback, useEffect, useState } from 'react';
import LoginForm from '@/components/LoginForm';
import { apiUrl, authHeaders, getStoredToken } from '@/lib/api';

type Measurement = { measured: boolean; note: string };
type DataSufficiency = { sufficient?: boolean; level: string; note: string };

type AIData = {
  recommendations: Array<{ product_id: number; name: string; price: string; reason: string }>;
  recommendationMeta: { data_points: number; data_sufficiency: DataSufficiency; measurement: Measurement };
  forecast: { predictions: Array<{ period: string; forecast_sales: string; forecast_orders: number }>; data_sufficiency: DataSufficiency; method: string; measurement: Measurement };
  segmentation: { segment?: string; confidence?: string; signals?: Record<string, unknown>; segments?: Array<{ outlet_id: number; outlet_name: string; segment: string; confidence: string }> };
};

export default function AnalyticsPage() {
  const [token, setToken] = useState<string | null>(null);
  const [data, setData] = useState<AIData | null>(null);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const load = useCallback(async (authToken: string) => {
    setLoading(true);
    setError(null);
    try {
      const headers = authHeaders(authToken);
      const [recommendations, forecast, segmentation] = await Promise.all([
        fetch(apiUrl('/ai/recommendations'), { headers }),
        fetch(apiUrl('/ai/forecast?period=weekly&horizon=4'), { headers }),
        fetch(apiUrl('/ai/segmentation'), { headers }),
      ]);
      const bodies = await Promise.all([recommendations.json(), forecast.json(), segmentation.json()]);
      const failed = bodies.find((body, index) => ![recommendations, forecast, segmentation][index].ok);
      if (failed) throw new Error(failed.message || 'Unable to load AI analytics.');
      setData({
        recommendations: bodies[0].data.recommendations,
        recommendationMeta: {
          data_points: bodies[0].data.data_points,
          data_sufficiency: bodies[0].data.data_sufficiency,
          measurement: bodies[0].data.measurement,
        },
        forecast: bodies[1].data,
        segmentation: bodies[2].data,
      });
    } catch (loadError) {
      setData(null);
      setError(loadError instanceof Error ? loadError.message : 'Unable to load AI analytics.');
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    const storedToken = getStoredToken();
    setToken(storedToken);
    if (storedToken) load(storedToken);
  }, [load]);

  if (!token) return <main className="mx-auto max-w-6xl space-y-4 p-8"><h1 className="text-3xl font-bold">AI analytics</h1><p className="text-gray-600">Sign in to view safe, explainable recommendations and forecasts.</p><LoginForm onLogin={(nextToken) => { setToken(nextToken); load(nextToken); }} /></main>;
  if (loading && !data) return <main className="mx-auto max-w-6xl p-8" aria-live="polite">Loading AI analytics...</main>;

  return (
    <main className="mx-auto max-w-6xl space-y-6 p-8">
      <header><h1 className="text-3xl font-bold">AI analytics</h1><p className="text-gray-600">Bounded, deterministic insights from existing order data.</p></header>
      {error && <p role="alert" className="rounded border border-red-200 bg-red-50 p-3 text-red-700">{error}</p>}
      {data && <div className="grid gap-6 lg:grid-cols-3">
        <section className="rounded border bg-white p-5 lg:col-span-2"><h2 className="mb-3 text-xl font-semibold">Recommendations</h2><p className="mb-3 text-sm text-gray-500">Data sufficiency: {data.recommendationMeta.data_sufficiency.level} ({data.recommendationMeta.data_points} completed orders). Measurement: {data.recommendationMeta.measurement.measured ? 'measured' : 'not measured'}; this ranking is a deterministic heuristic, not a probability.</p>{data.recommendations.length === 0 ? <p className="text-gray-500">No purchase history is available yet.</p> : <ul className="space-y-2">{data.recommendations.map((item) => <li key={item.product_id} className="flex justify-between border-b py-2"><span>{item.name}<small className="ml-2 text-gray-500">{item.reason}</small></span><span>Rp {Number(item.price).toLocaleString('id-ID')}</span></li>)}</ul>}</section>
        <section className="rounded border bg-white p-5"><h2 className="mb-3 text-xl font-semibold">Segment</h2>{data.segmentation.segment ? <><p className="text-2xl font-semibold">{data.segmentation.segment}</p><p className="text-sm text-gray-500">Confidence: {data.segmentation.confidence}</p></> : <ul className="space-y-2">{(data.segmentation.segments || []).slice(0, 10).map((segment) => <li key={segment.outlet_id} className="flex justify-between"><span>{segment.outlet_name}</span><span>{segment.segment}</span></li>)}</ul>}</section>
        <section className="rounded border bg-white p-5 lg:col-span-3"><h2 className="mb-3 text-xl font-semibold">Four-week forecast</h2><p className="mb-3 text-sm text-gray-500">Method: {data.forecast.method}. Data sufficiency: {data.forecast.data_sufficiency.level}. Measurement: {data.forecast.measurement.measured ? 'measured' : 'not measured'}; this is a deterministic heuristic, not a calibrated probability. {data.forecast.measurement.note}</p>{data.forecast.predictions.length === 0 ? <p className="text-gray-500">Not enough positive history to make a forecast.</p> : <div className="grid gap-3 sm:grid-cols-4">{data.forecast.predictions.map((point) => <article key={point.period} className="rounded bg-gray-50 p-3"><time className="text-sm text-gray-500">{point.period}</time><p className="font-semibold">Rp {Number(point.forecast_sales).toLocaleString('id-ID')}</p><p className="text-sm text-gray-600">{point.forecast_orders} orders</p></article>)}</div>}</section>
      </div>}
    </main>
  );
}

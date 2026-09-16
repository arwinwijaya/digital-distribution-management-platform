/**
 * Analytics data loader — extracted from page.tsx inline 3-way Promise.all.
 * Guarded with `withDummyRead` so zero network while dummy mode is ON.
 */
import { apiUrl, authHeaders } from '@/lib/api';
import { withDummyRead } from '@/dummy/guards';
import { useDummyStore } from '@/dummy/store';
import type { FullDummy } from '@/dummy';

type Measurement = { measured: boolean; note: string };
type DataSufficiency = { sufficient?: boolean; level: string; note: string };

export type AIData = {
  recommendations: Array<{ product_id: number; name: string; price: string; reason: string }>;
  recommendationMeta: {
    data_points: number;
    data_sufficiency: DataSufficiency;
    measurement: Measurement;
  };
  forecast: {
    predictions: Array<{ period: string; forecast_sales: string; forecast_orders: number }>;
    data_sufficiency: DataSufficiency;
    method: string;
    measurement: Measurement;
  };
  segmentation: {
    segment?: string;
    confidence?: string;
    signals?: Record<string, unknown>;
    segments?: Array<{
      outlet_id: number;
      outlet_name: string;
      segment: string;
      confidence: string;
    }>;
  };
};

/**
 * Load analytics (recommendations + forecast + segmentation) for a token.
 * While dummy mode is ON, returns pre-built aggregates — zero network.
 */
export async function loadAnalytics(token: string): Promise<AIData> {
  const { isDummy, dummyEntities } = useDummyStore.getState();
  const dummy = dummyEntities as FullDummy | null;

  const dummyData: AIData | null = isDummy && dummy
    ? {
        recommendations: dummy.analytics.recommendations,
        recommendationMeta: {
          data_points: dummy.analytics.recommendationMeta.data_points,
          data_sufficiency: dummy.analytics.recommendationMeta.data_sufficiency,
          measurement: dummy.analytics.recommendationMeta.measurement,
        },
        forecast: {
          predictions: dummy.analytics.forecast.predictions,
          data_sufficiency: dummy.analytics.forecast.data_sufficiency,
          method: dummy.analytics.forecast.method,
          measurement: dummy.analytics.forecast.measurement,
        },
        segmentation: {
          segments: dummy.analytics.segmentation.segments,
        },
      }
    : null;

  return withDummyRead(isDummy, dummyData as AIData, async () => {
    const headers = authHeaders(token);
    const [recommendations, forecast, segmentation] = await Promise.all([
      fetch(apiUrl('/ai/recommendations'), { headers }),
      fetch(apiUrl('/ai/forecast?period=weekly&horizon=4'), { headers }),
      fetch(apiUrl('/ai/segmentation'), { headers }),
    ]);
    const bodies = await Promise.all([
      recommendations.json(),
      forecast.json(),
      segmentation.json(),
    ]);
    const failed = bodies.find(
      (body, index) => ![recommendations, forecast, segmentation][index].ok,
    );
    if (failed) throw new Error(failed.message || 'Analitik AI tidak dapat dimuat.');
    return {
      recommendations: bodies[0].data.recommendations,
      recommendationMeta: {
        data_points: bodies[0].data.data_points,
        data_sufficiency: bodies[0].data.data_sufficiency,
        measurement: bodies[0].data.measurement,
      },
      forecast: bodies[1].data,
      segmentation: bodies[2].data,
    } as AIData;
  });
}

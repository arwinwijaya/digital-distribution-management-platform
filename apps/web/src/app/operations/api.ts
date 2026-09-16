/**
 * Operations page loader — wraps the readiness + issues read path.
 * Guarded with `withDummyRead` so zero network while dummy mode is ON.
 * (POST writes are out of scope for this task — T11.)
 */
import { withDummyRead } from '@/dummy/guards';
import { useDummyStore } from '@/dummy/store';
import type { FullDummy } from '@/dummy';
import { fetchReadiness, fetchIssues, FetchResult } from '@/lib/operations-api';
import type {
  OperationIssue,
  IssueFilters,
  IssueMeta,
  ReadinessData,
} from '@/lib/operations-types';

export type OperationsLoadResult = {
  readinessResult: FetchResult<ReadinessData>;
  issuesResult: FetchResult<{ issues: OperationIssue[]; meta: IssueMeta }>;
};

function okResult<T>(data: T): FetchResult<T> {
  return { ok: true, data, error: null, code: null, status: 200, raw: null };
}

/**
 * Load readiness + issues for a token with the given filters.
 * While dummy mode is ON, returns T6 aggregate `operations` with zero network.
 */
export async function loadOperations(
  token: string,
  filters: IssueFilters = {},
): Promise<OperationsLoadResult> {
  const { isDummy, dummyEntities } = useDummyStore.getState();
  const dummy = dummyEntities as FullDummy | null;

  const dummyValue: OperationsLoadResult | null = isDummy && dummy
    ? {
        readinessResult: okResult(dummy.operations.readiness),
        issuesResult: okResult({
          issues: dummy.operations.issues,
          meta: {
            page: filters.page ?? 1,
            limit: filters.limit ?? 20,
            total: dummy.operations.issues.length,
            has_more: false,
          },
        }),
      }
    : null;

  return withDummyRead(
    isDummy,
    dummyValue as OperationsLoadResult,
    async () => {
      const [readinessResult, issuesResult] = await Promise.all([
        fetchReadiness(token),
        fetchIssues(filters, token),
      ]);
      return { readinessResult, issuesResult };
    },
  );
}

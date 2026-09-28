/** Typed API failure with actual HTTP status and status-based retryability. */
export function isRetryableStatus(status: number | null): boolean {
  return status === null || status >= 500;
}

export class ApiError extends Error {
  readonly status: number | null;
  readonly retryable: boolean;

  constructor(status: number | null, message: string) {
    super(message);
    this.name = 'ApiError';
    this.status = status;
    this.retryable = isRetryableStatus(status);
  }
}

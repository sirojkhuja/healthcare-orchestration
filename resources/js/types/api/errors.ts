export class ApiError extends Error {
  constructor(
    public readonly code: string,
    message: string,
    public readonly details?: Record<string, string[]>,
    public readonly traceId?: string,
    public readonly correlationId?: string,
    public readonly status?: number,
  ) {
    super(message);
    this.name = 'ApiError';
  }
}

export const ErrorCode = {
  UNAUTHENTICATED: 'UNAUTHENTICATED',
  MFA_REQUIRED: 'MFA_REQUIRED',
  FORBIDDEN: 'FORBIDDEN',
  NOT_FOUND: 'NOT_FOUND',
  VALIDATION_ERROR: 'VALIDATION_ERROR',
  TENANT_MISMATCH: 'TENANT_MISMATCH',
  IDEMPOTENCY_REPLAY: 'IDEMPOTENCY_REPLAY',
  RATE_LIMITED: 'RATE_LIMITED',
  SERVER_ERROR: 'SERVER_ERROR',
} as const;

export type ErrorCode = (typeof ErrorCode)[keyof typeof ErrorCode];

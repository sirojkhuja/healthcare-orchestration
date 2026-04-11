import { useState } from 'react';
import { ApiError } from '@/types/api/errors';

interface ApiErrorAlertProps {
  error: unknown;
  className?: string;
}

export function ApiErrorAlert({ error, className }: ApiErrorAlertProps) {
  const [showDebug, setShowDebug] = useState(false);

  if (!error) return null;

  const apiError = error instanceof ApiError ? error : null;
  const message = apiError?.message ?? (error instanceof Error ? error.message : 'An unexpected error occurred');
  const isDev = import.meta.env.DEV;

  return (
    <div className={`rounded-md border border-red-200 bg-red-50 p-4 ${className ?? ''}`}>
      <div className="flex gap-3">
        <svg className="mt-0.5 h-5 w-5 shrink-0 text-red-400" viewBox="0 0 20 20" fill="currentColor">
          <path fillRule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.28 7.22a.75.75 0 00-1.06 1.06L8.94 10l-1.72 1.72a.75.75 0 101.06 1.06L10 11.06l1.72 1.72a.75.75 0 101.06-1.06L11.06 10l1.72-1.72a.75.75 0 00-1.06-1.06L10 8.94 8.28 7.22z" clipRule="evenodd" />
        </svg>
        <div className="flex-1">
          <p className="text-sm font-medium text-red-800">{message}</p>
          {apiError?.correlationId && (
            <p className="mt-1 text-xs text-red-600">
              Error ID: <span className="font-mono">{apiError.correlationId}</span>
            </p>
          )}
          {isDev && apiError && (
            <button
              onClick={() => setShowDebug((v) => !v)}
              className="mt-2 text-xs text-red-500 underline"
            >
              {showDebug ? 'Hide' : 'Show'} debug info
            </button>
          )}
          {isDev && showDebug && apiError && (
            <pre className="mt-2 overflow-auto rounded bg-red-100 p-2 text-xs text-red-700">
              {JSON.stringify({ code: apiError.code, traceId: apiError.traceId, details: apiError.details }, null, 2)}
            </pre>
          )}
        </div>
      </div>
    </div>
  );
}

import axios, { type AxiosInstance, type InternalAxiosRequestConfig, type AxiosResponse } from 'axios';
import { ApiError } from '@/types/api/errors';
import { tokenStorage } from '@/lib/auth/tokens';

// Filled in by the stores at runtime; avoids circular imports
let getAccessToken: () => string | null = () => null;
let getActiveTenantId: () => string | null = () => null;
let onTokenRefreshed: (accessToken: string, refreshToken: string) => void = () => undefined;
let onAuthFailure: () => void = () => undefined;
let onTenantMismatch: () => void = () => undefined;

export function configureApiClient(opts: {
  getAccessToken: () => string | null;
  getActiveTenantId: () => string | null;
  onTokenRefreshed: (accessToken: string, refreshToken: string) => void;
  onAuthFailure: () => void;
  onTenantMismatch: () => void;
}): void {
  getAccessToken = opts.getAccessToken;
  getActiveTenantId = opts.getActiveTenantId;
  onTokenRefreshed = opts.onTokenRefreshed;
  onAuthFailure = opts.onAuthFailure;
  onTenantMismatch = opts.onTenantMismatch;
}

const api: AxiosInstance = axios.create({
  baseURL: '/',
  headers: {
    'Content-Type': 'application/json',
    Accept: 'application/json',
    'X-Requested-With': 'XMLHttpRequest',
  },
});

// ─── Request interceptor ────────────────────────────────────────────────────
api.interceptors.request.use((config: InternalAxiosRequestConfig) => {
  const token = getAccessToken();
  if (token) {
    config.headers.Authorization = `Bearer ${token}`;
  }

  const tenantId = getActiveTenantId();
  if (tenantId) {
    config.headers['X-Tenant-Id'] = tenantId;
  }

  config.headers['X-Request-Id'] = crypto.randomUUID();

  return config;
});

// ─── Token refresh queue ─────────────────────────────────────────────────────
let isRefreshing = false;
let refreshQueue: Array<(token: string) => void> = [];

function processQueue(token: string): void {
  refreshQueue.forEach((resolve) => resolve(token));
  refreshQueue = [];
}

// ─── Response interceptor ────────────────────────────────────────────────────
api.interceptors.response.use(
  (response: AxiosResponse) => response,
  async (error) => {
    const originalRequest = error.config as InternalAxiosRequestConfig & { _retry?: boolean };
    const status: number = error.response?.status;
    const errorCode: string = error.response?.data?.code ?? '';
    const correlationId: string | undefined = error.response?.headers?.['x-correlation-id'];

    // 401 + not a retry → attempt token refresh
    if (status === 401 && !originalRequest._retry && errorCode !== 'MFA_REQUIRED') {
      const refreshToken = tokenStorage.getRefreshToken();
      if (!refreshToken) {
        onAuthFailure();
        return Promise.reject(buildApiError(error, correlationId));
      }

      if (isRefreshing) {
        return new Promise<AxiosResponse>((resolve) => {
          refreshQueue.push((newToken: string) => {
            originalRequest.headers.Authorization = `Bearer ${newToken}`;
            resolve(api(originalRequest));
          });
        });
      }

      originalRequest._retry = true;
      isRefreshing = true;

      try {
        const resp = await axios.post<{ tokens: { access_token: string; refresh_token: string } }>(
          '/api/v1/auth/refresh',
          { refresh_token: refreshToken },
          { headers: { 'Content-Type': 'application/json' } },
        );
        const { access_token, refresh_token } = resp.data.tokens;
        onTokenRefreshed(access_token, refresh_token);
        tokenStorage.setRefreshToken(refresh_token);
        processQueue(access_token);
        originalRequest.headers.Authorization = `Bearer ${access_token}`;
        return api(originalRequest);
      } catch {
        refreshQueue = [];
        tokenStorage.clearRefreshToken();
        onAuthFailure();
        return Promise.reject(buildApiError(error, correlationId));
      } finally {
        isRefreshing = false;
      }
    }

    // 403 TENANT_MISMATCH → clear tenant context
    if (status === 403 && errorCode === 'TENANT_MISMATCH') {
      onTenantMismatch();
    }

    return Promise.reject(buildApiError(error, correlationId));
  },
);

function buildApiError(error: unknown, correlationId?: string): ApiError {
  if (axios.isAxiosError(error)) {
    const data = error.response?.data as
      | { code?: string; message?: string; details?: Record<string, string[]>; traceId?: string }
      | undefined;
    return new ApiError(
      data?.code ?? 'SERVER_ERROR',
      data?.message ?? error.message,
      data?.details,
      data?.traceId,
      correlationId,
      error.response?.status,
    );
  }
  return new ApiError('SERVER_ERROR', 'An unexpected error occurred');
}

export default api;

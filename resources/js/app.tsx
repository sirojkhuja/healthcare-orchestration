import './bootstrap';
import '../css/app.css';

import React from 'react';
import { createRoot } from 'react-dom/client';
import { QueryClientProvider } from '@tanstack/react-query';
import { ReactQueryDevtools } from '@tanstack/react-query-devtools';
import { RouterProvider } from 'react-router';

import { queryClient } from '@/lib/query/queryClient';
import { router } from '@/router';
import { configureApiClient } from '@/lib/api/client';
import { useAuthStore } from '@/store/authStore';
import { useTenantStore } from '@/store/tenantStore';
import { tokenStorage } from '@/lib/auth/tokens';
import { ErrorBoundary } from '@/components/feedback/ErrorBoundary';

// Wire the API client to the stores (avoids circular imports at module init time)
configureApiClient({
  getAccessToken: () => useAuthStore.getState().accessToken,
  getActiveTenantId: () => useTenantStore.getState().activeTenantId,
  onTokenRefreshed: (accessToken, refreshToken) => {
    useAuthStore.getState().setAccessToken(accessToken);
    tokenStorage.setRefreshToken(refreshToken);
  },
  onAuthFailure: () => {
    useAuthStore.getState().clearAuth();
    window.location.replace('/login');
  },
  onTenantMismatch: () => {
    useTenantStore.getState().clearTenant();
    window.location.replace('/select-tenant');
  },
});

const root = document.getElementById('app');
if (!root) throw new Error('Root element #app not found');

createRoot(root).render(
  <React.StrictMode>
    <ErrorBoundary>
      <QueryClientProvider client={queryClient}>
        <RouterProvider router={router} />
        {import.meta.env.DEV && <ReactQueryDevtools initialIsOpen={false} />}
      </QueryClientProvider>
    </ErrorBoundary>
  </React.StrictMode>,
);

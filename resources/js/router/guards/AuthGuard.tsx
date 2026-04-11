import { Navigate, Outlet, useLocation } from 'react-router';
import { useEffect, useState } from 'react';
import { useAuthStore } from '@/store/authStore';
import { tokenStorage } from '@/lib/auth/tokens';
import api from '@/lib/api/client';
import { endpoints } from '@/lib/api/endpoints';
import type { AuthSessionResponse } from '@/types/api/auth';
import { Spinner } from '@/components/ui/Spinner';

export function AuthGuard() {
  const { isAuthenticated, setAuth, setAccessToken } = useAuthStore();
  const location = useLocation();
  const [checking, setChecking] = useState(!isAuthenticated);

  useEffect(() => {
    if (isAuthenticated) {
      setChecking(false);
      return;
    }
    const refreshToken = tokenStorage.getRefreshToken();
    if (!refreshToken) {
      setChecking(false);
      return;
    }
    // Silent refresh attempt
    api
      .post<{ tokens: { access_token: string; refresh_token: string }; user: AuthSessionResponse['user']; session: AuthSessionResponse['session']; permissions: string[]; tenant_memberships: AuthSessionResponse['tenant_memberships'] }>(
        endpoints.auth.refresh,
        { refresh_token: refreshToken },
      )
      .then((res) => {
        const { tokens, user, session, permissions, tenant_memberships } = res.data;
        setAuth({
          accessToken: tokens.access_token,
          refreshToken: tokens.refresh_token,
          user,
          sessionId: session.id,
          permissions,
          tenantMemberships: tenant_memberships,
        });
      })
      .catch(() => {
        tokenStorage.clearRefreshToken();
      })
      .finally(() => setChecking(false));
  }, [isAuthenticated, setAuth, setAccessToken]);

  if (checking) {
    return (
      <div className="flex min-h-screen items-center justify-center">
        <Spinner size="lg" />
      </div>
    );
  }

  if (!isAuthenticated) {
    return <Navigate to="/login" state={{ from: location }} replace />;
  }

  return <Outlet />;
}

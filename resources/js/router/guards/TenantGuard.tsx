import { Navigate, Outlet } from 'react-router';
import { useTenantStore } from '@/store/tenantStore';

export function TenantGuard() {
  const activeTenantId = useTenantStore((s) => s.activeTenantId);

  if (!activeTenantId) {
    return <Navigate to="/select-tenant" replace />;
  }

  return <Outlet />;
}

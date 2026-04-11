import { useAuthStore } from '@/store/authStore';

interface PermissionGateProps {
  permission: string;
  children: React.ReactNode;
  fallback?: React.ReactNode;
}

export function PermissionGate({ permission, children, fallback = null }: PermissionGateProps) {
  const hasPermission = useAuthStore((s) => s.hasPermission);
  return hasPermission(permission) ? <>{children}</> : <>{fallback}</>;
}

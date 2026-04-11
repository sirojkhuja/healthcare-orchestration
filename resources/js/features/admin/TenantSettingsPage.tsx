import { useQuery } from '@tanstack/react-query';
import api from '@/lib/api/client';
import { endpoints } from '@/lib/api/endpoints';
import { Badge } from '@/components/ui/Badge';
import { Spinner } from '@/components/ui/Spinner';
import { useTenantStore } from '@/store/tenantStore';
import { STALE } from '@/lib/query/queryClient';

interface TenantDetail {
  id: string;
  name: string;
  slug: string;
  is_active: boolean;
  limits: {
    max_providers: number;
    max_appointments_per_month: number;
  };
  usage: {
    active_providers: number;
    appointments_this_month: number;
  };
  settings: Record<string, unknown>;
}

function UsageMeter({ used, max, label }: { used: number; max: number; label: string }) {
  const pct = max > 0 ? Math.min(100, Math.round((used / max) * 100)) : 0;
  const color = pct >= 90 ? 'bg-red-500' : pct >= 70 ? 'bg-amber-500' : 'bg-blue-500';

  return (
    <div>
      <div className="flex items-center justify-between text-sm mb-1">
        <span className="text-gray-700">{label}</span>
        <span className="font-medium text-gray-900">{used} / {max}</span>
      </div>
      <div className="h-2 w-full rounded-full bg-gray-200 overflow-hidden">
        <div className={`h-2 rounded-full transition-all ${color}`} style={{ width: `${pct}%` }} />
      </div>
    </div>
  );
}

export default function TenantSettingsPage() {
  const activeTenantId = useTenantStore((s) => s.activeTenantId);

  const { data: tenant, isLoading } = useQuery({
    queryKey: ['tenant', 'detail', activeTenantId],
    queryFn: () => api.get<TenantDetail>(endpoints.tenant(activeTenantId!)).then((r) => r.data),
    staleTime: STALE.DETAIL,
    enabled: !!activeTenantId,
  });

  if (isLoading) return <div className="flex justify-center py-16"><Spinner size="lg" /></div>;
  if (!tenant) return <p className="text-center text-gray-500 py-16">Tenant not found.</p>;

  return (
    <div className="flex flex-col gap-8 max-w-3xl">
      <h1 className="text-2xl font-semibold text-gray-900">Tenant Settings</h1>

      {/* Overview */}
      <div className="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
        <div className="flex items-center justify-between">
          <div>
            <h2 className="text-xl font-semibold text-gray-900">{tenant.name}</h2>
            <p className="text-sm text-gray-500 font-mono">{tenant.slug}</p>
          </div>
          <Badge variant={tenant.is_active ? 'green' : 'gray'}>
            {tenant.is_active ? 'Active' : 'Inactive'}
          </Badge>
        </div>
      </div>

      {/* Usage */}
      <div className="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
        <h2 className="font-semibold text-gray-900 mb-4">Usage</h2>
        <div className="space-y-4">
          <UsageMeter
            used={tenant.usage.active_providers}
            max={tenant.limits.max_providers}
            label="Active providers"
          />
          <UsageMeter
            used={tenant.usage.appointments_this_month}
            max={tenant.limits.max_appointments_per_month}
            label="Appointments this month"
          />
        </div>
      </div>

      {/* Limits */}
      <div className="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
        <h2 className="font-semibold text-gray-900 mb-3">Plan limits</h2>
        <dl className="grid grid-cols-2 gap-3 text-sm">
          <div>
            <dt className="text-gray-500">Max providers</dt>
            <dd className="font-medium text-gray-900">{tenant.limits.max_providers}</dd>
          </div>
          <div>
            <dt className="text-gray-500">Appointments/month</dt>
            <dd className="font-medium text-gray-900">{tenant.limits.max_appointments_per_month.toLocaleString()}</dd>
          </div>
        </dl>
      </div>

      {/* Settings */}
      {Object.keys(tenant.settings).length > 0 && (
        <div className="rounded-xl border border-gray-200 bg-white p-6 shadow-sm">
          <h2 className="font-semibold text-gray-900 mb-3">Configuration</h2>
          <dl className="grid grid-cols-2 gap-3 text-sm">
            {Object.entries(tenant.settings).map(([key, value]) => (
              <div key={key}>
                <dt className="text-gray-500 capitalize">{key.replace('_', ' ')}</dt>
                <dd className="font-medium text-gray-900">{String(value)}</dd>
              </div>
            ))}
          </dl>
        </div>
      )}
    </div>
  );
}

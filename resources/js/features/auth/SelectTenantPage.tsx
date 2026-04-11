import { useNavigate } from 'react-router';
import { useEffect } from 'react';
import { useAuthStore } from '@/store/authStore';
import { useTenantStore } from '@/store/tenantStore';
import { Button } from '@/components/ui/Button';

export default function SelectTenantPage() {
  const navigate = useNavigate();
  const tenantMemberships = useAuthStore((s) => s.tenantMemberships);
  const clearAuth = useAuthStore((s) => s.clearAuth);
  const { setActiveTenant } = useTenantStore();

  // If only one tenant, auto-redirect
  useEffect(() => {
    if (tenantMemberships.length === 1 && tenantMemberships[0]) {
      setActiveTenant(tenantMemberships[0].tenant_id);
      navigate('/dashboard', { replace: true });
    }
  }, [tenantMemberships, setActiveTenant, navigate]);

  const handleSelect = (tenantId: string) => {
    setActiveTenant(tenantId);
    navigate('/dashboard', { replace: true });
  };

  const handleLogout = () => {
    clearAuth();
    navigate('/login', { replace: true });
  };

  return (
    <div className="flex min-h-screen items-center justify-center bg-gray-50 px-4 py-12">
      <div className="w-full max-w-lg">
        <div className="mb-6 text-center">
          <h1 className="text-2xl font-bold text-blue-600">MedFlow</h1>
          <h2 className="mt-4 text-lg font-semibold text-gray-900">Choose a workspace</h2>
          <p className="mt-1 text-sm text-gray-500">Select the clinic you want to work in today</p>
        </div>

        <div className="flex flex-col gap-3">
          {tenantMemberships.map((membership) => (
            <button
              key={membership.tenant_id}
              onClick={() => handleSelect(membership.tenant_id)}
              className="flex items-center justify-between rounded-xl border border-gray-200 bg-white p-5 text-left shadow-sm transition hover:border-blue-300 hover:shadow-md"
            >
              <div className="flex items-center gap-4">
                {membership.tenant_logo_url ? (
                  <img src={membership.tenant_logo_url} alt="" className="h-10 w-10 rounded-lg object-cover" />
                ) : (
                  <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-blue-100 text-lg font-bold text-blue-600">
                    {membership.tenant_name.charAt(0)}
                  </div>
                )}
                <div>
                  <p className="font-medium text-gray-900">{membership.tenant_name}</p>
                  <p className="text-sm text-gray-500 capitalize">{membership.role}</p>
                </div>
              </div>
              <svg className="h-5 w-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M9 5l7 7-7 7" />
              </svg>
            </button>
          ))}
        </div>

        <div className="mt-6 text-center">
          <Button variant="ghost" size="sm" onClick={handleLogout}>
            Sign out
          </Button>
        </div>
      </div>
    </div>
  );
}

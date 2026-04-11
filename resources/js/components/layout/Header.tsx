import { useUiStore } from '@/store/uiStore';
import { useTenantStore } from '@/store/tenantStore';
import { useAuthStore } from '@/store/authStore';
import { cn } from '@/lib/utils/cn';

export function Header() {
  const { toggleSidebar, sidebarOpen } = useUiStore();
  const { activeTenantId, tenantList, setActiveTenant } = useTenantStore();
  const user = useAuthStore((s) => s.user);

  const activeTenant = tenantList.find((t) => t.tenant_id === activeTenantId);

  return (
    <header
      className={cn(
        'fixed top-0 right-0 z-20 flex h-14 items-center justify-between border-b border-gray-200 bg-white px-4 transition-all duration-200',
        sidebarOpen ? 'left-60' : 'left-16',
      )}
    >
      <div className="flex items-center gap-3">
        <button
          onClick={toggleSidebar}
          className="rounded-md p-1.5 text-gray-400 hover:bg-gray-100 hover:text-gray-600"
          aria-label="Toggle sidebar"
        >
          <svg className="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 6h16M4 12h16M4 18h16" />
          </svg>
        </button>

        {activeTenant && (
          <span className="text-sm font-medium text-gray-700">{activeTenant.tenant_name}</span>
        )}
      </div>

      <div className="flex items-center gap-3">
        {/* Tenant switcher — only shown when user has multiple memberships */}
        {tenantList.length > 1 && (
          <select
            value={activeTenantId ?? ''}
            onChange={(e) => setActiveTenant(e.target.value)}
            className="rounded-md border border-gray-200 bg-white px-2 py-1 text-xs text-gray-600 focus:outline-none focus:ring-1 focus:ring-blue-500"
          >
            {tenantList.map((t) => (
              <option key={t.tenant_id} value={t.tenant_id}>
                {t.tenant_name}
              </option>
            ))}
          </select>
        )}

        {/* Notification bell placeholder */}
        <button className="relative rounded-md p-1.5 text-gray-400 hover:bg-gray-100 hover:text-gray-600">
          <svg className="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
          </svg>
        </button>

        {/* User avatar */}
        {user && (
          <div className="flex h-8 w-8 items-center justify-center rounded-full bg-blue-100 text-sm font-medium text-blue-600">
            {user.name.charAt(0).toUpperCase()}
          </div>
        )}
      </div>
    </header>
  );
}

import { create } from 'zustand';
import { queryClient } from '@/lib/query/queryClient';
import type { TenantMembership } from '@/types/api/auth';

interface TenantState {
  activeTenantId: string | null;
  tenantList: TenantMembership[];
}

interface TenantActions {
  setActiveTenant: (tenantId: string) => void;
  setTenantList: (list: TenantMembership[]) => void;
  clearTenant: () => void;
}

export const useTenantStore = create<TenantState & TenantActions>((set) => ({
  activeTenantId: null,
  tenantList: [],

  setActiveTenant: (tenantId) => {
    // Clear all cached data when switching tenants to prevent data leakage
    queryClient.clear();
    set({ activeTenantId: tenantId });
  },

  setTenantList: (list) => set({ tenantList: list }),

  clearTenant: () => {
    queryClient.clear();
    set({ activeTenantId: null, tenantList: [] });
  },
}));

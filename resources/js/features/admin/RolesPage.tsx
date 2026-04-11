import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import api from '@/lib/api/client';
import { endpoints } from '@/lib/api/endpoints';
import { Button } from '@/components/ui/Button';
import { Badge } from '@/components/ui/Badge';
import { Spinner } from '@/components/ui/Spinner';
import { ApiErrorAlert } from '@/components/feedback/ApiErrorAlert';
import { STALE } from '@/lib/query/queryClient';
import type { PaginatedResponse } from '@/types/common';

interface Role { id: string; name: string; user_count: number; permissions: string[] }
interface Permission { id: string; key: string; group: string; label: string }

const PERMISSION_GROUPS = [
  'patients', 'providers', 'scheduling', 'treatment', 'lab', 'pharmacy',
  'billing', 'insurance', 'notifications', 'users', 'rbac', 'tenants', 'compliance', 'ops',
];

export default function RolesPage() {
  const qc = useQueryClient();
  const [selectedRole, setSelectedRole] = useState<Role | null>(null);
  const [saveError, setSaveError] = useState<unknown>(null);

  const { data: roles, isLoading } = useQuery({
    queryKey: ['roles', 'list'],
    queryFn: () => api.get<PaginatedResponse<Role>>(endpoints.roles).then((r) => r.data),
    staleTime: STALE.REFERENCE,
  });

  const { data: allPermissions } = useQuery({
    queryKey: ['permissions', 'all'],
    queryFn: () => api.get<Permission[]>('/api/v1/permissions').then((r) => r.data),
    staleTime: STALE.REFERENCE,
  });

  const { mutate: savePermissions, isPending: saving } = useMutation({
    mutationFn: ({ roleId, permissions }: { roleId: string; permissions: string[] }) =>
      api.put(endpoints.rolePermissions(roleId), { permissions }),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['roles', 'list'] });
      setSaveError(null);
    },
    onError: setSaveError,
  });

  const [localPermissions, setLocalPermissions] = useState<Set<string>>(new Set());

  const selectRole = (role: Role) => {
    setSelectedRole(role);
    setLocalPermissions(new Set(role.permissions));
  };

  const togglePermission = (key: string) => {
    setLocalPermissions((prev) => {
      const next = new Set(prev);
      if (next.has(key)) next.delete(key);
      else next.add(key);
      return next;
    });
  };

  const groupedPermissions = PERMISSION_GROUPS.reduce<Record<string, Permission[]>>((acc, group) => {
    acc[group] = (allPermissions ?? []).filter((p) => p.group === group);
    return acc;
  }, {});

  return (
    <div className="flex flex-col gap-6">
      <h1 className="text-2xl font-semibold text-gray-900">Roles & Permissions</h1>

      <div className="grid grid-cols-1 gap-6 lg:grid-cols-3">
        {/* Role list */}
        <div className="rounded-xl border border-gray-200 bg-white shadow-sm overflow-hidden">
          <div className="border-b border-gray-100 px-4 py-3">
            <h2 className="font-semibold text-gray-900">Roles</h2>
          </div>
          {isLoading && <div className="flex justify-center py-8"><Spinner /></div>}
          <ul className="divide-y divide-gray-100">
            {(roles?.data ?? []).map((role) => (
              <li key={role.id}>
                <button
                  onClick={() => selectRole(role)}
                  className={`w-full text-left px-4 py-3 hover:bg-gray-50 transition-colors ${selectedRole?.id === role.id ? 'bg-blue-50 border-l-2 border-blue-500' : ''}`}
                >
                  <div className="flex items-center justify-between">
                    <span className="text-sm font-medium text-gray-900 capitalize">{role.name}</span>
                    <Badge variant="gray">{role.user_count} users</Badge>
                  </div>
                  <p className="text-xs text-gray-400 mt-0.5">{role.permissions.length} permissions</p>
                </button>
              </li>
            ))}
          </ul>
        </div>

        {/* Permission matrix */}
        <div className="lg:col-span-2 rounded-xl border border-gray-200 bg-white shadow-sm overflow-hidden">
          {!selectedRole ? (
            <div className="flex items-center justify-center py-16 text-sm text-gray-400">
              Select a role to edit permissions
            </div>
          ) : (
            <>
              <div className="flex items-center justify-between border-b border-gray-100 px-5 py-4">
                <h2 className="font-semibold text-gray-900 capitalize">{selectedRole.name} — permissions</h2>
                <div className="flex items-center gap-3">
                  {saveError && <span className="text-xs text-red-600">Failed to save</span>}
                  <Button
                    size="sm"
                    isLoading={saving}
                    onClick={() => savePermissions({ roleId: selectedRole.id, permissions: Array.from(localPermissions) })}
                  >
                    Save permissions
                  </Button>
                </div>
              </div>
              <div className="overflow-y-auto max-h-[60vh] p-5 space-y-5">
                {saveError && <ApiErrorAlert error={saveError} />}
                {PERMISSION_GROUPS.map((group) => {
                  const perms = groupedPermissions[group] ?? [];
                  if (perms.length === 0) return null;
                  return (
                    <div key={group}>
                      <h3 className="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2 capitalize">{group}</h3>
                      <div className="flex flex-wrap gap-3">
                        {perms.map((p) => (
                          <label key={p.id} className="flex items-center gap-2 cursor-pointer">
                            <input
                              type="checkbox"
                              checked={localPermissions.has(p.key)}
                              onChange={() => togglePermission(p.key)}
                              className="rounded border-gray-300 text-blue-600"
                            />
                            <span className="text-sm text-gray-700">{p.label}</span>
                          </label>
                        ))}
                      </div>
                    </div>
                  );
                })}
              </div>
            </>
          )}
        </div>
      </div>
    </div>
  );
}

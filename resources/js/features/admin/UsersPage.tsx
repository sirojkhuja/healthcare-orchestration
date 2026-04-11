import { useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import { type ColumnDef } from '@tanstack/react-table';
import { format } from 'date-fns';
import api from '@/lib/api/client';
import { endpoints } from '@/lib/api/endpoints';
import { DataTable } from '@/components/ui/DataTable';
import { Badge } from '@/components/ui/Badge';
import { Input } from '@/components/ui/Input';
import { PermissionGate } from '@/components/shared/PermissionGate';
import { Button } from '@/components/ui/Button';
import { STALE } from '@/lib/query/queryClient';
import type { PaginatedResponse } from '@/types/common';

interface UserRow {
  id: string;
  first_name: string;
  last_name: string;
  full_name: string;
  email: string;
  role_names: string[];
  status: 'active' | 'inactive' | 'locked';
  last_login_at?: string;
}

export default function UsersPage() {
  const [page, setPage] = useState(1);
  const [search, setSearch] = useState('');

  const { data, isLoading } = useQuery({
    queryKey: ['users', 'list', page, search],
    queryFn: () =>
      api.get<PaginatedResponse<UserRow>>(endpoints.users, {
        params: { page, per_page: 25, q: search || undefined },
      }).then((r) => r.data),
    staleTime: STALE.LIST,
  });

  const columns: ColumnDef<UserRow>[] = [
    {
      header: 'Name',
      cell: ({ row }) => (
        <div className="flex items-center gap-3">
          <div className="h-8 w-8 rounded-full bg-gray-200 flex items-center justify-center text-sm font-semibold text-gray-600">
            {row.original.first_name[0]}{row.original.last_name[0]}
          </div>
          <div>
            <p className="text-sm font-medium text-gray-900">{row.original.full_name}</p>
            <p className="text-xs text-gray-400">{row.original.email}</p>
          </div>
        </div>
      ),
    },
    {
      header: 'Roles',
      cell: ({ row }) => (
        <div className="flex flex-wrap gap-1">
          {row.original.role_names.map((role) => (
            <Badge key={role} variant="gray" className="capitalize">{role}</Badge>
          ))}
        </div>
      ),
    },
    {
      header: 'Status',
      cell: ({ row }) => (
        <Badge variant={row.original.status === 'active' ? 'green' : row.original.status === 'locked' ? 'red' : 'gray'} className="capitalize">
          {row.original.status}
        </Badge>
      ),
    },
    {
      header: 'Last login',
      cell: ({ row }) => row.original.last_login_at
        ? format(new Date(row.original.last_login_at), 'MMM d, yyyy')
        : <span className="text-gray-400">Never</span>,
    },
  ];

  return (
    <div className="flex flex-col gap-6">
      <div className="flex items-center justify-between">
        <h1 className="text-2xl font-semibold text-gray-900">Users</h1>
        <PermissionGate permission="users.manage">
          <Button>+ Invite user</Button>
        </PermissionGate>
      </div>

      <div className="flex items-center gap-3">
        <div className="flex-1">
          <Input
            placeholder="Search by name or email…"
            value={search}
            onChange={(e) => { setSearch(e.target.value); setPage(1); }}
          />
        </div>
      </div>

      <DataTable
        columns={columns}
        data={data?.data ?? []}
        isLoading={isLoading}
        pagination={data ? { page, pageSize: 25, total: data.meta.total, onPageChange: setPage } : undefined}
        emptyTitle="No users found"
        emptyDescription="Users will appear here once invited to this workspace."
      />
    </div>
  );
}

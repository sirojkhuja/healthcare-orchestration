import { useState } from 'react';
import { useNavigate } from 'react-router';
import { useQuery } from '@tanstack/react-query';
import { type ColumnDef } from '@tanstack/react-table';
import api from '@/lib/api/client';
import { endpoints } from '@/lib/api/endpoints';
import { DataTable } from '@/components/ui/DataTable';
import { Button } from '@/components/ui/Button';
import { Input } from '@/components/ui/Input';
import { Badge } from '@/components/ui/Badge';
import { STALE } from '@/lib/query/queryClient';
import type { Provider, ProviderFilters } from '@/types/api/providers';
import type { PaginatedResponse } from '@/types/common';

export default function ProviderListPage() {
  const navigate = useNavigate();
  const [filters, setFilters] = useState<ProviderFilters>({ page: 1, per_page: 25 });
  const [search, setSearch] = useState('');

  const { data, isLoading } = useQuery({
    queryKey: ['providers', 'list', filters],
    queryFn: () =>
      api.get<PaginatedResponse<Provider>>(endpoints.providers, { params: filters }).then((r) => r.data),
    staleTime: STALE.LIST,
  });

  const columns: ColumnDef<Provider>[] = [
    {
      header: 'Name',
      cell: ({ row }) => (
        <div className="flex items-center gap-3">
          <div className="h-8 w-8 rounded-full bg-blue-100 flex items-center justify-center text-sm font-semibold text-blue-700">
            {row.original.first_name[0]}{row.original.last_name[0]}
          </div>
          <span className="font-medium text-gray-900">{row.original.full_name}</span>
        </div>
      ),
    },
    {
      header: 'Specialties',
      cell: ({ row }) => (
        <div className="flex flex-wrap gap-1">
          {row.original.specialties.slice(0, 2).map((s) => (
            <Badge key={s.id} variant="blue">{s.name}</Badge>
          ))}
          {row.original.specialties.length > 2 && (
            <Badge variant="gray">+{row.original.specialties.length - 2}</Badge>
          )}
        </div>
      ),
    },
    { header: 'Clinic', accessorKey: 'clinic_name', cell: ({ getValue }) => getValue<string>() ?? '—' },
    { header: 'Department', accessorKey: 'department_name', cell: ({ getValue }) => getValue<string>() ?? '—' },
    {
      header: 'Phone',
      cell: ({ row }) => row.original.primary_phone?.number ?? '—',
    },
    {
      header: 'Status',
      cell: ({ row }) => (
        <Badge variant={row.original.is_active ? 'green' : 'gray'}>
          {row.original.is_active ? 'Active' : 'Inactive'}
        </Badge>
      ),
    },
  ];

  return (
    <div className="flex flex-col gap-6">
      <div className="flex items-center justify-between">
        <h1 className="text-2xl font-semibold text-gray-900">Providers</h1>
      </div>

      <div className="flex items-center gap-3">
        <div className="flex-1">
          <Input
            placeholder="Search by name or specialty…"
            value={search}
            onChange={(e) => setSearch(e.target.value)}
            onKeyDown={(e) => e.key === 'Enter' && setFilters((f) => ({ ...f, q: search || undefined, page: 1 }))}
          />
        </div>
        <Button variant="secondary" onClick={() => setFilters((f) => ({ ...f, q: search || undefined, page: 1 }))}>
          Search
        </Button>
      </div>

      <DataTable
        columns={columns}
        data={data?.data ?? []}
        isLoading={isLoading}
        onRowClick={(row) => navigate(`/providers/${row.id}`)}
        pagination={data ? {
          page: filters.page ?? 1,
          pageSize: filters.per_page ?? 25,
          total: data.meta.total,
          onPageChange: (page) => setFilters((f) => ({ ...f, page })),
        } : undefined}
        emptyTitle="No providers found"
        emptyDescription="Try adjusting your search filters."
      />
    </div>
  );
}

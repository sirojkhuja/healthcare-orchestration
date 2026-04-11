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
import { PermissionGate } from '@/components/shared/PermissionGate';
import type { Patient, PatientFilters } from '@/types/api/patients';
import type { PaginatedResponse } from '@/types/common';
import { STALE } from '@/lib/query/queryClient';

export default function PatientListPage() {
  const navigate = useNavigate();
  const [filters, setFilters] = useState<PatientFilters>({ page: 1, per_page: 25 });
  const [search, setSearch] = useState('');

  const { data, isLoading } = useQuery({
    queryKey: ['patients', 'list', filters],
    queryFn: () =>
      api.get<PaginatedResponse<Patient>>(endpoints.patients, { params: filters }).then((r) => r.data),
    staleTime: STALE.LIST,
  });

  const columns: ColumnDef<Patient>[] = [
    { header: 'Name', accessorKey: 'full_name', cell: ({ row }) => (
      <span className="font-medium text-blue-600 hover:underline cursor-pointer">{row.original.full_name}</span>
    )},
    { header: 'Date of Birth', accessorKey: 'date_of_birth' },
    { header: 'Phone', cell: ({ row }) => row.original.primary_phone?.number ?? '—' },
    { header: 'National ID', cell: ({ row }) => row.original.national_id ? `••••${row.original.national_id.slice(-4)}` : '—' },
    { header: 'Tags', cell: ({ row }) => (
      <div className="flex flex-wrap gap-1">
        {row.original.tags.slice(0, 3).map((tag) => (
          <Badge key={tag} variant="gray">{tag}</Badge>
        ))}
      </div>
    )},
    { header: 'Status', cell: ({ row }) => (
      <Badge variant={row.original.is_active ? 'green' : 'gray'}>
        {row.original.is_active ? 'Active' : 'Inactive'}
      </Badge>
    )},
  ];

  const handleSearch = () => {
    setFilters((f) => ({ ...f, q: search || undefined, page: 1 }));
  };

  return (
    <div className="flex flex-col gap-6">
      <div className="flex items-center justify-between">
        <h1 className="text-2xl font-semibold text-gray-900">Patients</h1>
        <PermissionGate permission="patients.manage">
          <Button onClick={() => navigate('/patients/new')}>+ New Patient</Button>
        </PermissionGate>
      </div>

      <div className="flex items-center gap-3">
        <div className="flex-1">
          <Input
            placeholder="Search by name, national ID, or phone…"
            value={search}
            onChange={(e) => setSearch(e.target.value)}
            onKeyDown={(e) => e.key === 'Enter' && handleSearch()}
          />
        </div>
        <Button variant="secondary" onClick={handleSearch}>Search</Button>
        <Button
          variant="secondary"
          onClick={() => {
            const params = new URLSearchParams(filters as Record<string, string>);
            window.open(`${endpoints.patientExport}?${params}`, '_blank');
          }}
        >
          Export CSV
        </Button>
      </div>

      <DataTable
        columns={columns}
        data={data?.data ?? []}
        isLoading={isLoading}
        onRowClick={(row) => navigate(`/patients/${row.id}`)}
        pagination={data ? {
          page: filters.page ?? 1,
          pageSize: filters.per_page ?? 25,
          total: data.meta.total,
          onPageChange: (page) => setFilters((f) => ({ ...f, page })),
        } : undefined}
        emptyTitle="No patients found"
        emptyDescription="Try adjusting your search or filters."
      />
    </div>
  );
}
